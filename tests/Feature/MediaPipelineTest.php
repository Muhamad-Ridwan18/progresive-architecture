<?php

namespace Tests\Feature;

use App\Actions\Media\AttachMedia;
use App\Actions\Media\DetachMedia;
use App\Actions\Media\PurgeOrphanedMedia;
use App\Actions\Media\UploadMedia;
use App\Enums\CategoryType;
use App\Enums\MediaStatus;
use App\Models\Category;
use App\Models\MediaFile;
use App\Services\Media\ImageProcessor;
use App\Services\Media\InvalidMediaException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MediaPipelineTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        Storage::fake('media-private');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /** Buat gambar uji. $seed membuat isinya (dan hash-nya) berbeda. */
    private function image(int $w = 3000, int $h = 2000, int $seed = 1, string $type = 'jpeg'): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, ($seed * 37) % 255, ($seed * 91) % 255, ($seed * 53) % 255));
        imagefilledellipse($im, intdiv($w, 2), intdiv($h, 2), intdiv($w, 2), intdiv($h, 2), imagecolorallocate($im, 240, 240, 240));

        $path = tempnam(sys_get_temp_dir(), 'img_');
        $this->tempFiles[] = $path;

        match ($type) {
            'png' => imagepng($im, $path),
            'gif' => imagegif($im, $path),
            default => imagejpeg($im, $path, 85),
        };

        return $path;
    }

    private function upload(string $path, string $name = 'photo.jpg'): MediaFile
    {
        return app(UploadMedia::class)->handle($path, $name, null);
    }

    private function usable(): Category
    {
        return Category::create(['type' => CategoryType::Project, 'name' => 'Holder']);
    }

    // ---------------------------------------------------------------- varian

    public function test_target_widths_never_upscale(): void
    {
        $p = new ImageProcessor;

        $this->assertSame([3000, 2560, 1920, 1280, 800, 400], $p->targetWidths(3000));
        $this->assertSame([300], $p->targetWidths(300));
        $this->assertSame([3840, 2560, 1920, 1280, 800, 400], $p->targetWidths(5000));
        $this->assertSame([3840, 2560, 1920, 1280, 800, 400], $p->targetWidths(3840));
        $this->assertSame([800, 400], $p->targetWidths(800));
    }

    public function test_generates_webp_variants_with_preserved_aspect_ratio(): void
    {
        $widths = [];
        $result = (new ImageProcessor)->generate($this->image(3000, 2000), function (int $w, string $bytes) use (&$widths): void {
            $info = getimagesizefromstring($bytes);

            $this->assertSame('image/webp', $info['mime']);
            $this->assertSame($w, $info[0]);
            // 3:2 dipertahankan (toleransi pembulatan 1 px).
            $this->assertEqualsWithDelta($w * 2 / 3, $info[1], 1);

            $widths[] = $w;
        });

        $this->assertSame([3000, 2560, 1920, 1280, 800, 400], $widths);
        $this->assertSame(3000, $result['width']);
        $this->assertSame(2000, $result['height']);
    }

    // ---------------------------------------------------------------- upload

    public function test_upload_processes_image_and_stores_variants_and_original(): void
    {
        $file = $this->upload($this->image(3000, 2000), 'site.jpg')->fresh();

        $this->assertSame(MediaStatus::Ready, $file->status);
        $this->assertSame([3000, 2560, 1920, 1280, 800, 400], $file->variants);
        $this->assertNotNull($file->orphaned_at, 'Unggahan baru belum dipakai, jadi masuk antrean sementara.');
        $this->assertNull($file->committed_at);

        foreach ($file->variants as $w) {
            Storage::disk('media')->assertExists($file->variantPath($w));
        }
        Storage::disk('media-private')->assertExists($file->originalPath());
    }

    public function test_identical_files_are_stored_once(): void
    {
        $path = $this->image(1000, 800, seed: 5);

        $a = $this->upload($path, 'first.jpg');
        $b = $this->upload($path, 'second-name.jpg');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, MediaFile::count());
    }

    public function test_content_not_filename_decides_validity(): void
    {
        $fake = tempnam(sys_get_temp_dir(), 'txt_');
        $this->tempFiles[] = $fake;
        file_put_contents($fake, 'ini bukan gambar, hanya teks');

        $this->expectException(InvalidMediaException::class);
        $this->upload($fake, 'looks-real.jpg');
    }

    public function test_unsupported_format_is_rejected(): void
    {
        $this->expectExceptionMessage('Format tidak didukung');
        $this->upload($this->image(200, 200, type: 'gif'), 'anim.gif');
    }

    public function test_oversized_file_is_rejected(): void
    {
        config(['media.max_size_kb' => 1]);

        $this->expectExceptionMessage('Ukuran gambar melebihi batas');
        $this->upload($this->image(800, 800), 'big.jpg');
    }

    public function test_pixel_limit_is_enforced_before_decoding(): void
    {
        config(['media.max_pixels' => 1_000_000]);

        $this->expectExceptionMessage('Resolusi gambar melebihi batas');
        $this->upload($this->image(2000, 1000), 'huge.jpg');
    }

    public function test_png_is_accepted(): void
    {
        $file = $this->upload($this->image(600, 400, type: 'png'), 'plan.png')->fresh();

        $this->assertSame(MediaStatus::Ready, $file->status);
    }

    // ---------------------------------------------------------------- kegagalan terisolasi

    public function test_one_failing_image_does_not_break_others_and_can_be_retried(): void
    {
        $this->app->bind(ImageProcessor::class, fn () => new class extends ImageProcessor
        {
            public function generate(string $sourcePath, callable $onVariant): array
            {
                throw new RuntimeException('boom');
            }
        });

        $failed = $this->upload($this->image(900, 600, seed: 2))->fresh();

        $this->assertSame(MediaStatus::Failed, $failed->status);
        $this->assertNotNull($failed->error);
        Storage::disk('media')->assertMissing($failed->variantPath(900));

        // Gambar gagal tidak boleh dipasang ke konten.
        try {
            app(AttachMedia::class)->handle($failed, $this->usable(), 'cover');
            $this->fail('Gambar gagal seharusnya ditolak.');
        } catch (InvalidMediaException) {
            $this->addToAssertionCount(1);
        }

        // Perbaiki pemrosesnya, unggah ulang file yang sama: diproses ulang pada baris yang sama.
        $this->app->bind(ImageProcessor::class, fn () => new ImageProcessor);

        $retried = $this->upload($this->image(900, 600, seed: 2))->fresh();

        $this->assertSame($failed->id, $retried->id);
        $this->assertSame(MediaStatus::Ready, $retried->status);
        $this->assertNull($retried->error);
    }

    // ---------------------------------------------------------------- penggunaan

    public function test_attach_commits_and_detach_returns_file_to_removal_queue(): void
    {
        $file = $this->upload($this->image(800, 600, seed: 3))->fresh();
        $holder = $this->usable();

        $usage = app(AttachMedia::class)->handle($file, $holder, 'cover', ['alt_text' => 'Fasad gedung']);

        $file->refresh();
        $this->assertNull($file->orphaned_at);
        $this->assertNotNull($file->committed_at);
        $this->assertSame('Fasad gedung', $usage->alt_text);

        app(DetachMedia::class)->handle($usage);

        $this->assertNotNull($file->fresh()->orphaned_at);
    }

    public function test_alt_text_and_caption_are_per_usage(): void
    {
        $file = $this->upload($this->image(800, 600, seed: 4))->fresh();
        $a = $this->usable();
        $b = Category::create(['type' => CategoryType::Article, 'name' => 'Other']);

        $u1 = app(AttachMedia::class)->handle($file, $a, 'gallery', ['alt_text' => 'Tampak depan', 'caption' => 'A']);
        $u2 = app(AttachMedia::class)->handle($file, $b, 'inline', ['alt_text' => 'Tampak samping', 'caption' => 'B']);

        $this->assertSame($u1->media_file_id, $u2->media_file_id);
        $this->assertNotSame($u1->alt_text, $u2->alt_text);

        // Melepas salah satu tidak membuat file masuk antrean hapus selama masih dipakai.
        app(DetachMedia::class)->handle($u1);
        $this->assertNull($file->fresh()->orphaned_at);
    }

    public function test_srcset_and_best_variant(): void
    {
        $file = $this->upload($this->image(3000, 2000, seed: 6))->fresh();

        $this->assertSame(800, $file->bestVariant(700));
        $this->assertSame(3000, $file->bestVariant(99999), 'Bila permintaan melebihi semua varian, pakai yang terbesar.');
        $this->assertStringContainsString('w400.webp', $file->srcset());
        $this->assertStringContainsString(' 400w', $file->srcset());
    }

    // ---------------------------------------------------------------- pembersihan

    public function test_never_used_upload_is_purged_only_after_temp_retention(): void
    {
        $file = $this->upload($this->image(600, 400, seed: 7))->fresh();

        $this->assertSame(0, app(PurgeOrphanedMedia::class)->handle()['purged']);
        $this->assertDatabaseHas('media_files', ['id' => $file->id]);

        $this->travel(25)->hours();

        $this->assertSame(1, app(PurgeOrphanedMedia::class)->handle()['purged']);
        $this->assertDatabaseMissing('media_files', ['id' => $file->id]);
        Storage::disk('media')->assertMissing($file->variantPath(600));
        Storage::disk('media-private')->assertMissing($file->originalPath());
    }

    public function test_removed_image_is_kept_for_seven_days(): void
    {
        $file = $this->upload($this->image(600, 400, seed: 8))->fresh();
        $usage = app(AttachMedia::class)->handle($file, $this->usable(), 'cover');
        app(DetachMedia::class)->handle($usage);

        // 25 jam sudah melewati retensi temporary, tetapi gambar ini pernah dipakai: retensi 7 hari.
        $this->travel(25)->hours();
        $this->assertSame(0, app(PurgeOrphanedMedia::class)->handle()['purged']);

        $this->travel(8)->days();
        $this->assertSame(1, app(PurgeOrphanedMedia::class)->handle()['purged']);
        Storage::disk('media')->assertMissing($file->variantPath(600));
    }

    public function test_reusing_an_image_during_retention_cancels_deletion(): void
    {
        $file = $this->upload($this->image(600, 400, seed: 9))->fresh();
        $usage = app(AttachMedia::class)->handle($file, $this->usable(), 'cover');
        app(DetachMedia::class)->handle($usage);

        $this->travel(5)->days();
        app(AttachMedia::class)->handle($file->fresh(), $this->usable(), 'gallery');

        $this->assertNull($file->fresh()->orphaned_at);

        $this->travel(10)->days();
        $this->assertSame(0, app(PurgeOrphanedMedia::class)->handle()['purged']);
        Storage::disk('media')->assertExists($file->variantPath(600));
    }

    public function test_file_in_use_is_never_purged_even_if_marked_orphaned(): void
    {
        $file = $this->upload($this->image(600, 400, seed: 10))->fresh();
        app(AttachMedia::class)->handle($file, $this->usable(), 'cover');

        // Simulasikan data tidak konsisten: ditandai orphaned lama padahal masih dipakai.
        $file->forceFill(['orphaned_at' => now()->subYear()])->save();

        $this->assertSame(0, app(PurgeOrphanedMedia::class)->handle()['purged']);
        $this->assertDatabaseHas('media_files', ['id' => $file->id]);
        $this->assertNull($file->fresh()->orphaned_at, 'Tanda orphaned yang keliru dipulihkan.');
    }

    public function test_unmarked_unused_files_are_reconciled_into_the_queue(): void
    {
        $file = $this->upload($this->image(600, 400, seed: 11))->fresh();
        $file->forceFill(['orphaned_at' => null])->save();

        $result = app(PurgeOrphanedMedia::class)->handle();

        $this->assertSame(1, $result['reconciled']);
        $this->assertNotNull($file->fresh()->orphaned_at);
    }

    public function test_purge_is_recorded_in_activity_log(): void
    {
        $this->upload($this->image(600, 400, seed: 12));
        $this->travel(25)->hours();

        app(PurgeOrphanedMedia::class)->handle();

        $this->assertTrue(Activity::query()->where('log_name', 'media')->where('event', 'deleted')->exists());
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $file = $this->upload($this->image(600, 400, seed: 13))->fresh();
        $this->travel(25)->hours();

        $this->assertSame(1, app(PurgeOrphanedMedia::class)->handle(dryRun: true)['purged']);
        $this->assertDatabaseHas('media_files', ['id' => $file->id]);
        Storage::disk('media')->assertExists($file->variantPath(600));
    }
}
