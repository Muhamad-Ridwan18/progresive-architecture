<?php

/*
 * Pengaturan pipeline gambar (SRS Bagian 12).
 * Semua angka di sini adalah PARAMETER AWAL dan dapat disesuaikan setelah uji
 * dengan foto proyek asli serta kapasitas server (SRS 12.4, NFR-CAP-001).
 */

return [

    // Disk hasil olahan (publik) dan disk file asli (privat).
    'disk' => env('MEDIA_DISK', 'media'),
    'original_disk' => env('MEDIA_ORIGINAL_DISK', 'media-private'),

    // Lebar varian dalam piksel (SRS 12.4). Varian yang lebih lebar dari sumber tidak dibuat.
    'variant_widths' => [400, 800, 1280, 1920, 2560, 3840],

    // Kualitas WebP awal (SRS 12.4). Dievaluasi dengan foto asli sebelum produksi.
    'webp_quality' => (int) env('MEDIA_WEBP_QUALITY', 88),

    // Sharpening ringan (0 = mati). Hanya dipakai pada varian yang diperkecil.
    'sharpen' => (int) env('MEDIA_SHARPEN', 5),

    // Validasi unggahan (SRS 12.2, NFR-SEC-003).
    'max_size_kb' => 50 * 1024,
    'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    'max_files_per_batch' => 20,

    // Batas piksel total per gambar. Melindungi RAM server dari gambar "bom dekompresi".
    // 80 MP menampung foto kamera resolusi tinggi (mis. 10.400 x 6.933 = 72 MP, sekitar 600 MB memori saat diproses;
    // lihat processing_memory_limit). Turunkan lewat MEDIA_MAX_PIXELS bila memori server terbatas.
    'max_pixels' => (int) env('MEDIA_MAX_PIXELS', 80_000_000),
    'max_dimension' => (int) env('MEDIA_MAX_DIMENSION', 12_000),

    // Antrean pemrosesan gambar. Bawaan `default` agar worker standar (`php artisan queue:work`)
    // langsung memprosesnya. Bila ingin worker khusus gambar, set MEDIA_QUEUE=media lalu jalankan:
    //   php artisan queue:work --queue=media --tries=1 --timeout=0
    'queue' => env('MEDIA_QUEUE', 'default'),

    // Batas memori PHP yang dipakai job saat memproses gambar. Hasil ukur (GD): ±200 MB untuk
    // foto 24 MP dan ±315 MB untuk 48 MP (±470 MB untuk 72 MP). Diproses satu per satu, jadi cukup satu worker.
    'processing_memory_limit' => env('MEDIA_PROCESSING_MEMORY', '896M'),

    // Pembersihan (SRS 12.3 dan 12.9).
    // - Unggahan yang tidak pernah dipakai: dibuang setelah sekian jam (SRS 15 butir 4, usulan 24 jam).
    // - Gambar yang pernah dipakai lalu dilepas: dibuang setelah 7 hari (SRS FR-IM-072).
    'temp_retention_hours' => (int) env('MEDIA_TEMP_RETENTION_HOURS', 24),
    'removed_retention_days' => (int) env('MEDIA_REMOVED_RETENTION_DAYS', 7),
];
