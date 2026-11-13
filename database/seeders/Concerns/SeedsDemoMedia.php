<?php

namespace Database\Seeders\Concerns;

use App\Actions\Media\UploadMedia;
use App\Jobs\ProcessMediaImage;
use App\Models\MediaFile;
use Illuminate\Support\Carbon;

/**
 * Bantuan untuk seeder: mengimpor foto dari folder `foto/` lewat pipeline gambar sungguhan (UploadMedia lalu
 * ProcessMediaImage, diproses langsung tanpa worker), dan memundurkan jam agar tanggal terbit tersebar
 * seperti website yang sudah lama berjalan.
 *
 * Foto proyek asli diletakkan di `foto/progressive/{nama folder}/` (satu folder per proyek, mis. `AD HOUSE`).
 * Foto besar (belasan MB) diperkecil dulu ke sisi terpanjang 2560 px karena batas unggah situs 50 MB.
 */
trait SeedsDemoMedia
{
    /** Folder foto proyek asli. */
    protected function photoRoot(): string
    {
        return base_path('foto/progressive');
    }

    /** Nama folder di Drive yang tidak mengikuti nama proyek: slug proyek => kata kunci nama folder. */
    private const FOLDER_ALIASES = ['fr-house' => ['frweb']];

    /**
     * Foto sebuah proyek dari `foto/progressive`, terurut: eksterior lebih dulu, lalu nama berurutan alami.
     * Folder dicocokkan dari slug proyek (`ad-house` cocok dengan `AD HOUSE`, `FURNITURE BV HOUSE` cocok dengan `bv-house`).
     *
     * @return list<MediaFile>
     */
    protected function projectPhotos(string $slug): array
    {
        $root = $this->photoRoot();

        if (! is_dir($root)) {
            return [];
        }

        $keys = [str_replace('-', '', $slug), ...(self::FOLDER_ALIASES[$slug] ?? [])];
        $folder = null;

        foreach (scandir($root) ?: [] as $entry) {
            $normalized = preg_replace('/[^a-z0-9]/', '', strtolower($entry));

            if ($entry !== '.' && $entry !== '..' && is_dir("{$root}/{$entry}")) {
                foreach ($keys as $key) {
                    if ($key !== '' && str_contains($normalized, $key)) {
                        $folder = "{$root}/{$entry}";
                        break 2;
                    }
                }
            }
        }

        if ($folder === null) {
            return [];
        }

        $files = collect(glob($folder.'/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: []);

        // Urutan alami: "INTERIOR 2" sebelum "INTERIOR 11".
        $files = $files->sort(function (string $a, string $b): int {
            $ea = stripos(basename($a), 'EKSTERIOR') !== false ? 0 : 1;
            $eb = stripos(basename($b), 'EKSTERIOR') !== false ? 0 : 1;

            return $ea <=> $eb ?: strnatcasecmp(basename($a), basename($b));
        })->values();

        return $files->map(fn (string $path): ?MediaFile => $this->importFile($path, basename($path)))->filter()->values()->all();
    }

    /** Impor satu berkas; yang terlalu besar diperkecil ke salinan sementara lebih dulu. */
    protected function importFile(string $path, string $name): ?MediaFile
    {
        $upload = $this->downscaled($path);

        try {
            $file = app(UploadMedia::class)->handle($upload, $name);
            ProcessMediaImage::dispatchSync($file->id);

            return $file->fresh();
        } catch (\Throwable $e) {
            $this->command?->warn("{$name} dilewati: {$e->getMessage()}");

            return null;
        } finally {
            if ($upload !== $path) {
                @unlink($upload);
            }
        }
    }

    /** Salinan sementara bersisi terpanjang 2560 px bila berkas > 15 MB atau lebih lebar dari 3000 px; selain itu aslinya. */
    private function downscaled(string $path): string
    {
        $info = @getimagesize($path);

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return $path;
        }

        [$width, $height] = $info;

        if (filesize($path) <= 15 * 1024 * 1024 && max($width, $height) <= 3000) {
            return $path;
        }

        ini_set('memory_limit', '3G');

        $source = $info[2] === IMAGETYPE_JPEG ? imagecreatefromjpeg($path) : imagecreatefrompng($path);
        $scale = 2560 / max($width, $height);
        $target = imagescale($source, (int) round($width * $scale), (int) round($height * $scale), IMG_BICUBIC);

        $temp = tempnam(sys_get_temp_dir(), 'seed_photo_');
        imagejpeg($target, $temp, 85);
        imagedestroy($source);
        imagedestroy($target);

        return $temp;
    }

    /** Jalankan blok pada waktu yang dimundurkan, lalu kembalikan jam. Aman terhadap galat. */
    protected function atDaysAgo(int|float $days, callable $callback): mixed
    {
        Carbon::setTestNow(now()->subDays($days));

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
