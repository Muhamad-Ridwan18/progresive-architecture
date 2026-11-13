<?php

namespace App\Actions\Articles;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Buang editan yang belum dipublikasikan dan kembali ke versi published. */
class DiscardArticleDraft
{
    public function handle(Article $article, ?int $userId = null, ?string $editorId = null): Article
    {
        if ($article->published_at === null) {
            throw new RuntimeException('Artikel belum pernah dipublikasikan, tidak ada versi untuk dikembalikan.');
        }

        return DB::transaction(function () use ($article, $userId, $editorId): Article {
            $locked = Article::query()->lockForUpdate()->findOrFail($article->getKey());

            $locked->forceFill([
                'draft_data' => $locked->publishedFields(),
                'draft_version' => $locked->draft_version + 1,
                'draft_editor' => $editorId,
                'status' => ArticleStatus::Published,
                'updated_by' => $userId,
            ])->save();

            $locked->copyMedia(fromDraft: false, toDraft: true);

            activity('article')->performedOn($locked)->event('draft_discarded')->log('Article draft discarded');

            return $locked->refresh();
        });
    }
}
