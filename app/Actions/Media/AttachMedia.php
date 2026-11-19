<?php

namespace App\Actions\Media;

use App\Enums\MediaStatus;
use App\Models\MediaFile;
use App\Models\MediaUsage;
use App\Services\Media\InvalidMediaException;
use Illuminate\Database\Eloquent\Model;

/**
 * Pasang gambar pada sebuah konten (commit). Membatalkan antrean hapus bila gambar
 * sedang di sana (SRS FR-IM-072). Alt text, caption, dan focal point milik penggunaan ini.
 */
class AttachMedia
{
    /**
     * @param  array{alt_text?:?string, caption?:?string, focal?:?array, sort_order?:int, is_draft?:bool}  $attributes
     */
    public function handle(MediaFile $file, Model $usable, string $role, array $attributes = []): MediaUsage
    {
        if ($file->status === MediaStatus::Failed) {
            throw new InvalidMediaException('Gambar gagal diproses dan tidak dapat dipakai. Unggah ulang atau coba lagi.');
        }

        return $usable->morphMany(MediaUsage::class, 'usable')->create([
            'media_file_id' => $file->getKey(),
            'role' => $role,
            // Pemasangan baru selalu masuk draft; versi published hanya diisi lewat salinan saat publish.
            'is_draft' => $attributes['is_draft'] ?? true,
            'sort_order' => $attributes['sort_order'] ?? 0,
            'alt_text' => $attributes['alt_text'] ?? null,
            'caption' => $attributes['caption'] ?? null,
            'note' => $attributes['note'] ?? null,
            'note_by' => $attributes['note_by'] ?? null,
            'join_previous' => (bool) ($attributes['join_previous'] ?? false),
            'focal' => $attributes['focal'] ?? null,
        ]);
    }
}
