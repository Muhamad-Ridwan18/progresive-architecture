<?php

namespace App\Actions\Articles;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Aturan validasi field artikel yang dipakai bersama oleh aksi buat, simpan draft, dan publish. */
trait ValidatesArticleFields
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  bool  $checkDraftSlugs  juga bandingkan dengan slug draft artikel lain (saat menyimpan draft)
     *
     * @throws ValidationException
     */
    protected function validateFields(array $fields, ?Article $article, bool $checkDraftSlugs): void
    {
        $ignoreId = $article?->getKey();

        Validator::make($fields, [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('articles', 'slug')->ignore($ignoreId),
                function (string $attribute, mixed $value, \Closure $fail) use ($ignoreId, $checkDraftSlugs): void {
                    if ($checkDraftSlugs && Article::query()
                        ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                        ->where('draft_data->slug', $value)
                        ->exists()) {
                        $fail('Slug sudah dipakai artikel lain.');
                    }
                },
            ],
            'category_id' => [
                'required', 'integer',
                Rule::exists('categories', 'id')->where('type', Article::categoryType()->value),
            ],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:500000'],
            'author_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ], [
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung.',
            'category_id.exists' => 'Pilih kategori artikel yang valid.',
        ])->validate();
    }

    /** Buat slug unik dari teks (untuk artikel baru). */
    protected function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'article';
        $candidate = $base;
        $i = 2;

        while (Article::query()->where('slug', $candidate)->orWhere('draft_data->slug', $candidate)->exists()) {
            $candidate = $base.'-'.$i++;
        }

        return $candidate;
    }

    protected function categoryExists(int $id): bool
    {
        return Category::query()->whereKey($id)->where('type', Article::categoryType()->value)->exists();
    }
}
