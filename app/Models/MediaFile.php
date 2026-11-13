<?php

namespace App\Models;

use App\Enums\MediaStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * File gambar fisik. Satu baris per isi unik (SHA-256), dipakai bersama oleh banyak konten
 * lewat MediaUsage. Lihat config/media.php untuk parameter pemrosesan.
 */
class MediaFile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => MediaStatus::class,
            'variants' => 'array',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'committed_at' => 'datetime',
            'orphaned_at' => 'datetime',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class);
    }

    public function isReady(): bool
    {
        return $this->status === MediaStatus::Ready;
    }

    /** Folder bersama untuk file ini, disebar per 2 karakter pertama hash agar folder tidak membengkak. */
    public function directory(): string
    {
        return substr($this->sha256, 0, 2).'/'.$this->sha256;
    }

    public function variantPath(int $width): string
    {
        return $this->directory().'/w'.$width.'.webp';
    }

    public function originalPath(): string
    {
        return $this->directory();
    }

    /**
     * Lebar varian terkecil yang masih >= $width. Bila tidak ada, varian terbesar.
     * Null bila belum ada varian (gambar belum siap).
     */
    public function bestVariant(int $width): ?int
    {
        $widths = collect($this->variants ?? [])->map(fn ($w) => (int) $w)->sort()->values();

        if ($widths->isEmpty()) {
            return null;
        }

        return $widths->first(fn (int $w): bool => $w >= $width) ?? $widths->last();
    }

    public function url(int $width = 1280): ?string
    {
        $best = $this->bestVariant($width);

        return $best === null
            ? null
            : Storage::disk(config('media.disk'))->url($this->variantPath($best));
    }

    /** Nilai atribut `srcset` untuk seluruh varian yang tersedia. */
    public function srcset(): string
    {
        $disk = Storage::disk(config('media.disk'));

        return collect($this->variants ?? [])
            ->map(fn ($w) => (int) $w)
            ->sort()
            ->map(fn (int $w): string => $disk->url($this->variantPath($w)).' '.$w.'w')
            ->implode(', ');
    }

    /** Hapus seluruh file fisik (varian dan asli). Tidak menyentuh baris database. */
    public function deletePhysicalFiles(): void
    {
        Storage::disk(config('media.disk'))->deleteDirectory($this->directory());
        Storage::disk(config('media.original_disk'))->delete($this->originalPath());
    }

    /** Dipanggil saat penggunaan pertama: gambar resmi dipakai konten (SRS 12.3 - commit). */
    public function markCommitted(): void
    {
        $this->forceFill([
            'committed_at' => $this->committed_at ?? now(),
            'orphaned_at' => null, // jika sedang di antrean hapus, dibatalkan (FR-IM-072)
        ])->save();
    }

    /** Dipanggil saat penggunaan terakhir dilepas: masuk antrean penghapusan. */
    public function markOrphanedIfUnused(): void
    {
        if ($this->usages()->doesntExist() && $this->orphaned_at === null) {
            $this->forceFill(['orphaned_at' => now()])->save();
        }
    }
}
