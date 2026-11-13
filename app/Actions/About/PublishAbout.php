<?php

namespace App\Actions\About;

use App\Enums\PageStatus;
use App\Models\AboutPage;
use App\Support\AboutContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Publikasikan draft About Us: versi yang tampil di website digantikan oleh draft (SRS 11.2). */
class PublishAbout
{
    /**
     * @throws ValidationException
     */
    public function handle(AboutPage $page, ?int $userId = null, ?string $editorId = null): AboutPage
    {
        return DB::transaction(function () use ($page, $userId, $editorId): AboutPage {
            $locked = AboutPage::query()->lockForUpdate()->findOrFail($page->getKey());
            $draft = AboutContent::validated($locked->draft ?? []);

            // Halaman tanpa isi apa pun akan tampil kosong di website.
            if (! AboutContent::hasContent($draft)) {
                throw ValidationException::withMessages([
                    'overview' => 'Isi minimal judul atau uraian pada Company Overview sebelum mempublikasikan.',
                ]);
            }

            $this->assertPhotosReady($locked);

            $before = $locked->publishedContent();

            $locked->forceFill([
                'draft' => $draft,
                'published' => $draft,
                // Versi naik agar tab lain yang masih membawa versi lama ditolak saat menyimpan.
                'draft_version' => $locked->draft_version + 1,
                'draft_editor' => $editorId,
                'status' => PageStatus::Published,
                'published_at' => $locked->published_at ?? now(),
                'updated_by' => $userId,
            ])->save();

            activity('about')
                ->performedOn($locked)
                ->event('published')
                ->withProperties(['old' => $before, 'attributes' => $draft])
                ->log('About Us published');

            // Foto draft menjadi foto published (SRS 11.2 langkah 6).
            $locked->copyMedia(fromDraft: true, toDraft: false);

            return $locked->refresh();
        });
    }

    /** Foto yang belum selesai diproses tidak punya varian dan akan tampil kosong; publikasi ditahan. */
    private function assertPhotosReady(AboutPage $page): void
    {
        $notReady = $page->usages(draft: true)->filter(fn ($usage): bool => ! $usage->file->isReady());

        if ($notReady->isNotEmpty()) {
            throw ValidationException::withMessages([
                'media' => "{$notReady->count()} foto belum siap (masih diproses atau gagal). "
                    .'Tunggu hingga selesai, atau hapus foto yang gagal, lalu publish lagi.',
            ]);
        }
    }
}
