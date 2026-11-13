<?php

namespace App\Actions\Articles;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Validation\ValidationException;

/** Buat artikel baru berstatus Draft. Hanya judul dan kategori yang wajib; sisanya diisi di editor. */
class CreateArticle
{
    use ValidatesArticleFields;

    /**
     * @param  array{title:string, category_id:int|string}  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data, ?int $userId = null): Article
    {
        $fields = [
            'title' => $data['title'] ?? null,
            'slug' => $this->uniqueSlug((string) ($data['title'] ?? '')),
            'category_id' => $data['category_id'] ?? null,
            'excerpt' => null,
            'content' => null,
            'author_id' => $userId,
        ];

        $this->validateFields($fields, null, checkDraftSlugs: true);

        $article = Article::create([
            ...$fields,
            'draft_data' => $fields,
            'status' => ArticleStatus::Draft,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        activity('article')
            ->performedOn($article)
            ->event('created')
            ->withProperties(['attributes' => ['title' => $article->title, 'slug' => $article->slug]])
            ->log('Article created');

        return $article->refresh();
    }
}
