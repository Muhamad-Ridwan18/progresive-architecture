<?php

namespace Tests\Feature;

use App\Actions\Media\AttachMedia;
use App\Actions\Media\UploadMedia;
use App\Actions\Projects\CreateProject;
use App\Actions\Projects\PublishProject;
use App\Enums\CategoryType;
use App\Enums\ProjectStatus;
use App\Models\Category;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Perintah `projects:arrange-galleries`: grup 2-3 foto per jenis dengan satu caption per grup. */
class ArrangeProjectGalleriesTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function project(string $name, array $originalNames): Project
    {
        $category = Category::query()->firstOrCreate(['type' => CategoryType::Project->value, 'slug' => 'architecture'], ['name' => 'Architecture']);
        $project = app(CreateProject::class)->handle(['name' => $name, 'category_id' => $category->id]);

        foreach ($originalNames as $i => $original) {
            $im = imagecreatetruecolor(1200, 800);
            imagefilledrectangle($im, 0, 0, 1200, 800, imagecolorallocate($im, ($i * 37) % 255, ($i * 91) % 255, ($i * 53) % 255));
            $path = tempnam(sys_get_temp_dir(), 'img_');
            $this->tempFiles[] = $path;
            imagejpeg($im, $path, 80);

            $file = app(UploadMedia::class)->handle($path, $original)->fresh();
            app(AttachMedia::class)->handle($file, $project->fresh(), $i === 0 ? 'cover' : 'gallery', ['sort_order' => $i, 'caption' => 'caption lama']);
        }

        return app(PublishProject::class)->handle($project->fresh());
    }

    public function test_photos_are_grouped_by_kind_with_one_caption_per_group(): void
    {
        $names = ['AD HOUSE - EKSTERIOR 1.jpg'];

        foreach (range(2, 6) as $n) {
            $names[] = "AD HOUSE - EKSTERIOR {$n}.jpg";
        }

        foreach (range(1, 4) as $n) {
            $names[] = "AD HOUSE - INTERIOR {$n}.jpg";
        }

        // 1 cover + 5 eksterior (3 + 2) + 4 interior (2 + 2).
        $project = $this->project('AD House', $names);

        $this->artisan('projects:arrange-galleries')->assertSuccessful();

        $photos = $project->fresh()->usages(draft: true, role: 'gallery');

        $this->assertSame([false, true, true, false, true], $photos->take(5)->map->join_previous->all());
        $this->assertSame('AD House — Exterior', $photos[0]->note);
        $this->assertNull($photos[1]->note);
        $this->assertSame('AD House — Interior', $photos[5]->note);
        $this->assertSame([false, true, false, true], $photos->slice(5)->values()->map->join_previous->all());
        $this->assertTrue($photos->every(fn ($p) => $p->caption === null), 'Caption per foto lama dihapus; caption kini milik grup.');
        $this->assertSame(ProjectStatus::UnpublishedChanges, $project->fresh()->status, 'Tanpa --publish, hasilnya menunggu dipublikasikan.');
    }

    public function test_publish_option_makes_the_arrangement_live_and_dry_run_changes_nothing(): void
    {
        $project = $this->project('PS House', ['PS HOUSE - EKSTERIOR 1.jpg', 'PS HOUSE - EKSTERIOR 2.jpg', 'PS HOUSE - EKSTERIOR 3.jpg']);

        $this->artisan('projects:arrange-galleries --dry-run')->assertSuccessful();
        $this->assertNull($project->fresh()->usages(draft: true, role: 'gallery')->first()->note);

        $this->artisan('projects:arrange-galleries --publish')->assertSuccessful();

        $project = $project->fresh();
        $this->assertSame(ProjectStatus::Published, $project->status);
        $this->assertSame('PS House — Exterior', $project->usages(draft: false, role: 'gallery')->first()->note);

        $this->get(route('projects.show', $project->slug))->assertOk()->assertSee('PS House — Exterior');
    }

    public function test_testing_projects_and_projects_without_gallery_photos_are_skipped(): void
    {
        $testing = $this->project('Testing Project', ['T EKSTERIOR 1.jpg', 'T EKSTERIOR 2.jpg']);
        $empty = $this->project('Kosong', ['KOSONG EKSTERIOR 1.jpg']);

        $this->artisan('projects:arrange-galleries --publish')->assertSuccessful();

        $this->assertNull($testing->fresh()->usages(draft: true, role: 'gallery')->first()->note);
        $this->assertSame('caption lama', $testing->fresh()->usages(draft: true, role: 'gallery')->first()->caption);
        $this->assertSame(0, $empty->fresh()->usages(draft: true, role: 'gallery')->count());
    }
}
