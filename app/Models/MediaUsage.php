<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Satu penggunaan gambar oleh satu konten. Alt text, caption, dan focal point
 * disimpan di sini, bukan di file, sehingga tiap konten boleh berbeda (SRS 12.6).
 */
class MediaUsage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'focal' => 'array',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(fn (self $usage) => $usage->file->markCommitted());

        // Penggunaan terakhir dilepas -> file masuk antrean hapus (retensi 7 hari).
        static::deleted(fn (self $usage) => $usage->file->markOrphanedIfUnused());
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'media_file_id');
    }

    public function usable(): MorphTo
    {
        return $this->morphTo();
    }
}
