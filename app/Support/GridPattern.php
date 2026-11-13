<?php

namespace App\Support;

/**
 * Pola tata letak (SRS 3.2 dan 3.3). Listing proyek dua kolom sama besar (mockup); galeri tetap asimetris. Pola ditentukan sistem dari POSISI item,
 * bukan acak dan bukan diatur admin satu per satu.
 *
 * Nama class ditulis utuh (bukan dirakit) agar Tailwind dapat menemukannya saat build.
 * Setiap pola: [kolom (md ke atas), rasio gambar, offset atas, jumlah kolom (untuk atribut sizes)].
 */
final class GridPattern
{
    /** Listing proyek. Panjang 8 = jumlah item per halaman, jadi pola utuh pada tiap batch. */
    private const PROJECTS = [
        ['md:col-span-6', 'aspect-[3/2]', '', 6],
        ['md:col-span-6', 'aspect-[3/2]', '', 6],
        ['md:col-span-6', 'aspect-[3/2]', '', 6],
        ['md:col-span-6', 'aspect-[3/2]', '', 6],
        ['md:col-span-6', 'aspect-[3/2]', '', 6],
        ['md:col-span-6', 'aspect-[3/2]', '', 6],
        ['md:col-span-6', 'aspect-[3/2]', '', 6],
        ['md:col-span-6', 'aspect-[3/2]', '', 6],
    ];

    /** Galeri foto. Foto mempertahankan rasio aslinya; yang diatur hanya lebar dan posisinya. */
    private const GALLERY = [
        ['md:col-span-8', '', '', 8],
        ['md:col-span-4', '', 'md:mt-32', 4],
        ['md:col-span-5', '', '', 5],
        ['md:col-span-7', '', 'md:mt-16', 7],
        ['md:col-span-12', '', '', 12],
    ];

    public const PROJECTS_PER_PAGE = 8;

    /** @return array{col:string, ratio:string, offset:string, sizes:string} */
    public static function project(int $index): array
    {
        return self::resolve(self::PROJECTS, $index);
    }

    /** @return array{col:string, ratio:string, offset:string, sizes:string} */
    public static function gallery(int $index): array
    {
        return self::resolve(self::GALLERY, $index);
    }

    /** @param list<array{0:string,1:string,2:string,3:int}> $pattern */
    private static function resolve(array $pattern, int $index): array
    {
        [$col, $ratio, $offset, $span] = $pattern[$index % count($pattern)];

        return [
            'col' => $col,
            'ratio' => $ratio,
            'offset' => $offset,
            // Lebar tampil sebenarnya ≈ span/12 dari viewport pada layar md ke atas.
            'sizes' => '(min-width: 768px) '.round($span / 12 * 100).'vw, 100vw',
        ];
    }
}
