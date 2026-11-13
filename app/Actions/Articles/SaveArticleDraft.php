<?php

namespace App\Actions\Articles;

use App\Actions\Projects\StaleDraftException;
use App\Models\Article;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Simpan hasil editan sebagai draft (auto-save, SRS 11.1). Versi published tidak disentuh.
 * Pengaman tabrakan sama dengan SaveProjectDraft (SRS FR-WF-004).
 */
class SaveArticleDraft
{
    use ValidatesArticleFields;

    /**
     * @param  array<string, mixed>  $fields
     *
     * @throws StaleDraftException
     * @throws ValidationException
     */
    public function handle(Article $article, array $fields, int $expectedVersion, ?int $userId = null, ?string $editorId = null): Article
    {
        $fields = collect($fields)->only(Article::FIELDS)->all();

        return DB::transaction(function () use ($article, $fields, $expectedVersion, $userId, $editorId): Article {
            $locked = Article::query()->lockForUpdate()->findOrFail($article->getKey());

            $sameEditor = $editorId !== null && $locked->draft_editor === $editorId;

            if ($locked->draft_version !== $expectedVersion && ! $sameEditor) {
                throw new StaleDraftException;
            }

            $merged = array_merge($locked->draftFields(), $fields);
            $this->validateFields($merged, $locked, checkDraftSlugs: true);

            $locked->forceFill([
                'draft_data' => $merged,
                'draft_version' => $locked->draft_version + 1,
                'draft_editor' => $editorId,
                'updated_by' => $userId,
            ])->save();

            $locked->syncStatus();

            return $locked->refresh();
        });
    }
}
