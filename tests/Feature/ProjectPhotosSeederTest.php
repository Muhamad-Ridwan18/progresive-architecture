<?php

namespace Tests\Feature;

use App\Actions\Projects\CreateProject;
use App\Actions\Projects\PublishProject;
use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Project;
use Database\Seeders\ProjectPhotosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pemasangan foto proyek dari folder foto/progressive. Memakai folder uji bernama unik ("ZZ TEST HOUSE")
 * yang dihapus sesudahnya; folder foto asli proyek tidak disentuh.
 */
class ProjectPhotosSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        Storage::fake('media-private');

        $this->folder = base_path('foto/progressive/ZZ TEST HOUSE');
        File::ensureDirectoryExists($this->folder);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);

        // Hapus foto/progressive hanya bila kosong (jangan menghapus foto asli yang sudah ditaruh di sana).
        $root = base_path('foto/progressive');

        if (is_dir($root) && count(scandir($root)) === 2) {
            rmdir($root);
        }

        parent::tearDown();
    }

    private function jpeg(string $name, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 120, 140, 160));
        imagejpeg($image, "{$this->folder}/{$name}", 80);
        imagedestroy($image);
    }

    private function project(string $name = 'ZZ Test House'): Project
    {
        $category = Category::query()->firstOrCreate(['type' => CategoryType::Project->value, 'slug' => 'architecture'], ['name' => 'Architecture']);
        $project = app(CreateProject::class)->handle(['name' => $name, 'category_id' => $category->id]);

        return app(PublishProject::class)->handle($project);
    }

    public function test_photos_become_cover_and_gallery_with_exterior_first_and_natural_order(): void
    {
        $this->jpeg('ZZ TEST HOUSE - INTERIOR 11.jpg', 800, 600);
        $this->jpeg('ZZ TEST HOUSE - INTERIOR 2.jpg', 810, 600);
        $this->jpeg('ZZ TEST HOUSE - EKSTERIOR 7.jpg', 820, 600);

        $project = $this->project();

        $this->seed(ProjectPhotosSeeder::class);

        $project->refresh();
        $cover = $project->publishedCoverUsage()->with('file')->first();
        $gallery = $project->publishedGalleryUsages()->with('file')->get();

        $this->assertNotNull($cover, 'Cover published harus terpasang.');
        $this->assertSame('ZZ TEST HOUSE - EKSTERIOR 7.jpg', $cover->file->original_name);
        $this->assertSame(['ZZ TEST HOUSE - INTERIOR 2.jpg', 'ZZ TEST HOUSE - INTERIOR 11.jpg'], $gallery->pluck('file.original_name')->all());
    }

    public function test_oversized_photos_are_downscaled_to_2560_px(): void
    {
        $this->jpeg('ZZ TEST HOUSE - EKSTERIOR 1.jpg', 3600, 2400);

        $project = $this->project();

        $this->seed(ProjectPhotosSeeder::class);

        $file = $project->refresh()->publishedCoverUsage()->with('file')->firstOrFail()->file;

        $this->assertSame(2560, max($file->width, $file->height));
    }

    public function test_projects_with_a_cover_or_without_a_folder_are_left_alone_and_rerun_is_safe(): void
    {
        $this->jpeg('ZZ TEST HOUSE - EKSTERIOR 1.jpg', 800, 600);

        $project = $this->project();
        $other = $this->project('Rumah Tanpa Folder');

        $this->seed(ProjectPhotosSeeder::class);
        $this->seed(ProjectPhotosSeeder::class);

        $this->assertSame(1, $project->refresh()->mediaUsages()->where('is_draft', false)->count(), 'Dijalankan ulang tidak menggandakan foto.');
        $this->assertSame(0, $other->refresh()->mediaUsages()->count());
    }
}
