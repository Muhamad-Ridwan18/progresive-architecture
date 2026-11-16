<?php

namespace App\Actions\Projects;

use App\Models\Category;
use App\Models\Project;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Aturan validasi field proyek yang dipakai bersama oleh aksi buat, simpan draft, dan publish. */
trait ValidatesProjectFields
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  bool  $checkDraftSlugs  juga bandingkan dengan slug draft proyek lain (saat menyimpan draft)
     *
     * @throws ValidationException
     */
    protected function validateFields(array $fields, ?Project $project, bool $checkDraftSlugs): void
    {
        $ignoreId = $project?->getKey();

        Validator::make($fields, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('projects', 'slug')->ignore($ignoreId),
                function (string $attribute, mixed $value, \Closure $fail) use ($ignoreId, $checkDraftSlugs): void {
                    if ($checkDraftSlugs && Project::query()
                        ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                        ->where('draft_data->slug', $value)
                        ->exists()) {
                        $fail('Slug sudah dipakai proyek lain.');
                    }
                },
            ],
            'category_id' => [
                'required', 'integer',
                Rule::exists('categories', 'id')->where('type', Project::categoryType()->value),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.((int) date('Y') + 5)],
            'description' => ['nullable', 'string', 'max:20000'],
            'client' => ['nullable', 'string', 'max:255'],
            'typology' => ['nullable', 'string', 'max:80'],
            'land_area' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'building_area' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'storeys' => ['nullable', 'integer', 'min:1', 'max:200'],
            'stage' => ['nullable', 'string', 'max:40'],
            'credits' => ['nullable', 'array', 'max:20'],
            'credits.*.title' => ['nullable', 'string', 'max:80'],
            'credits.*.names' => ['nullable', 'string', 'max:3000'],
        ], [
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung.',
            'category_id.exists' => 'Pilih kategori proyek yang valid.',
        ])->validate();
    }

    /** Buat slug unik dari teks (untuk proyek baru). */
    protected function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'project';
        $candidate = $base;
        $i = 2;

        while (Project::query()->where('slug', $candidate)->orWhere('draft_data->slug', $candidate)->exists()) {
            $candidate = $base.'-'.$i++;
        }

        return $candidate;
    }

    protected function categoryExists(int $id): bool
    {
        return Category::query()->whereKey($id)->where('type', Project::categoryType()->value)->exists();
    }
}
