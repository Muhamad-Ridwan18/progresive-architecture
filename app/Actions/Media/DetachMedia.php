<?php

namespace App\Actions\Media;

use App\Models\MediaUsage;

/**
 * Lepas gambar dari sebuah konten. Hanya menghapus relasi penggunaan; file fisik tetap ada
 * dan baru masuk antrean hapus bila tidak ada penggunaan lain (SRS 12.9 dan 12.10).
 */
class DetachMedia
{
    public function handle(MediaUsage $usage): void
    {
        $usage->delete();
    }
}
