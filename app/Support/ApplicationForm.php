<?php

namespace App\Support;

use App\Enums\FormFieldType;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Struktur formulir lamaran dinamis (SRS 6.2) berupa daftar field, disimpan sebagai JSON pada
 * kategori pekerjaan (template) dan lowongan (salinan). Kelas ini menjadi satu-satunya tempat yang
 * tahu cara menurunkan aturan validasi dan identitas pelamar dari daftar tersebut.
 *
 * Bentuk satu field:
 * array{key: string, type: string, label: string, description?: ?string, placeholder?: ?string,
 *       required?: bool, options?: ?string, columns?: ?string, accept?: list<string>, max_mb?: int|string}
 *
 * `options` (Dropdown, Radio, Checkbox) dan `columns` (Repeater) berupa teks satu nilai per baris.
 */
class ApplicationForm
{
    /** Jenis dokumen yang boleh dipilih admin untuk field File Upload. */
    public const FILE_TYPES = [
        'pdf' => 'PDF',
        'doc' => 'DOC',
        'docx' => 'DOCX',
        'jpg' => 'JPG',
        'png' => 'PNG',
    ];

    /** Batas ukuran dokumen (MB). Batas pastinya masih keputusan terbuka SRS 15 butir 8. */
    public const DEFAULT_MAX_MB = 5;

    public const MAX_MB_LIMIT = 10;

    /**
     * Template awal: nama, email, telepon, CV, dan surat lamaran.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaultFields(): array
    {
        return [
            self::field(FormFieldType::Text, 'Nama lengkap', required: true, key: 'full_name'),
            self::field(FormFieldType::Email, 'Email', required: true, key: 'email'),
            self::field(FormFieldType::Phone, 'Nomor telepon', required: true, key: 'phone'),
            self::field(FormFieldType::File, 'CV (PDF)', required: true, key: 'cv', extra: ['accept' => ['pdf'], 'max_mb' => 5]),
            self::field(FormFieldType::Textarea, 'Surat lamaran', required: false, key: 'cover_letter'),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function field(FormFieldType $type, string $label, bool $required = false, ?string $key = null, array $extra = []): array
    {
        return [
            'key' => $key ?? self::newKey(),
            'type' => $type->value,
            'label' => $label,
            'required' => $required,
            ...$extra,
        ];
    }

    public static function newKey(): string
    {
        return 'f_'.Str::lower(Str::random(8));
    }

    /**
     * Rapikan hasil editor admin: key unik dan stabil untuk setiap field, nilai bawaan terisi.
     * Key tidak berubah setelah dibuat agar jawaban lama tetap terbaca.
     *
     * @param  array<int|string, array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    public static function normalize(array $fields): array
    {
        $seen = [];
        $result = [];

        foreach (array_values($fields) as $field) {
            $type = self::typeOf($field['type'] ?? null) ?? FormFieldType::Text;
            $key = (string) ($field['key'] ?? '');

            if ($key === '' || isset($seen[$key])) {
                $key = self::newKey();
            }

            $seen[$key] = true;

            $result[] = [
                'key' => $key,
                'type' => $type->value,
                'label' => trim((string) ($field['label'] ?? '')) ?: $type->getLabel(),
                'description' => filled($field['description'] ?? null) ? (string) $field['description'] : null,
                'placeholder' => filled($field['placeholder'] ?? null) ? (string) $field['placeholder'] : null,
                'required' => $type->isInput() && (bool) ($field['required'] ?? false),
                'options' => $type->hasOptions() ? (string) ($field['options'] ?? '') : null,
                'columns' => $type === FormFieldType::Repeater ? (string) ($field['columns'] ?? '') : null,
                'accept' => $type === FormFieldType::File ? self::acceptedTypes($field) : null,
                'max_mb' => $type === FormFieldType::File ? self::maxMb($field) : null,
            ];
        }

        return $result;
    }

    /**
     * Merapikan data satu isian pustaka (form Filament) menurut jenisnya: opsi hanya untuk Dropdown/Radio/Checkbox,
     * kolom hanya untuk Repeater, jenis berkas dan batas ukuran hanya untuk File Upload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function tidyLibraryField(array $data): array
    {
        $normalized = self::normalize([[...$data, 'key' => 'tmp']])[0];

        foreach (['options', 'columns', 'accept', 'max_mb'] as $column) {
            $data[$column] = $normalized[$column];
        }

        return $data;
    }

    /** Tipe field dari nilai mentah; Filament dapat mengirim string maupun enum. */
    public static function typeOf(mixed $value): ?FormFieldType
    {
        return $value instanceof FormFieldType ? $value : FormFieldType::tryFrom((string) $value);
    }

