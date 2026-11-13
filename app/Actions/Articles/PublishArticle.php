<?php

namespace App\Actions\Articles;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\MediaUsage;
use App\Services\AdminNotifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publikasikan draft (Publish Now, atau eksekusi jadwal; SRS 11.2 dan FR-WF-012). Teks disalin ke
 * kolom utama dan gambar draft disalin menjadi gambar published.
 */
class PublishArticle
{
    use ValidatesArticleFields;

    /**
     * @param  Carbon|null  $publishedAt  waktu terbit pertama; bawaan: sekarang. Jadwal memakai waktu jadwalnya.
     *
     * @throws ValidationException
     */
    public function handle(Article $article, ?int $userId = null, ?string $editorId = null, ?Carbon $publishedAt = null): Article
    {
        $wasPublished = $article->published_at !== null;

        $published = DB::transaction(function () use ($article, $userId, $editorId, $publishedAt): Article {
            $locked = Article::query()->lockForUpdate()->findOrFail($article->getKey());
            $draft = $locked->draftFields();

            $this->validateFields($draft, $locked, checkDraftSlugs: false);
            $this->assertImagesReady($locked);

            $before = $locked->published_at ? $locked->publishedFields() : null;

            $locked->forceFill([
                ...$draft,
                'draft_data' => $draft,
                'draft_version' => $locked->draft_version + 1,
                'draft_editor' => $editorId,
                'status' => ArticleStatus::Published,
                'scheduled_at' => null,
                'published_at' => $locked->published_at ?? $publishedAt ?? now(),
                'updated_by' => $userId,
            ])->save();

            $locked->copyMedia(fromDraft: true, toDraft: false);

            activity('article')
                ->performedOn($locked)
                ->causedBy($userId)
                ->event('published')
                ->withProperties(['old' => $before, 'attributes' => $draft])
                ->log('Article published');

            return $locked->refresh();
        });

        app(AdminNotifier::class)->articlePublished($published, $wasPublished);

        return $published;
    }

    /** Gambar yang belum siap tidak punya varian dan akan tampil kosong; publikasi ditahan. */
    private function assertImagesReady(Article $article): void
    {
        $notReady = $article->usages(draft: true)->filter(fn (MediaUsage $u): bool => ! $u->file->isReady());

        if ($notReady->isNotEmpty()) {
            throw ValidationException::withMessages([
                'media' => "{$notReady->count()} gambar belum siap (masih diproses atau gagal). "
                    .'Tunggu hingga selesai, atau hapus gambar yang gagal, lalu publish lagi.',
            ]);
        }
    }
}
