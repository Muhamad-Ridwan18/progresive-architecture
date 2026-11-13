<?php

namespace Tests\Feature;

use App\Actions\Media\AttachMedia;
use App\Actions\Media\PurgeOrphanedMedia;
use App\Actions\Media\UploadMedia;
use App\Actions\Projects\ArchiveProject;
use App\Actions\Projects\CreateProject;
use App\Actions\Projects\DiscardProjectDraft;
use App\Actions\Projects\PublishProject;
use App\Actions\Projects\SaveProjectDraft;
use App\Actions\Projects\StaleDraftException;
use App\Enums\CategoryType;
use App\Enums\MediaStatus;
use App\Enums\ProjectStatus;
use App\Models\Category;
use App\Models\MediaFile;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        Storage::fake('media-private');

        $this->category = Category::create(['type' => CategoryType::Project, 'name' => 'Commercial']);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function newProject(string $name = 'Tower One'): Project
    {
        return app(CreateProject::class)->handle(['name' => $name, 'category_id' => $this->category->id]);
    }

    /** Proyek yang sudah dipublikasikan dengan isi lengkap. */
    private function publishedProject(): Project
    {
        $p = $this->newProject();
        $p = app(SaveProjectDraft::class)->handle($p, ['location' => 'Jakarta', 'year' => 2023, 'description' => 'Menara perkantoran'], $p->draft_version);

        return app(PublishProject::class)->handle($p);
    }

    private function edit(Project $p, array $fields): Project
    {
        return app(SaveProjectDraft::class)->handle($p, $fields, $p->draft_version);
    }

    private function media(int $seed): MediaFile
    {
        $im = imagecreatetruecolor(800, 600);
        imagefilledrectangle($im, 0, 0, 800, 600, imagecolorallocate($im, ($seed * 37) % 255, ($seed * 91) % 255, ($seed * 53) % 255));
        $path = tempnam(sys_get_temp_dir(), 'img_');
        $this->tempFiles[] = $path;
        imagejpeg($im, $path, 85);

        return app(UploadMedia::class)->handle($path, "p{$seed}.jpg")->fresh();
    }

    // ---------------------------------------------------------------- buat

    public function test_new_project_starts_as_draft_with_generated_slug(): void
    {
        $p = $this->newProject('Gedung Bank Sentral');

        $this->assertSame(ProjectStatus::Draft, $p->status);
        $this->assertSame('gedung-bank-sentral', $p->slug);
        $this->assertNull($p->published_at);
        $this->assertSame('Gedung Bank Sentral', $p->draftFields()['name']);
        $this->assertTrue(Activity::where('log_name', 'project')->where('event', 'created')->exists());
    }

    public function test_duplicate_names_get_unique_slugs(): void
    {
        $this->assertSame('tower', $this->newProject('Tower')->slug);
        $this->assertSame('tower-2', $this->newProject('Tower')->slug);
    }

    public function test_only_project_categories_are_accepted(): void
    {
        $article = Category::create(['type' => CategoryType::Article, 'name' => 'News']);

        $this->expectException(ValidationException::class);
        app(CreateProject::class)->handle(['name' => 'X', 'category_id' => $article->id]);
    }

    // ---------------------------------------------------------------- draft vs published

    public function test_editing_a_draft_project_does_not_touch_published_columns(): void
    {
        $p = $this->newProject('Original');
        $p = $this->edit($p, ['location' => 'Bandung']);

        $this->assertSame('Bandung', $p->draftFields()['location']);
        $this->assertNull($p->location, 'Kolom utama baru terisi saat publish.');
        $this->assertSame(ProjectStatus::Draft, $p->status);
    }

    public function test_publish_copies_draft_into_published_columns(): void
    {
        $p = $this->newProject();
        $p = $this->edit($p, ['name' => 'Tower Prime', 'location' => 'Jakarta', 'year' => 2024]);
        $p = app(PublishProject::class)->handle($p);

        $this->assertSame(ProjectStatus::Published, $p->status);
        $this->assertSame('Tower Prime', $p->name);
        $this->assertSame('Jakarta', $p->location);
        $this->assertSame(2024, $p->year);
        $this->assertNotNull($p->published_at);
        $this->assertTrue(Activity::where('event', 'published')->exists());
    }

    public function test_editing_published_project_keeps_live_version_until_publish(): void
    {
        $p = $this->publishedProject();

        $p = $this->edit($p, ['location' => 'Surabaya']);

        $this->assertSame(ProjectStatus::UnpublishedChanges, $p->status);
        $this->assertSame('Jakarta', $p->location, 'Versi published tetap lama.');
        $this->assertSame('Surabaya', $p->draftFields()['location']);

        $p = app(PublishProject::class)->handle($p);

        $this->assertSame('Surabaya', $p->location);
        $this->assertSame(ProjectStatus::Published, $p->status);
    }

    public function test_reverting_edits_returns_status_to_published(): void
    {
        $p = $this->publishedProject();

        $p = $this->edit($p, ['location' => 'Medan']);
        $this->assertSame(ProjectStatus::UnpublishedChanges, $p->status);

        $p = $this->edit($p, ['location' => 'Jakarta']);
        $this->assertSame(ProjectStatus::Published, $p->status);
    }

    public function test_discard_restores_the_published_version(): void
    {
        $p = $this->publishedProject();
        $cover = $this->media(1);
        app(AttachMedia::class)->handle($cover, $p, Project::ROLE_COVER);
        $p = $this->edit($p, ['name' => 'Salah Ketik']);

        $this->assertSame(ProjectStatus::UnpublishedChanges, $p->fresh()->status);

        $p = app(DiscardProjectDraft::class)->handle($p->fresh());

        $this->assertSame('Tower One', $p->draftFields()['name']);
        $this->assertSame(ProjectStatus::Published, $p->status);
        $this->assertNull($p->cover(draft: true), 'Gambar draft ikut dikembalikan ke versi published.');
    }

    public function test_discard_requires_a_published_version(): void
    {
        $this->expectException(RuntimeException::class);
        app(DiscardProjectDraft::class)->handle($this->newProject());
    }

    // ---------------------------------------------------------------- tabrakan edit

    public function test_stale_save_is_rejected_and_does_not_overwrite(): void
    {
        $p = $this->newProject();
        $oldVersion = $p->draft_version;

        $p = $this->edit($p, ['location' => 'Tab pertama']);

        $this->expectException(StaleDraftException::class);

        try {
            app(SaveProjectDraft::class)->handle($p, ['location' => 'Tab kedua (usang)'], $oldVersion);
        } finally {
            $this->assertSame('Tab pertama', $p->fresh()->draftFields()['location']);
        }
    }

    public function test_overlapping_saves_from_the_same_editor_are_not_a_conflict(): void
    {
        $save = app(SaveProjectDraft::class);
        $p = $this->newProject();
        $v = $p->draft_version;

        // Editor A menyimpan; permintaan A berikutnya yang tumpang tindih masih membawa versi lama.
        $p = $save->handle($p, ['location' => 'Satu'], $v, null, 'editor-A');
        $p = $save->handle($p, ['location' => 'Dua'], $v, null, 'editor-A');

        $this->assertSame('Dua', $p->draftFields()['location']);
    }

    public function test_a_different_editor_with_a_stale_version_is_still_rejected(): void
    {
        $save = app(SaveProjectDraft::class);
        $p = $this->newProject();
        $v = $p->draft_version;

        $save->handle($p, ['location' => 'Dari A'], $v, null, 'editor-A');

        $this->expectException(StaleDraftException::class);
        $save->handle($p->fresh(), ['location' => 'Dari B (usang)'], $v, null, 'editor-B');
    }

    public function test_editor_identity_does_not_let_a_stale_editor_overwrite_after_someone_else_saved(): void
    {
        $save = app(SaveProjectDraft::class);
        $p = $this->newProject();
        $v = $p->draft_version;

        $p = $save->handle($p, ['location' => 'A1'], $v, null, 'editor-A');
        $p = $save->handle($p, ['location' => 'B1'], $p->draft_version, null, 'editor-B'); // B memuat versi terbaru

        // Permintaan usang milik A (versi lama) tiba terlambat: B yang terakhir menyimpan, jadi ditolak.
        $this->expectException(StaleDraftException::class);
        $save->handle($p->fresh(), ['location' => 'A2 (usang)'], $v, null, 'editor-A');
    }

    public function test_publish_invalidates_stale_editors(): void
    {
        $p = $this->newProject();
        $stale = $p->draft_version;
        app(PublishProject::class)->handle($p);

        $this->expectException(StaleDraftException::class);
        app(SaveProjectDraft::class)->handle($p->fresh(), ['location' => 'x'], $stale);
    }

    // ---------------------------------------------------------------- validasi

    public function test_invalid_input_is_rejected(): void
    {
        $p = $this->newProject();

        foreach ([
            ['name' => ''],
            ['slug' => 'Slug Salah!'],
            ['year' => 1500],
            ['category_id' => 9999],
        ] as $bad) {
            try {
                $this->edit($p, $bad);
                $this->fail('Input tidak valid seharusnya ditolak: '.json_encode($bad));
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('Tower One', $p->fresh()->draftFields()['name']);
    }

    public function test_slug_must_be_unique_against_published_and_draft_slugs(): void
    {
        $a = $this->newProject('Alpha');
        $b = $this->newProject('Beta');

        $this->expectException(ValidationException::class);
        $this->edit($b, ['slug' => $a->slug]);
    }

    // ---------------------------------------------------------------- gambar

    public function test_draft_media_is_invisible_until_publish_and_protects_files(): void
    {
        $p = $this->publishedProject();
        $file = $this->media(2);

        app(AttachMedia::class)->handle($file, $p, Project::ROLE_GALLERY, ['alt_text' => 'Lobi']);

        $this->assertCount(1, $p->usages(draft: true));
        $this->assertCount(0, $p->usages(draft: false), 'Belum dipublikasikan.');

        // File yang hanya dipakai draft tidak boleh dibersihkan walau sudah lewat masa tunggu.
        $this->travel(30)->days();
        $this->assertSame(0, app(PurgeOrphanedMedia::class)->handle()['purged']);
        $this->assertDatabaseHas('media_files', ['id' => $file->id]);

        $p->syncStatus();
        $this->assertSame(ProjectStatus::UnpublishedChanges, $p->fresh()->status);

        app(PublishProject::class)->handle($p->fresh());

        $this->assertCount(1, $p->usages(draft: false));
        $this->assertSame('Lobi', $p->usages(draft: false)->first()->alt_text);
        $this->assertCount(1, $p->usages(draft: true), 'Draft tetap punya salinannya.');
    }

    public function test_publish_is_blocked_while_images_are_not_ready(): void
    {
        $p = $this->newProject();
        $file = $this->media(20);
        app(AttachMedia::class)->handle($file, $p, Project::ROLE_COVER);

        // Gambar masih diproses / gagal: tidak punya varian, akan tampil kosong di website.
        $file->forceFill(['status' => MediaStatus::Processing, 'variants' => null])->save();

        try {
            app(PublishProject::class)->handle($p->fresh());
            $this->fail('Publish seharusnya ditahan.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('media', $e->errors());
        }

        $this->assertSame(ProjectStatus::Draft, $p->fresh()->status, 'Status tidak berubah.');
        $this->assertCount(0, $p->usages(draft: false), 'Tidak ada yang tersalin ke versi published.');

        // Setelah gambar siap, publish berhasil.
        $file->forceFill(['status' => MediaStatus::Ready, 'variants' => [800]])->save();
        $this->assertSame(ProjectStatus::Published, app(PublishProject::class)->handle($p->fresh())->status);
    }

    public function test_publishing_twice_does_not_duplicate_published_media(): void
    {
        $p = $this->publishedProject();
        app(AttachMedia::class)->handle($this->media(3), $p, Project::ROLE_COVER);

        app(PublishProject::class)->handle($p->fresh());
        app(PublishProject::class)->handle($p->fresh());

        $this->assertCount(1, $p->usages(draft: false, role: Project::ROLE_COVER));
    }

    public function test_deleting_project_releases_its_images(): void
    {
        $p = $this->publishedProject();
        $file = $this->media(4);
        app(AttachMedia::class)->handle($file, $p, Project::ROLE_COVER);
        app(PublishProject::class)->handle($p->fresh());

        $p->fresh()->delete();

        $this->assertDatabaseMissing('projects', ['id' => $p->id]);
        $this->assertDatabaseCount('media_usages', 0);
        $this->assertNotNull($file->fresh()->orphaned_at, 'File masuk antrean hapus, bukan langsung terhapus.');
        $this->assertTrue(Activity::where('log_name', 'project')->where('event', 'deleted')->exists());
    }

    // ---------------------------------------------------------------- arsip

    public function test_archive_and_restore(): void
    {
        $archive = app(ArchiveProject::class);

        $published = $this->publishedProject();
        $archive->archive($published);
        $this->assertSame(ProjectStatus::Archived, $published->fresh()->status);
        $this->assertFalse($published->fresh()->status->isLive());

        $restored = $archive->restore($published->fresh());
        $this->assertSame(ProjectStatus::Published, $restored->status);

        // Belum pernah dipublikasikan: kembali ke Draft.
        $draft = $this->newProject('Baru');
        $archive->archive($draft);
        $this->assertSame(ProjectStatus::Draft, $archive->restore($draft->fresh())->status);

        // Punya editan yang belum dipublikasikan: kembali ke Unpublished Changes.
        $dirty = $this->publishedProject();
        $dirty = $this->edit($dirty, ['name' => 'Berubah']);
        $archive->archive($dirty->fresh());
        $this->assertSame(ProjectStatus::UnpublishedChanges, $archive->restore($dirty->fresh())->status);
    }
}
