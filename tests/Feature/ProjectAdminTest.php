<?php

namespace Tests\Feature;

use App\Actions\Projects\CreateProject as CreateProjectAction;
use App\Actions\Projects\PublishProject;
use App\Actions\Projects\SaveProjectDraft;
use App\Enums\CategoryType;
use App\Enums\MediaStatus;
use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Livewire\ProjectMediaManager;
use App\Models\Category;
use App\Models\MediaUsage;
use App\Models\Project;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectAdminTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        Storage::fake('media');
        Storage::fake('media-private');

        $this->actingAs(User::factory()->create());
        $this->category = Category::create(['type' => CategoryType::Project, 'name' => 'Commercial']);
    }

    private function project(string $name = 'Tower One'): Project
    {
        return app(CreateProjectAction::class)->handle(['name' => $name, 'category_id' => $this->category->id]);
    }

    private function photo(string $name = 'a.jpg', int $w = 800, int $h = 600): UploadedFile
    {
        return UploadedFile::fake()->image($name, $w, $h);
    }

    private function manager(Project $p, string $role): Testable
    {
        return Livewire::test(ProjectMediaManager::class, ['projectId' => $p->id, 'role' => $role]);
    }

    // ---------------------------------------------------------------- halaman

    public function test_pages_load(): void
    {
        $p = $this->project();

        foreach (['/admin/projects', '/admin/projects/create', "/admin/projects/{$p->id}/edit"] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_list_shows_draft_name_and_status(): void
    {
        $p = $this->project('Gedung A');

        Livewire::test(ListProjects::class)
            ->assertCanSeeTableRecords([$p])
            ->assertSee('Gedung A')
            ->assertSee('Draft');
    }

    public function test_create_page_makes_a_draft_and_opens_the_editor(): void
    {
        Livewire::test(CreateProject::class)
            ->fillForm(['name' => 'Menara Baru', 'category_id' => $this->category->id])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $p = Project::firstWhere('name', 'Menara Baru');

        $this->assertSame(ProjectStatus::Draft, $p->status);
        $this->assertSame('menara-baru', $p->slug);
    }

    // ---------------------------------------------------------------- auto-save

    public function test_editor_is_filled_from_the_draft_not_published_columns(): void
    {
        $p = $this->project();
        $p = app(PublishProject::class)->handle($p);
        app(SaveProjectDraft::class)->handle($p, ['location' => 'Draft Location'], $p->draft_version);

        Livewire::test(EditProject::class, ['record' => $p->id])
            ->assertFormSet(['location' => 'Draft Location']);
    }

    public function test_autosave_writes_to_draft_only(): void
    {
        $p = app(PublishProject::class)->handle($this->project());

        $page = Livewire::test(EditProject::class, ['record' => $p->id])
            ->fillForm(['location' => 'Semarang', 'year' => 2022])
            ->call('autosave');

        $page->assertSet('saveState', 'saved');

        $p->refresh();
        $this->assertSame('Semarang', $p->draftFields()['location']);
        $this->assertNull($p->location, 'Versi published tidak berubah.');
        $this->assertSame(ProjectStatus::UnpublishedChanges, $p->status);
    }

    public function test_autosave_with_invalid_input_saves_nothing(): void
    {
        $p = $this->project('Valid');

        Livewire::test(EditProject::class, ['record' => $p->id])
            ->fillForm(['name' => ''])
            ->call('autosave')
            ->assertSet('saveState', 'invalid');

        $this->assertSame('Valid', $p->fresh()->draftFields()['name']);
    }

    public function test_autosave_detects_a_conflicting_editor(): void
    {
        $p = $this->project();

        $page = Livewire::test(EditProject::class, ['record' => $p->id]);

        // Tab/administrator lain menyimpan lebih dulu.
        app(SaveProjectDraft::class)->handle($p->fresh(), ['location' => 'Dari tab lain'], $p->draft_version);

        $page->fillForm(['location' => 'Dari tab usang'])
            ->call('autosave')
            ->assertSet('saveState', 'conflict');

        $this->assertSame('Dari tab lain', $p->fresh()->draftFields()['location']);
    }

    public function test_built_in_save_cannot_bypass_the_draft_workflow(): void
    {
        $p = app(PublishProject::class)->handle($this->project());

        Livewire::test(EditProject::class, ['record' => $p->id])
            ->fillForm(['location' => 'Lewat save bawaan'])
            ->call('save');

        $this->assertNull($p->fresh()->location, 'save() bawaan tidak boleh menulis ke versi published.');
    }

    // ---------------------------------------------------------------- publish / buang / arsip

    public function test_publish_action_publishes_the_draft(): void
    {
        $p = $this->project();

        Livewire::test(EditProject::class, ['record' => $p->id])
            ->fillForm(['location' => 'Makassar'])
            ->callAction('publish')
            ->assertSet('saveState', 'saved');

        $p->refresh();
        $this->assertSame(ProjectStatus::Published, $p->status);
        $this->assertSame('Makassar', $p->location, 'Editan yang belum sempat auto-save ikut tersimpan lalu dipublikasikan.');
    }

    public function test_discard_action_restores_the_published_version(): void
    {
        $p = app(PublishProject::class)->handle($this->project());
        $p = app(SaveProjectDraft::class)->handle($p, ['name' => 'Salah'], $p->draft_version);

        Livewire::test(EditProject::class, ['record' => $p->id])
            ->callAction('discard')
            ->assertFormSet(['name' => 'Tower One']);

        $this->assertSame(ProjectStatus::Published, $p->fresh()->status);
    }

    public function test_archive_action_hides_the_project(): void
    {
        $p = app(PublishProject::class)->handle($this->project());

        Livewire::test(EditProject::class, ['record' => $p->id])->callAction('archive');

        $this->assertSame(ProjectStatus::Archived, $p->fresh()->status);
    }

    public function test_list_and_create_pages_explain_the_draft_workflow(): void
    {
        Livewire::test(ListProjects::class)
            ->assertSee('Perubahan disimpan sebagai draft sampai di-publish')
            ->assertSee('New project');

        Livewire::test(CreateProject::class)
            ->assertSee('Isi nama dan kategori dulu')
            ->assertSee('Setelah dibuat')
            ->assertSee('belum tampil di website sampai Anda menekan Publish');
    }

    public function test_text_after_a_photo_is_edited_in_the_media_manager_and_follows_the_draft_flow(): void
    {
        $p = $this->project();
        $m = $this->manager($p, 'gallery')->set('uploads', [$this->photo('a.jpg')]);
        $p = app(PublishProject::class)->handle($p->fresh());
        $usage = $p->usages(draft: true, role: 'gallery')->first();

        $this->assertSame(ProjectStatus::Published, $p->fresh()->status);

        $m->call('updateMeta', $usage->id, 'note', 'Narasi setelah foto.')
            ->call('updateMeta', $usage->id, 'note_by', 'Hilmi, Founder');

        $this->assertSame('Narasi setelah foto.', $usage->fresh()->note);
        $this->assertSame('Hilmi, Founder', $usage->fresh()->note_by);
        $this->assertSame(ProjectStatus::UnpublishedChanges, $p->fresh()->status, 'Teks baru adalah perubahan yang belum dipublikasikan.');
    }

    public function test_credits_are_normalised_validated_and_follow_the_draft_flow(): void
    {
        $p = app(PublishProject::class)->handle($this->project());

        $this->assertNull($p->fresh()->credits);

        $p = app(SaveProjectDraft::class)->handle($p, ['credits' => [
            ['title' => ' Project Team ', 'names' => " Aldy \n\n Reyan "],
            ['title' => 'Kosong', 'names' => ''],
        ]], $p->draft_version);

        $this->assertSame([['title' => 'Project Team', 'names' => "Aldy\nReyan"]], $p->draftFields()['credits']);
        $this->assertSame(ProjectStatus::UnpublishedChanges, $p->status);
        $this->assertSame(['credits'], $p->changedFieldKeys());
        $this->assertNull($p->fresh()->credits, 'Versi published belum berubah sebelum Publish.');

        $p = app(PublishProject::class)->handle($p->fresh());
        $this->assertSame([['title' => 'Project Team', 'names' => "Aldy\nReyan"]], $p->fresh()->credits);
        $this->assertSame(ProjectStatus::Published, $p->fresh()->status);

        // Baris yang hanya berisi kosong dianggap sama dengan "tidak ada kredit".
        $p = app(SaveProjectDraft::class)->handle($p->fresh(), ['credits' => [['title' => 'X', 'names' => ' ']]], $p->draft_version);
        $this->assertNull($p->draftFields()['credits']);

        $this->expectException(ValidationException::class);
        app(SaveProjectDraft::class)->handle($p->fresh(), ['credits' => [['title' => str_repeat('a', 81), 'names' => 'Nama']]], $p->fresh()->draft_version);
    }

    public function test_credits_can_be_edited_in_the_project_editor(): void
    {
        $p = $this->project();

        Livewire::test(EditProject::class, ['record' => $p->id])
            ->assertSee('Credits')
            ->fillForm(['credits' => [['title' => 'Collaborators', 'names' => "A+Architecture\nALTO"]]])
            ->call('autosave')
            ->assertSet('saveState', 'saved');

        $this->assertSame([['title' => 'Collaborators', 'names' => "A+Architecture\nALTO"]], $p->fresh()->draftFields()['credits']);
    }

    public function test_map_coordinates_are_validated_and_edited_in_the_editor(): void
    {
        $p = $this->project();

        Livewire::test(EditProject::class, ['record' => $p->id])
            ->assertSee('Location map')
            ->fillForm(['latitude' => -6.917464, 'longitude' => 107.619123])
            ->call('autosave')
            ->assertSet('saveState', 'saved');

        $fields = $p->fresh()->draftFields();
        $this->assertSame(-6.917464, $fields['latitude']);
        $this->assertSame(107.619123, $fields['longitude']);

        foreach ([['latitude' => 95], ['longitude' => -190], ['latitude' => 'abc']] as $invalid) {
            try {
                app(SaveProjectDraft::class)->handle($p->fresh(), $invalid, $p->fresh()->draft_version);
                $this->fail('Koordinat tidak valid seharusnya ditolak: '.json_encode($invalid));
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    // ---------------------------------------------------------------- penanda perubahan

    public function test_editor_summarises_what_has_changed_since_publish(): void
    {
        $p = app(PublishProject::class)->handle($this->project());

        Livewire::test(EditProject::class, ['record' => $p->id])
            ->assertDontSee('perubahan belum di-publish');

        $p = app(SaveProjectDraft::class)->handle($p, ['location' => 'Makassar', 'stage' => 'Complete'], $p->draft_version);

        $this->assertSame(['location', 'stage'], $p->changedFieldKeys());

        Livewire::test(EditProject::class, ['record' => $p->id])
            ->assertSee('2 perubahan belum di-publish: Location, Status')
            ->assertSee('Changed');
    }

    public function test_a_project_that_was_never_published_has_no_change_markers(): void
    {
        $p = app(SaveProjectDraft::class)->handle($this->project(), ['location' => 'Makassar'], 1);

        $this->assertSame([], $p->changedFieldKeys());

        Livewire::test(EditProject::class, ['record' => $p->id])->assertDontSee('perubahan belum di-publish');
    }

    // ---------------------------------------------------------------- pengelola gambar

    public function test_gallery_upload_creates_draft_usages_and_processes_images(): void
    {
        $p = $this->project();

        $this->manager($p, 'gallery')
            ->set('uploads', [$this->photo('a.jpg'), $this->photo('b.jpg', 900, 700)])
            ->assertSet('uploadErrors', []);

        $usages = $p->usages(draft: true, role: 'gallery');

        $this->assertCount(2, $usages);
        $this->assertSame(MediaStatus::Ready, $usages->first()->file->status);
        $this->assertCount(0, $p->usages(draft: false), 'Belum dipublikasikan.');
    }

    public function test_invalid_upload_is_reported_without_blocking_valid_ones(): void
    {
        $p = $this->project();

        $this->manager($p, 'gallery')
            ->set('uploads', [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'), $this->photo('ok.jpg')]);

        $this->assertCount(1, $p->usages(draft: true, role: 'gallery'));
    }

    public function test_same_photo_cannot_be_added_twice_to_a_gallery(): void
    {
        $p = $this->project();
        $same = $this->photo('same.jpg');

        $this->manager($p, 'gallery')->set('uploads', [$same])->set('uploads', [$same]);

        $this->assertCount(1, $p->usages(draft: true, role: 'gallery'));
    }

    public function test_new_cover_replaces_the_old_one(): void
    {
        $p = $this->project();

        $m = $this->manager($p, 'cover');
        $m->set('uploads', [$this->photo('c1.jpg', 800, 600)]);
        $m->set('uploads', [$this->photo('c2.jpg', 1000, 700)]);

        $covers = $p->usages(draft: true, role: 'cover');
        $this->assertCount(1, $covers);
        $this->assertSame('c2.jpg', $covers->first()->file->original_name);
    }

    public function test_alt_text_caption_focal_point_and_reordering(): void
    {
        $p = $this->project();
        $m = $this->manager($p, 'gallery')->set('uploads', [$this->photo('a.jpg'), $this->photo('b.jpg', 900, 700)]);

        [$first, $second] = $p->usages(draft: true, role: 'gallery')->all();

        $m->call('updateMeta', $first->id, 'alt_text', 'Lobi utama')
            ->call('updateMeta', $first->id, 'caption', 'Foto 1')
            ->call('updateMeta', $first->id, 'bukan_field', 'x');

        $this->assertSame('Lobi utama', $first->fresh()->alt_text);
        $this->assertSame('Foto 1', $first->fresh()->caption);

        $m->call('move', $first->id, 'down');
        $this->assertSame(
            [$second->id, $first->id],
            $p->usages(draft: true, role: 'gallery')->pluck('id')->all(),
        );

        $cover = $this->manager($p, 'cover')->set('uploads', [$this->photo('cv.jpg')]);
        $coverUsage = $p->cover(draft: true);
        $cover->call('setFocal', $coverUsage->id, 150, -20);

        // JSON tidak membedakan 100.0 dan 100, jadi yang dibandingkan nilainya.
        $this->assertEquals(['x' => 100, 'y' => 0], $coverUsage->fresh()->focal, 'Titik fokus dibatasi 0–100.');
    }

    public function test_removing_an_image_detaches_it_and_queues_the_file_for_cleanup(): void
    {
        $p = $this->project();
        $m = $this->manager($p, 'gallery')->set('uploads', [$this->photo('a.jpg')]);
        $usage = $p->usages(draft: true, role: 'gallery')->first();

        $m->call('remove', $usage->id);

        $this->assertCount(0, $p->usages(draft: true, role: 'gallery'));
        $this->assertNotNull($usage->file->fresh()->orphaned_at);
    }

    public function test_media_changes_update_the_project_status(): void
    {
        $p = app(PublishProject::class)->handle($this->project());
        $this->assertSame(ProjectStatus::Published, $p->status);

        $this->manager($p, 'gallery')->set('uploads', [$this->photo('a.jpg')]);

        $this->assertSame(ProjectStatus::UnpublishedChanges, $p->fresh()->status);
    }

    // ---------------------------------------------------------------- keamanan

    public function test_cannot_touch_another_projects_images(): void
    {
        $mine = $this->project('Mine');
        $other = $this->project('Other');
        $this->manager($other, 'gallery')->set('uploads', [$this->photo('theirs.jpg')]);
        $theirs = $other->usages(draft: true, role: 'gallery')->first();

        $this->manager($mine, 'gallery')
            ->call('remove', $theirs->id)
            ->assertStatus(404);

        $this->assertNotNull(MediaUsage::find($theirs->id), 'Gambar proyek lain tidak boleh terhapus.');
    }

    public function test_inactive_users_cannot_use_the_media_manager(): void
    {
        $p = $this->project();
        $m = $this->manager($p, 'gallery');

        $this->actingAs(User::factory()->create(['is_active' => false]));

        $m->set('uploads', [$this->photo('a.jpg')])->assertForbidden();

        $this->assertCount(0, $p->usages(draft: true));
    }
}
