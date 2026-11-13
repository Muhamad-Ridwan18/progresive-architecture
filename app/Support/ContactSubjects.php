<?php

namespace App\Support;

/**
 * Pilihan "Subject" pada formulir kontak (SRS 3.8). Daftar ini USULAN; belum diputuskan klien.
 */
final class ContactSubjects
{
    /** @return array<string, string> kunci => label */
    public static function all(): array
    {
        return [
            'general' => 'General inquiry',
            'project' => 'Project inquiry',
            'partnership' => 'Partnership',
            'careers' => 'Careers',
            'other' => 'Other',
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function label(string $key): string
    {
        return self::all()[$key] ?? self::all()['other'];
    }
}
