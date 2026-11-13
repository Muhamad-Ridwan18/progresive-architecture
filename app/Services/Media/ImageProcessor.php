<?php

namespace App\Services\Media;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Mengolah satu gambar menjadi beberapa varian WebP (SRS 12.4 dan 12.5).
 *
 * Kelas ini murni: tidak menyentuh database atau storage. Hasil varian diserahkan
 * lewat callback sehingga pemanggil yang memutuskan di mana menyimpannya.
 *
 * Driver saat ini GD. Untuk beralih ke Imagick cukup mengganti driver di konstruktor.
 * Catatan GD: profil warna tidak dikonversi ke sRGB (SRS 12.5); itu butuh Imagick/libvips.
 */
class ImageProcessor
{
    private ImageManager $manager;

    public function __construct(?ImageManager $manager = null)
    {
        $this->manager = $manager ?? new ImageManager(new Driver);
    }

    /**
     * Periksa gambar tanpa mendekodenya (hanya membaca header), agar gambar "bom dekompresi"
     * ditolak sebelum memakan memori. Jenis file ditentukan dari isi, bukan nama file.
     *
     * @return array{width:int, height:int, mime:string}
     */
    public function inspect(string $path): array
    {
        $info = is_file($path) ? @getimagesize($path) : false;

        if ($info === false) {
            throw new InvalidMediaException('File bukan gambar yang valid.');
        }

        [$width, $height] = $info;
        $mime = (string) ($info['mime'] ?? '');

        if (! in_array($mime, config('media.allowed_mimes'), true)) {
            throw new InvalidMediaException('Format tidak didukung. Gunakan JPG, PNG, atau WebP.');
        }

        if ($width < 1 || $height < 1) {
            throw new InvalidMediaException('Dimensi gambar tidak valid.');
        }

        $maxDimension = (int) config('media.max_dimension');
        if ($width > $maxDimension || $height > $maxDimension) {
            throw new InvalidMediaException("Dimensi gambar melebihi batas {$maxDimension} px.");
        }

        $maxPixels = (int) config('media.max_pixels');
        if ($width * $height > $maxPixels) {
            throw new InvalidMediaException('Resolusi gambar melebihi batas yang diizinkan.');
        }

        return ['width' => $width, 'height' => $height, 'mime' => $mime];
    }

    /**
     * Lebar varian yang akan dibuat untuk sumber selebar $sourceWidth: seluruh lebar standar yang
     * lebih kecil dari sumber, ditambah lebar sumber itu sendiri bila tidak melebihi lebar standar
     * terbesar. Tidak pernah memperbesar (tanpa upscaling). Urut dari terbesar.
     *
     * @return list<int>
     */
    public function targetWidths(int $sourceWidth): array
    {
        $standard = collect(config('media.variant_widths'))->map(fn ($w) => (int) $w)->sort()->values();

        $widths = $standard->filter(fn (int $w): bool => $w < $sourceWidth);

        if ($sourceWidth <= $standard->last()) {
            $widths->push($sourceWidth);
        }

        return $widths->unique()->sortDesc()->values()->all();
    }

    /**
     * Buat semua varian. Dikerjakan dari yang terbesar ke terkecil pada satu gambar yang diperkecil
     * bertahap, sehingga memori puncak ≈ satu gambar penuh (penting untuk server 2 GB).
     *
     * @param  callable(int $width, string $webpBytes): void  $onVariant
     * @return array{width:int, height:int, widths:list<int>} dimensi setelah koreksi orientasi EXIF
     *
     * @throws InvalidMediaException
     */
    public function generate(string $sourcePath, callable $onVariant): array
    {
        $this->assertEnoughMemory($sourcePath);

        try {
            // orient() menerapkan rotasi EXIF; metadata EXIF tidak ikut terbawa saat di-encode ulang.
            $image = $this->manager->decodePath($sourcePath)->orient();
        } catch (\Throwable $e) {
            throw new InvalidMediaException('Gambar rusak atau tidak dapat dibaca.', 0, $e);
        }

        $width = $image->width();
        $height = $image->height();
        $quality = (int) config('media.webp_quality');
        $sharpen = (int) config('media.sharpen');
        $widths = $this->targetWidths($width);

        foreach ($widths as $target) {
            $shrunk = $target < $image->width();

            if ($shrunk) {
                $image->scaleDown(width: $target);
            }

            // Sharpening ringan hanya pada varian yang diperkecil, dipasang pada salinan
            // agar tidak menumpuk di langkah pengecilan berikutnya.
            $output = ($shrunk && $sharpen > 0) ? (clone $image)->sharpen($sharpen) : $image;

            $onVariant($target, $output->encode(new WebpEncoder(quality: $quality))->toString());

            unset($output);
        }

        return ['width' => $width, 'height' => $height, 'widths' => $widths];
    }

    /**
     * Tolak lebih awal bila perkiraan kebutuhan memori melebihi batas PHP, daripada
     * membiarkan proses mati dengan fatal error. ~8 byte/piksel: hasil dekode + satu salinan kerja.
     */
    private function assertEnoughMemory(string $path): void
    {
        $limit = $this->memoryLimitBytes();

        if ($limit === null) {
            return;
        }

        $info = @getimagesize($path);
        $needed = $info ? $info[0] * $info[1] * 8 : 0;
        $available = $limit - memory_get_usage(true);

        if ($needed > $available) {
            throw new InvalidMediaException(
                'Gambar terlalu besar untuk diproses dengan memori server saat ini. '
                .'Perkecil resolusinya atau naikkan batas memori worker.'
            );
        }
    }

    /**
     * Naikkan memory_limit PHP ke $target bila batas saat ini lebih kecil. Tidak pernah menurunkan.
     * Batas CLI bawaan (128M) terlalu kecil untuk foto ±24 MP; hasil ukur: ±200 MB untuk 24 MP
     * dan ±315 MB untuk 48 MP.
     */
    public function ensureMemoryLimit(string $target): void
    {
        $current = $this->memoryLimitBytes();
        $wanted = self::parseBytes($target);

        if ($current !== null && $wanted !== null && $current < $wanted) {
            ini_set('memory_limit', $target);
        }
    }

    private function memoryLimitBytes(): ?int
    {
        return self::parseBytes((string) ini_get('memory_limit'));
    }

    /** "256M" -> byte. Null untuk tak terbatas (-1) atau kosong. */
    private static function parseBytes(string $raw): ?int
    {
        $raw = trim($raw);

        if ($raw === '' || $raw === '-1') {
            return null;
        }

        $value = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g' => $value * 1024 ** 3,
            'm' => $value * 1024 ** 2,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