    /**
     * Teks satu-per-baris menjadi daftar nilai bersih.
     *
     * @return list<string>
     */
    public static function lines(?string $text): array
    {
        return collect(preg_split('/\R/', (string) $text) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    public static function acceptedTypes(array $field): array
    {
        $types = array_values(array_intersect(array_keys(self::FILE_TYPES), (array) ($field['accept'] ?? [])));

        return $types === [] ? ['pdf'] : $types;
    }

    /** @param  array<string, mixed>  $field */
    public static function maxMb(array $field): int
    {
        $value = (int) ($field['max_mb'] ?? self::DEFAULT_MAX_MB);

        return max(1, min($value, self::MAX_MB_LIMIT));
    }

    /**
     * Field yang benar-benar diisi pelamar (tanpa Section).
     *
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    public static function inputs(array $fields): array
    {
        return array_values(array_filter(
            $fields,
            fn (array $field): bool => (FormFieldType::tryFrom($field['type'] ?? '') ?? FormFieldType::Text)->isInput(),
        ));
    }

    /**
     * Aturan validasi untuk seluruh field. Jawaban berada di `answers.{key}`, berkas di `files.{key}`.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return array{rules: array<string, mixed>, attributes: array<string, string>}
     */
    public static function validation(array $fields): array
    {
        $rules = [];
        $attributes = [];

        foreach (self::inputs($fields) as $field) {
            $type = FormFieldType::from($field['type']);
            $required = (bool) ($field['required'] ?? false);
            $presence = $required ? 'required' : 'nullable';
            $name = ($type === FormFieldType::File ? 'files.' : 'answers.').$field['key'];

            $attributes[$name] = (string) $field['label'];

            switch ($type) {
                case FormFieldType::Text:
                    $rules[$name] = [$presence, 'string', 'max:190'];
                    break;
                case FormFieldType::Email:
                    $rules[$name] = [$presence, 'email', 'max:190'];
                    break;
                case FormFieldType::Phone:
                    $rules[$name] = [$presence, 'string', 'regex:/^[0-9+\-\s()]{6,30}$/'];
                    break;
                case FormFieldType::Number:
                    $rules[$name] = [$presence, 'numeric'];
                    break;
                case FormFieldType::Textarea:
                    $rules[$name] = [$presence, 'string', 'max:5000'];
                    break;
                case FormFieldType::Date:
                    $rules[$name] = [$presence, 'date'];
                    break;
                case FormFieldType::Dropdown:
                case FormFieldType::Radio:
                    $rules[$name] = [$presence, Rule::in(self::lines($field['options'] ?? null))];
                    break;
                case FormFieldType::Checkbox:
                    if (self::lines($field['options'] ?? null) === []) {
                        // Satu kotak persetujuan.
                        $rules[$name] = [$required ? 'accepted' : 'nullable', 'boolean'];
                    } else {
                        $rules[$name] = [$presence, 'array'];
                        $rules[$name.'.*'] = [Rule::in(self::lines($field['options']))];
                    }
                    break;
                case FormFieldType::File:
                    $rules[$name] = [
                        $presence,
                        File::types(self::acceptedTypes($field))->max(self::maxMb($field) * 1024),
                    ];
                    break;
                case FormFieldType::Repeater:
                    $rules[$name] = [$presence, 'array', 'max:20'];

                    foreach (array_keys(self::lines($field['columns'] ?? null)) as $index) {
                        $rules[$name.'.*.'.$index] = ['nullable', 'string', 'max:500'];
                    }

                    break;
                case FormFieldType::Section:
                    break;
            }
        }

        return ['rules' => $rules, 'attributes' => $attributes];
    }

    /**
     * Nama, email, dan telepon pelamar untuk kolom tabel: field Text pertama, Email pertama, Phone pertama.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $answers
     * @return array{name: string, email: string, phone: ?string}
     */
    public static function identity(array $fields, array $answers): array
    {
        $first = function (FormFieldType $type) use ($fields, $answers): ?string {
            foreach ($fields as $field) {
                if (($field['type'] ?? null) === $type->value && filled($answers[$field['key']] ?? null)) {
                    return trim((string) $answers[$field['key']]);
                }
            }

            return null;
        };

        return [
            'name' => $first(FormFieldType::Text) ?? 'Pelamar',
            'email' => $first(FormFieldType::Email) ?? '',
            'phone' => $first(FormFieldType::Phone),
        ];
    }

    /**
     * Jawaban dibersihkan: hanya key yang ada di formulir, bentuk sesuai tipe, tanpa berkas.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function cleanAnswers(array $fields, array $input): array
    {
        $answers = [];

        foreach (self::inputs($fields) as $field) {
            $type = FormFieldType::from($field['type']);

            if ($type === FormFieldType::File) {
                continue;
            }

            $value = $input[$field['key']] ?? null;

            $answers[$field['key']] = match ($type) {
                FormFieldType::Checkbox => self::lines($field['options'] ?? null) === []
                    ? (bool) $value
                    : array_values((array) $value),
                FormFieldType::Repeater => self::cleanRepeaterRows(is_array($value) ? $value : []),
                default => is_string($value) ? trim($value) : $value,
            };
        }

        return $answers;
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @return list<list<string>>
     */
    private static function cleanRepeaterRows(array $rows): array
    {
        return collect($rows)
            ->map(fn ($row): array => collect((array) $row)->map(fn ($cell): string => trim((string) $cell))->values()->all())
            ->filter(fn (array $row): bool => collect($row)->contains(fn (string $cell): bool => $cell !== ''))
            ->values()
            ->all();
    }
}
