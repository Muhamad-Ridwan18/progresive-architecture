<?php

namespace App\Services;

use App\Models\FormField;
use App\Models\JobCategory;
use App\Support\ApplicationForm;

/**
 * Menyusun formulir lamaran sebuah lowongan dari daftar centang isian pustaka (SRS 6.2).
 *
 * Hasilnya adalah daftar field yang disimpan sebagai salinan di lowongan (`job_vacancies.form_fields`). Isian
 * inti (nama, email, telepon) selalu ikut dan selalu wajib. Isian khusus yang pernah ada di lowongan tetapi tidak
 * ada di pustaka (mis. dari data lama) dipertahankan, supaya menyimpan lowongan tidak menghilangkan isian.
 */
class VacancyFormComposer
{
    /** Centang bawaan untuk kategori baru: CV dan surat lamaran. Isian inti selalu ada dan tidak ikut daftar centang. */
    public const DEFAULT_KEYS = ['cv', 'cover_letter'];

    /**
     * @param  list<string>  $selectedKeys  isian pustaka yang dicentang
     * @param  list<string>  $requiredKeys  di antaranya, yang wajib diisi
     * @param  list<array<string, mixed>>|null  $existing  isian lowongan sebelumnya (untuk isian khusus di luar pustaka)
     * @return list<array<string, mixed>>
     */
    public function compose(array $selectedKeys, array $requiredKeys, ?array $existing = null): array
    {
        $library = FormField::query()->inOrder()->get();
        $libraryKeys = $library->pluck('key')->all();
        $selected = array_unique([...FormField::CORE_KEYS, ...$selectedKeys]);

        $fields = $library
            ->filter(fn (FormField $field): bool => in_array($field->key, $selected, true))
            ->map(fn (FormField $field): array => $field->toApplicationField(in_array($field->key, $requiredKeys, true) || $field->is_core))
            ->values()
            ->all();

        $custom = collect($existing ?? [])
            ->reject(fn (array $field): bool => in_array($field['key'] ?? null, $libraryKeys, true))
            ->all();

        return ApplicationForm::normalize([...$fields, ...$custom]);
    }

    /**
     * Pilihan centang dari formulir lowongan yang sudah ada (tanpa isian inti, yang tidak ada di daftar centang).
     *
     * @param  list<array<string, mixed>>|null  $fields
     * @return array{keys: list<string>, required: list<string>}
     */
    public function selectionFrom(?array $fields): array
    {
        $libraryKeys = FormField::query()->pluck('key')->all();
        $inputs = collect($fields ?? [])->filter(fn (array $f): bool => in_array($f['key'] ?? null, $libraryKeys, true));

        $optional = $inputs->reject(fn (array $f): bool => in_array($f['key'], FormField::CORE_KEYS, true));

        return [
            'keys' => $optional->pluck('key')->values()->all(),
            'required' => $optional->filter(fn (array $f): bool => (bool) ($f['required'] ?? false))->pluck('key')->values()->all(),
        ];
    }

    /**
     * Pilihan bawaan sebuah kategori (untuk lowongan baru, SRS FR-RC-011).
     *
     * @return array{keys: list<string>, required: list<string>}
     */
    public function selectionFromCategory(?JobCategory $category): array
    {
        $keys = $category?->default_field_keys ?: self::DEFAULT_KEYS;

        $withoutCore = fn (array $list): array => array_values(array_diff($list, FormField::CORE_KEYS));

        return [
            'keys' => $withoutCore($keys),
            'required' => $withoutCore($category?->default_required_keys ?? ['cv']),
        ];
    }
}
