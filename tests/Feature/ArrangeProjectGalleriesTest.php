<?php

namespace Tests\Feature;

use App\Actions\Media\AttachMedia;
use App\Actions\Media\UploadMedia;
use App\Actions\Projects\CreateProject;
use App\Actions\Projects\PublishProject;
use App\Actions\Projects\SaveProjectDraft;
use App\Enums\CategoryType;
use App\Enums\ProjectStatus;
use App\Models\Category;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Perintah `projects:arrange-galleries`: grup 2-3 foto dan narasi dipecah menjadi caption grup. */
class ArrangeProjectGalleriesTest extends TestCase
{
    use RefreshDatabase;

    private const NARRATIVE = "Paragraf satu tetap di header.\n\nParagraf dua untuk grup pertama.\n\nParagraf tiga untuk grup kedua.";

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /** @param list<string> $originalNames foto pertama menjadi cover, sisanya galeri */
    private function project(string $name, array $originalNames, ?string $description = self::NARRATIVE): Project
    {
        $category = Category::query()->firstOrCreate(['type' => CategoryType::Project->value, 'slug' => 'architecture'], ['name' => 'Architecture']);
        $project = app(CreateProject::class)->handle(['name' => $name, 'category_id' => $category->id]);

        if ($description !== null) {
            $project = app(SaveProjectDraft::class)->handle($project, ['description' => $description], $project->draft_version);
        }

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

    public function test_photos_are_grouped_by_kind_and_the_narrative_becomes_group_captions(): void
    {
        $names = ['AD HOUSE - EKSTERIOR 1.jpg'];

        foreach (range(2, 6) as $n) {
            $names[] = "AD HOUSE - EKSTERIOR {$n}.jpg";
        }

        foreach (range(1, 4) as $n) {
            $names[] = "AD HOUSE - INTERIOR {$n}.jpg";
        }

        // 1 cover + 5 eksterior (3 + 2) + 4 interior (2 + 2): empat grup.
        $project = $this->project('AD House', $names);

        $this->artisan('projects:arrange-galleries')->assertSuccessful();

        $project = $project->fresh();
        $photos = $project->usages(draft: true, role: 'gallery');

        // Tidak mencampur eksterior dan interior; grup berukuran 3, 2, 2, 2.
        $this->assertSame([false, true, true, false, true, false, true, false, true], $photos->map->join_previous->all());
        $this->assertTrue($photos->every(fn ($p) => $p->caption === null), 'Caption per foto lama dihapus; caption kini milik grup.');

        // Paragraf 2 dan 3 menjadi caption grup 1 dan 2; paragraf 1 tetap di header; grup 3 dan 4 tanpa caption.
        $this->assertSame('Paragraf satu tetap di header.', $project->draftFields()['description']);
        $this->assertSame('Paragraf dua untuk grup pertama.', $photos[0]->note);
        $this->assertSame('Paragraf tiga untuk grup kedua.', $photos[3]->note);
        $this->assertNull($photos[5]->note);
        $this->assertNull($photos[7]->note);

        $this->assertSame(ProjectStatus::UnpublishedChanges, $project->status, 'Tanpa --publish, hasilnya menunggu dipublikasikan.');
    }

    public function test_extra_paragraphs_are_merged_into_the_last_group_so_no_text_is_lost(): void
    {
        $narrative = "Satu.\n\nDua.\n\nTiga.\n\nEmpat.\n\nLima.";
        $project = $this->project('Rumah Panjang', ['A 1.jpg', 'A 2.jpg', 'A 3.jpg', 'A 4.jpg'], $narrative);

        $this->artisan('projects:arrange-galleries --publish')->assertSuccessful();

        $photos = $project->fresh()->usages(draft: false, role: 'gallery');

        // 3 foto galeri = 1 grup; semua paragraf 2..5 masuk ke grup tunggal itu.
        $this->assertSame("Dua.\n\nTiga.\n\nEmpat.\n\nLima.", $photos[0]->note);
        $this->assertSame('Satu.', $project->fresh()->description);

        $this->get(route('projects.show', $project->slug))->assertOk()->assertSee('Dua.')->assertSee('Lima.');
    }

    public function test_dry_run_changes_nothing_and_publish_makes_the_result_live(): void
    {
        $project = $this->project('PS House', ['P 1.jpg', 'P 2.jpg', 'P 3.jpg', 'P 4.jpg']);

        $this->artisan('projects:arrange-galleries --dry-run')->assertSuccessful();
        $this->assertNull($project->fresh()->usages(draft: true, role: 'gallery')->first()->note);
        $this->assertStringContainsString('Paragraf dua', $project->fresh()->draftFields()['description']);

        $this->artisan('projects:arrange-galleries --publish')->assertSuccessful();

        $project = $project->fresh();
        $this->assertSame(ProjectStatus::Published, $project->status);
        // Satu grup saja (3 foto galeri): paragraf 2 dan 3 digabung agar tidak ada teks yang hilang.
        $this->assertSame("Paragraf dua untuk grup pertama.\n\nParagraf tiga untuk grup kedua.", $project->usages(draft: false, role: 'gallery')->first()->note);

        $this->get(route('projects.show', $project->slug))->assertOk()->assertSee('Paragraf dua untuk grup pertama.')->assertSee('Paragraf tiga untuk grup kedua.');
    }

    public function test_rerunning_skips_arranged_projects_and_force_rebuilds_without_losing_text(): void
    {
        $project = $this->project('Rumah Ulang', ['R 1.jpg', 'R 2.jpg', 'R 3.jpg', 'R 4.jpg', 'R 5.jpg']);

        $this->artisan('projects:arrange-galleries --publish')->assertSuccessful();

        $first = $project->fresh()->usages(draft: true, role: 'gallery');

        // Tanpa --force proyek yang sudah tersusun dilewati (narasi tidak berubah).
        $this->artisan('projects:arrange-galleries --publish')->expectsOutputToContain('sudah tersusun')->assertSuccessful();
        $this->assertSame('Paragraf dua untuk grup pertama.', $project->fresh()->usages(draft: true, role: 'gallery')->first()->note);

        // Dengan --force teks tetap utuh: narasi disatukan lalu dibagi lagi.
        $this->artisan('projects:arrange-galleries --force --publish')->assertSuccessful();

        $after = $project->fresh();
        $this->assertSame('Paragraf satu tetap di header.', $after->draftFields()['description']);
        $this->assertSame('Paragraf dua untuk grup pertama.', $after->usages(draft: true, role: 'gallery')->first()->note);
        $this->assertSame($first->count(), $after->usages(draft: true, role: 'gallery')->count());
    }

    public function test_testing_projects_and_projects_without_gallery_photos_are_skipped(): void
    {
        $testing = $this->project('Testing Project', ['T 1.jpg', 'T 2.jpg', 'T 3.jpg']);
        $empty = $this->project('Kosong', ['K 1.jpg']);

        $this->artisan('projects:arrange-galleries --publish')->assertSuccessful();

        $this->assertNull($testing->fresh()->usages(draft: true, role: 'gallery')->first()->note);
        $this->assertSame('caption lama', $testing->fresh()->usages(draft: true, role: 'gallery')->first()->caption);
        $this->assertStringContainsString('Paragraf dua', $testing->fresh()->draftFields()['description']);
        $this->assertSame(0, $empty->fresh()->usages(draft: true, role: 'gallery')->count());
    }
}
