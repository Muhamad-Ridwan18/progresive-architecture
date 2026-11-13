<?php

namespace App\Jobs;

use App\Enums\MediaStatus;
use App\Models\MediaFile;
use App\Services\Media\ImageProcessor;
use App\Services\Media\InvalidMediaException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Membuat varian WebP untuk satu gambar. Kegagalan satu gambar hanya menandai gambar itu
 * `failed` (bisa diulang), tidak membatalkan antrean atau gambar lain (SRS 12.2 - Isolated Failure).
 */
class ProcessMediaImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $mediaFileId) {}

    public function handle(ImageProcessor $processor): void
    {
        $file = MediaFile::query()->find($this->mediaFileId);

        if (! $file || $file->isReady()) {
            return;
        }

        $file->forceFill(['status' => MediaStatus::Processing, 'error' => null])->save();

        // Worker CLI memakai batas memori bawaan yang kecil; naikkan hanya untuk pemrosesan gambar.
        $processor->ensureMemoryLimit((string) config('media.processing_memory_limit'));

        $disk = Storage::disk(config('media.disk'));
        $tmp = null;

        try {
            // Bersihkan sisa percobaan sebelumnya agar tidak ada varian setengah jadi.
            $disk->deleteDirectory($file->directory());

            $tmp = $this->copyOriginalToTemp($file);

            $result = $processor->generate($tmp, function (int $width, string $bytes) use ($disk, $file): void {
                $disk->put($file->variantPath($width), $bytes);
            });

            $file->forceFill([
                'width' => $result['width'],
                'height' => $result['height'],
                'variants' => $result['widths'],
                'status' => MediaStatus::Ready,
                'error' => null,
            ])->save();
        } catch (InvalidMediaException $e) {
            $this->markFailed($file, $e->getMessage());
        } catch (Throwable $e) {
            report($e);
            $this->markFailed($file, 'Pemrosesan gagal. Coba unggah ulang.');
        } finally {
            if ($tmp !== null && is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private function markFailed(MediaFile $file, string $message): void
    {
        Storage::disk(config('media.disk'))->deleteDirectory($file->directory());

        $file->forceFill([
            'status' => MediaStatus::Failed,
            'error' => $message,
            'variants' => null,
        ])->save();
    }

    /** Salin file asli ke file sementara lokal, agar bekerja untuk disk apa pun (termasuk object storage). */
    private function copyOriginalToTemp(MediaFile $file): string
    {
        $source = Storage::disk(config('media.original_disk'))->readStream($file->originalPath());
        $tmp = tempnam(sys_get_temp_dir(), 'media_');
        $target = fopen($tmp, 'wb');

        stream_copy_to_stream($source, $target);

        fclose($target);
        is_resource($source) && fclose($source);

        return $tmp;
    }
}
