<?php

namespace Tests\Feature;

use App\Actions\About\PublishAbout;
use App\Actions\About\SaveAboutDraft;
use App\Enums\MediaStatus;
use App\Enums\PageStatus;
use App\Filament\Pages\ManageAboutUs;
use App\Livewire\ProjectMediaManager;
use App\Models\AboutPage;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Foto di bawah visi dan misi pada halaman About: berketerangan, berurutan, berversi draft/published (SRS 3.5, 5.4, 12). */
class AboutVisionPhotosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('media');
        Storage::fake('media-private');

        // Pengelola gambar hanya melayani administrator aktif.
        $this->actingAs(User::factory()->create());
    }

    /** Halaman About yang sudah dipublikasikan, dengan teks visi kecuali ditimpa. */
    private function publishedPage(?string $vision = 'Kami percaya arsitektur lebih dari sekadar wadah.', ?string $mission = null): AboutPage
    {
        $page = AboutPage::current();

        $page = app(SaveAboutDraft::class)->handle($page, [
            'overview' => ['heading' => 'Tentang kami', 'body' => 'Kontraktor.'],
            'vision_mission' => ['vision' => $vision, 'mission' => $mission],
        ], $page->draft_version);

        return app(PublishAbout::class)->handle($page);
    }

    /**
     * Unggah satu foto per keterangan lewat pengelola gambar, lalu isi keterangannya.
     *
     * @param  list<string>  $captions
     */
    private function addVisionPhotos(AboutPage $page, array $captions): void
    {
        $manager = Livewire::test(ProjectMediaManager::class, [
            'projectId' => $page->id,
            'role' => AboutPage::ROLE_GALLERY,
            'kind' => 'about',
        ]);

        // Lebar berbeda agar isi (dan hash) tiap foto berbeda.
        $manager->set('uploads', array_map(
            fn (int $i): UploadedFile => UploadedFile::fake()->image("foto-{$i}.jpg", 800 + $i * 10, 600),
            array_keys($captions),
        ));

        foreach ($page->usages(draft: true, role: AboutPage::ROLE_GALLERY) as $i => $usage) {
            $manager->call('updateMeta', $usage->id, 'caption', $captions[$i]);
        }
    }

    public function test_editor_offers_a_photo_manager_under_vision_and_mission(): void
    {
        Livewire::test(ManageAboutUs::class)
            ->assertSeeLivewire(ProjectMediaManager::class);
    }

    public function test_uploading_creates_ordered_draft_photos_with_captions(): void
    {
        $page = AboutPage::current();

        $this->addVisionPhotos($page, ['Tropical Future', 'Expression of Life']);

        $draft = $page->usages(draft: true, role: AboutPage::ROLE_GALLERY);

        $this->assertSame(['Tropical Future', 'Expression of Life'], $draft->pluck('caption')->all());
        $this->assertSame([MediaStatus::Ready, MediaStatus::Ready], $draft->map(fn ($u) => $u->file->status)->all());
        $this->assertCount(0, $page->usages(draft: false), 'Belum dipublikasikan.');
    }

    public function test_photos_stay_off_the_website_until_publish_then_show_in_order(): void
    {
        $page = $this->publishedPage();

        $this->addVisionPhotos($page, ['First caption', 'Second caption']);

        $this->assertSame(PageStatus::UnpublishedChanges, $page->fresh()->status);
        $this->get('/about')
            ->assertOk()
            ->assertDontSee('First caption');

        app(PublishAbout::class)->handle($page->fresh());

        $this->get('/about')
            ->assertOk()
            ->assertSeeInOrder(['First caption', 'Second caption']);
    }

    public function test_admin_preview_shows_draft_photos_while_the_website_does_not(): void
    {
        $page = $this->publishedPage();

        $this->addVisionPhotos($page, ['Draft only caption']);

        $this->get('/preview/about')
            ->assertOk()
            ->assertSee('Draft only caption');
        $this->get('/about')
            ->assertOk()
            ->assertDontSee('Draft only caption');
    }

    public function test_section_shows_with_photos_alone_labelled_vision(): void
    {
        $page = $this->publishedPage(vision: null, mission: null);
        $this->addVisionPhotos($page, ['Solo caption']);
        app(PublishAbout::class)->handle($page->fresh());

        $this->get('/about')
            ->assertOk()
            ->assertSee('Solo caption')
            ->assertSee('Vision')
            ->assertDontSee('Mission');
    }

    public function test_section_is_absent_without_vision_text_mission_text_or_photos(): void
    {
        $this->publishedPage(vision: null, mission: null);

        $this->get('/about')
            ->assertOk()
            ->assertDontSee('Vision')
            ->assertDontSee('Mission');
    }

    public function test_photos_that_are_not_ready_are_left_out_without_breaking_the_page(): void
    {
        $page = $this->publishedPage();
        $this->addVisionPhotos($page, ['Ready caption', 'Broken caption']);
        app(PublishAbout::class)->handle($page->fresh());

        MediaFile::query()->orderByDesc('id')->firstOrFail()->forceFill([
            'status' => MediaStatus::Failed,
            'variants' => null,
        ])->save();

        $this->get('/about')
            ->assertOk()
            ->assertSee('Ready caption')
            ->assertDontSee('Broken caption');
    }

    public function test_caption_is_escaped_on_the_website(): void
    {
        $page = $this->publishedPage();
        $this->addVisionPhotos($page, ["<script>alert('x')</script>"]);
        app(PublishAbout::class)->handle($page->fresh());

        $this->get('/about')
            ->assertOk()
            ->assertDontSee("<script>alert('x')</script>", false)
            ->assertSee('&lt;script&gt;', false);
    }
}
