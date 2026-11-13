<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Akses pengaturan website (SRS Bagian 8).
 * Dipakai frontend (navbar, footer, SEO) dan halaman Website Settings di dashboard.
 */
class Settings
{
    private const CACHE_KEY = 'website_settings';

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => Setting::query()->pluck('value', 'key')->all(),
        );
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    /**
     * Simpan banyak nilai sekaligus.
     *
     * @param  array<string, mixed>  $values
     * @return array{old: array<string, mixed>, new: array<string, mixed>} hanya nilai yang berubah
     */
    public static function setMany(array $values): array
    {
        $current = self::all();
        $old = [];
        $new = [];

        foreach ($values as $key => $value) {
            if (($current[$key] ?? null) === $value) {
                continue;
            }

            $old[$key] = $current[$key] ?? null;
            $new[$key] = $value;

            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);

        return ['old' => $old, 'new' => $new];
    }
}
