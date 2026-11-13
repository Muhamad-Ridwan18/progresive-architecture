<?php

namespace Tests\Feature;

use App\Actions\About\DiscardAboutDraft;
use App\Actions\About\PublishAbout;
use App\Actions\About\SaveAboutDraft;
use App\Enums\MediaStatus;
use App\Enums\PageStatus;
use App\Livewire\ProjectMediaManager;
use App\Models\AboutPage;
use App\Models\MediaUsage;
use App\Models\User;
use App\Support\AboutContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Foto anggota tim About Us: id stabil per anggota, foto berversi draft/published (SRS 3.5, 5.4, 12). */
class AboutTeamPhotoTest extends TestCase
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

    /** @param list<array<string, mixed>> $team */
    private function pageWithTeam(array $team): AboutPage
    {
        $page = AboutPage::current();

        return app(SaveAboutDraft::class)->handle($page, [
            'overview' => ['heading' => 'Tentang kami', 'body' => 'Kontraktor.'],
            'team' => $team,
        ], $page->draft_version);
    }

    private function member(AboutPage $page, int $index = 0): array
    {
        return $page->draftContent()['team'][$index];
    }

    private function upload(AboutPage $page, string $memberId, string $name = 'foto.jpg', int $width = 800): void
    {
        Livewire::test(ProjectMediaManager::class, [
            'projectId' => $page->id,
            'role' => AboutPage::teamRole($memberId),
            'kind' => 'about',
        ])->set('uploads', [UploadedFile::fake()->image($name, $width, 1000)]);
    }

    private function usages(AboutPage $page, bool $draft)
    {
        return $page->mediaUsages()->where('is_draft', $draft)->where('role', 'like', 'team:%')->get();
    }

    // ---- Id anggota -----------------------------------------------------------------------

    public function test_every_team_member_gets_a_stable_id_and_blank_members_are_dropped(): void
    {
        $content = AboutContent::normalize(['team' => [
            ['id' => 'abc123def456', 'name' => 'Siti'],
            ['name' => 'Budi'],
            ['id' => 'zzzzzzzzzzzz'],
        ]]);

        $this->assertCount(2, $content['team']);
        $this->assertSame('abc123def456', $content['team'][0]['id']);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{6,20}$/', $content['team'][1]['id']);

        // Anggota lama tanpa id mendapat id deterministik: bentuk baku sama setiap kali dinormalkan.
        $again = AboutContent::normalize(['team' => [['name' => 'Siti'], ['name' => 'Budi']]]);
        $this->assertSame($again, AboutContent::normalize(['team' => [['name' => 'Siti'], ['name' => 'Budi']]]));
    }

    public function test_member_id_must_be_a_safe_short_token_or_it_is_replaced(): void
    {
        $content = AboutContent::validated(['team' => [['id' => 'Bad Id!', 'name' => 'Siti']]]);

        $this->assertMatchesRegularExpression('/^[a-z0-9]{6,20}$/', $content['team'][0]['id']);
    }

    // ---- Unggah dan versi -----------------------------------------------------------------

    public function test_uploading_a_photo_creates_a_draft_usage_for_that_member_only(): void
    {
        $page = $this->pageWithTeam([['id' => 'siti00000001', 'name' => 'Siti'], ['id' => 'budi00000002', 'name' => 'Budi']]);

        $this->upload($page, 'siti00000001');

        $usages = $this->usages($page, true);
        $this->assertCount(1, $usages);
        $this->assertSame('team:siti00000001', $usages->first()->role);
        $this->assertTrue((bool) $usages->first()->is_draft);
        $this->assertSame(MediaStatus::Ready, $usages->first()->file->status);
        $this->assertCount(0, $this->usages($page, false));
    }

    public function test_replacing_a_photo_keeps_a_single_one_per_member(): void
    {
        $page = $this->pageWithTeam([['id' => 'siti00000001', 'name' => 'Siti']]);

        $this->upload($page, 'siti00000001', 'satu.jpg');
        $this->upload($page, 'siti00000001', 'dua.jpg', 900);

        $this->assertCount(1, $this->usages($page, true));
    }

    public function test_photo_is_hidden_from_the_website_until_publish_and_shown_after(): void
    {
        $page = $this->pageWithTeam([['id' => 'siti00000001', 'name' => 'Siti Rahma']]);
        $this->upload($page, 'siti00000001');

        app(PublishAbout::class)->handle($page->refresh());
        $published = $this->get('/about')->assertOk();
        $published->assertSee('Siti Rahma')->assertSee('srcset', false);

        // Foto baru di draft tidak tampil sebelum dipublikasikan.
        $page->refresh();
        $firstFile = $this->usages($page, false)->first()->media_file_id;
        $this->upload($page, 'siti00000001', 'baru.jpg', 900);

        $this->assertSame(PageStatus::UnpublishedChanges, $page->refresh()->status);
        $this->assertSame($firstFile, $this->usages($page, false)->first()->media_file_id);

        app(PublishAbout::class)->handle($page);
        $this->assertNotSame($firstFile, $this->usages($page->refresh(), false)->first()->media_file_id);
        $this->assertSame(PageStatus::Published, $page->status);
    }

    public function test_a_photo_that_was_never_published_is_not_on_the_website(): void
    {
        $page = $this->pageWithTeam([['id' => 'siti00000001', 'name' => 'Siti Rahma']]);
        app(PublishAbout::class)->handle($page);

        $page->refresh();
        $this->upload($page, 'siti00000001');

        $this->get('/about')->assertOk()->assertSee('Siti Rahma')->assertDontSee('srcset', false);
    }

    public function test_preview_shows_draft_photos_to_admins(): void
    {
        $page = $this->pageWithTeam([['id' => 'siti00000001', 'name' => 'Siti Rahma']]);
        $this->upload($page, 'siti00000001');

        $this->get('/preview/about')->assertOk()->assertSee('srcset', false);
    }

    public function test_discard_restores_published_photos(): void
    {
        $page = $this->pageWithTeam([['id' => 'siti00000001', 'name' => 'Siti']]);
        $this->upload($page, 'siti00000001', 'asli.jpg');
        $page = app(PublishAbout::class)->handle($page->refresh());
        $publishedFile = $this->usages($page, false)->first()->media_file_id;

        $this->upload($page, 'siti00000001', 'ganti.jpg', 900);
        $this->assertNotSame($publishedFile, $this->usages($page, true)->first()->media_file_id);

        app(DiscardAboutDraft::class)->handle($page->refresh());

        $this->assertSame($publishedFile, $this->usages($page, true)->first()->media_file_id);
        $this->assertSame(PageStatus::Published, $page->refresh()->status);
    }

    // ---- Anggota dihapus / belum bernama --------------------------------------------------

    public function test_removing_a_member_removes_their_draft_photo(): void
    {
        $page = $this->pageWithTeam([['id' => 'siti00000001', 'name' => 'Siti'], ['id' => 'budi00000002', 'name' => 'Budi']]);
        $this->upload($page, 'siti00000001');
        $this->upload($page, 'budi00000002');

        $this->pageWithTeam([['id' => 'budi00000002', 'name' => 'Budi']]);

        $remaining = $this->usages($page, true);
        $this->assertCount(1, $remaining);
        $this->assertSame('team:budi00000002', $remaining->first()->role);
    }

    public function test_photo_of_a_member_whose_name_is_not_typed_yet_survives_autosave(): void
    {
        $page = $this->pageWithTeam([['id' => 'siti00000001', 'name' => 'Siti']]);
        $this->upload($page, 'baru00000003');

        // Anggota baru (belum bernama) ikut terkirim di state formulir; foto yang baru diunggah tidak boleh hilang.
        $this->pageWithTeam([['id' => 'siti00000001', 'name' => 'Siti'], ['id' => 'baru00000003', 'name' => '']]);

        $this->assertCount(1, $this->usages($page, true));
    }

    // ---- Keamanan dan batas ---------------------------------------------------------------

    public function test_manager_ignores_an_invalid_team_role(): void
    {
        $page = $this->pageWithTeam([['id' => 'siti00000001', 'name' => 'Siti']]);

        // Peran tidak valid jatuh ke galeri (bukan foto tim).
        Livewire::test(ProjectMediaManager::class, ['projectId' => $page->id, 'role' => 'team:../../x', 'kind' => 'about'])
            ->assertSet('role', 'gallery');
    }

    public function test_publish_is_blocked_while_a_photo_is_still_processing(): void
    {
        $page = $this->pageWithTeam([['id' => 'siti00000001', 'name' => 'Siti']]);
        $this->upload($page, 'siti00000001');

        MediaUsage::query()->where('usable_type', AboutPage::class)->first()->file->forceFill(['status' => MediaStatus::Processing])->save();

        $this->expectException(ValidationException::class);

        app(PublishAbout::class)->handle($page->refresh());
    }
}
