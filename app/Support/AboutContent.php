<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Struktur dan aturan isi halaman About Us (SRS 3.5). Enam bagian tetap:
 * Company Overview, Company History, Vision & Mission, Company Values, Company Statistics,
 * dan Our Team (opsional). Tidak ada page builder bebas.
 *
 * Bentuk baku (kanonik) dipakai untuk membandingkan draft dengan versi published, sehingga
 * "ada perubahan yang belum dipublikasikan?" dapat dijawab dengan akurat.
 */
final class AboutContent
{
    /** Batas jumlah item pada bagian yang berulang. */
    public const MAX_MILESTONES = 30;

    public const MAX_VALUES = 12;

    public const MAX_STATISTICS = 8;

    public const MAX_TEAM = 60;

    /** @return array<string, mixed> */
    public static function empty(): array
    {
        return [
            'overview' => ['heading' => null, 'body' => null],
            'history' => ['body' => null, 'milestones' => []],
            'vision_mission' => ['vision' => null, 'mission' => null],
            'values' => [],
            'statistics' => [],
            'team' => [],
        ];
    }

    /**
     * Ubah masukan apa pun menjadi bentuk baku: kunci tak dikenal dibuang, teks dipangkas,
     * string kosong menjadi null, item berulang yang seluruhnya kosong dibuang, daftar diindeks ulang.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function normalize(array $raw): array
    {
        return [
            'overview' => self::fields($raw['overview'] ?? [], ['heading', 'body']),
            'history' => [
                'body' => self::text($raw['history']['body'] ?? null),
                'milestones' => self::items($raw['history']['milestones'] ?? [], ['year', 'title', 'description']),
            ],
            'vision_mission' => self::fields($raw['vision_mission'] ?? [], ['vision', 'mission']),
            'values' => self::items($raw['values'] ?? [], ['title', 'description']),
            'statistics' => self::items($raw['statistics'] ?? [], ['value', 'label']),
            'team' => self::team($raw['team'] ?? []),
        ];
    }

    /**
     * Normalkan lalu validasi.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed> isi baku yang sudah valid
     *
     * @throws ValidationException
     */
    public static function validated(array $raw): array
    {
        $content = self::normalize($raw);

        Validator::make($content, [
            'overview.heading' => ['nullable', 'string', 'max:160'],
            'overview.body' => ['nullable', 'string', 'max:5000'],

            'history.body' => ['nullable', 'string', 'max:5000'],
            'history.milestones' => ['array', 'max:'.self::MAX_MILESTONES],
            'history.milestones.*.year' => ['nullable', 'string', 'max:12'],
            'history.milestones.*.title' => ['required', 'string', 'max:160'],
            'history.milestones.*.description' => ['nullable', 'string', 'max:1000'],

            'vision_mission.vision' => ['nullable', 'string', 'max:2000'],
            'vision_mission.mission' => ['nullable', 'string', 'max:2000'],

            'values' => ['array', 'max:'.self::MAX_VALUES],
            'values.*.title' => ['required', 'string', 'max:120'],
            'values.*.description' => ['nullable', 'string', 'max:600'],

            'statistics' => ['array', 'max:'.self::MAX_STATISTICS],
            'statistics.*.value' => ['required', 'string', 'max:20'],
            'statistics.*.label' => ['required', 'string', 'max:80'],

            'team' => ['array', 'max:'.self::MAX_TEAM],
            'team.*.id' => ['required', 'string', 'regex:/^[a-z0-9]{6,20}$/'],
            'team.*.name' => ['required', 'string', 'max:120'],
            'team.*.position' => ['nullable', 'string', 'max:120'],
            'team.*.bio' => ['nullable', 'string', 'max:4000'],
        ], [
            'history.milestones.*.title.required' => 'Setiap tonggak sejarah perlu judul.',
            'values.*.title.required' => 'Setiap nilai perusahaan perlu judul.',
            'statistics.*.value.required' => 'Setiap statistik perlu angka.',
            'statistics.*.label.required' => 'Setiap statistik perlu keterangan.',
            'team.*.name.required' => 'Setiap anggota tim perlu nama.',
        ])->validate();

        return $content;
    }

    /** Apakah ada isi yang layak tampil? Halaman kosong tidak boleh dipublikasikan. */
    public static function hasContent(array $content): bool
    {
        return filled($content['overview']['heading']) || filled($content['overview']['body']);
    }

    /**
     * Anggota tim: setiap anggota punya `id` stabil yang mengaitkannya dengan fotonya (media_usages,
     * peran `team:{id}`). Anggota tanpa isi dibuang; anggota lama tanpa id mendapat id deterministik
     * (dari posisi dan nama) agar bentuk baku tetap sama setiap kali dinormalkan sampai disimpan.
     *
     * @return list<array<string, ?string>>
     */
    private static function team(mixed $list): array
    {
        if (! is_array($list)) {
            return [];
        }

        return collect($list)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item, int|string $index): array {
                $fields = self::fields($item, ['name', 'position', 'bio']);
                $id = self::text($item['id'] ?? null);
                $id = $id !== null && preg_match('/^[a-z0-9]{6,20}$/', $id) ? $id : 'l'.substr(md5($index.'|'.($fields['name'] ?? '')), 0, 11);

                return ['id' => $id, ...$fields];
            })
            ->reject(fn (array $item) => collect($item)->except('id')->every(fn ($v) => $v === null))
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------ internal

    private static function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, ?string>
     */
    private static function fields(mixed $data, array $keys): array
    {
        $data = is_array($data) ? $data : [];

        return collect($keys)->mapWithKeys(fn (string $k) => [$k => self::text($data[$k] ?? null)])->all();
    }

    /**
     * @param  list<string>  $keys
     * @return list<array<string, ?string>>
     */
    private static function items(mixed $list, array $keys): array
    {
        if (! is_array($list)) {
            return [];
        }

        return collect($list)
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => self::fields($item, $keys))
            ->reject(fn (array $item) => collect($item)->every(fn ($v) => $v === null))
            ->values()
            ->all();
    }
}
