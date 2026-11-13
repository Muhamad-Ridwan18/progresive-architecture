<?php

namespace App\Actions\Media;

use App\Enums\MediaStatus;
use App\Jobs\ProcessMediaImage;
use App\Models\MediaFile;
use App\Services\Media\ImageProcessor;
use App\Services\Media\InvalidMediaException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Storage;

/**
 * Terima satu unggahan: validasi, deduplikasi, simpan file asli, lalu antrekan pemrosesan.
 * Gambar yang baru diunggah belum dipakai konten mana pun, jadi langsung masuk antrean
 * pembersihan sementara (orphaned_at) sampai dipasang lewat AttachMedia (SRS 12.3).
 */
class UploadMedia
{
    public function __construct(private ImageProcessor $processor) {}

    /**
     * @throws InvalidMediaException bila file ditolak. Pesannya aman ditampilkan ke admin.
     */
    public function handle(string $path, string $originalName, ?int $userId = null): MediaFile
    {
        $this->assertSize($path);
        $info = $this->processor->inspect($path);
        $sha = hash_file('sha256', $path);

        if ($existing = MediaFile::query()->where('sha256', $sha)->first()) {
            return $this->reuse($existing);
        }

        $file = new MediaFile([
            'sha256' => $sha,
            'original_name' => mb_substr($originalName, 0, 255),
            'mime' => $info['mime'],
            'size_bytes' => filesize($path),
            'width' => $info['width'],
            'height' => $info['height'],
            'status' => MediaStatus::Pending,
            'uploaded_by' => $userId,
            'orphaned_at' => now(),
        ]);

        try {
            $file->save();
        } catch (UniqueConstraintViolationException) {
            // Dua unggahan identik tiba bersamaan: pakai baris yang menang.
            return $this->reuse(MediaFile::query()->where('sha256', $sha)->firstOrFail());
        }

        $stream = fopen($path, 'rb');
        Storage::disk(config('media.original_disk'))->put($file->originalPath(), $stream);
        is_resource($stream) && fclose($stream);

        ProcessMediaImage::dispatch($file->id)->onQueue(config('media.queue'));

        return $file;
    }

    /** Gambar identik sudah ada (SRS 12.7): pakai file yang sama, tidak menyimpan fisik ganda. */
    private function reuse(MediaFile $file): MediaFile
    {
        // Perpanjang masa tunggu agar tidak terhapus sebelum sempat dipasang ke konten.
        if ($file->orphaned_at !== null) {
            $file->forceFill(['orphaned_at' => now()])->save();
        }

        // Percobaan ulang yang gagal sebelumnya: coba proses lagi.
        if ($file->status === MediaStatus::Failed) {
            $file->forceFill(['status' => MediaStatus::Pending, 'error' => null])->save();
            ProcessMediaImage::dispatch($file->id)->onQueue(config('media.queue'));
        }

        return $file;
    }

    private function assertSize(string $path): void
    {
        $max = (int) config('media.max_size_kb');

        if (! is_file($path) || filesize($path) > $max * 1024) {
            throw new InvalidMediaException('Ukuran gambar melebihi batas '.round($max / 1024).' MB.');
        }
    }
}
