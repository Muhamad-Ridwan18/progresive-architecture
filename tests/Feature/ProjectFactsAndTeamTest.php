<?php

namespace Tests\Feature;

use App\Actions\About\PublishAbout;
use App\Actions\About\SaveAboutDraft;
use App\Actions\Projects\CreateProject;
use App\Actions\Projects\PublishProject;
use App\Actions\Projects\SaveProjectDraft;
use App\Enums\CategoryType;
use App\Enums\ProjectStatus;
use App\Models\AboutPage;
use App\Models\Article;
use App\Models\Category;
use App\Models\Project;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Data teknis proyek (company profile), bagian Team di About, menu, dan footer yang mengikuti mockup. */
class ProjectFactsAndTeamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function project(array $fields = [], bool $publish = true): Project
    {
        $category = Category::query()->firstOrCreate(['type' => CategoryType::Project->value, 'slug' => 'architecture'], ['name' => 'Architecture']);
        $project = app(CreateProject::class)->handle(['name' => 'AD House', 'category_id' => $category->id]);
        $project = app(SaveProjectDraft::class)->handle($project, $fields, $project->draft_version);

        return $publish ? app(PublishProject::class)->handle($project) : $project;
    }

    // ---- Data teknis proyek ---------------------------------------------------------------

    public function test_technical_facts_are_shown_on_the_project_page(): void
    {
        $project = $this->project([
            'location' => 'Bekasi, Indonesia', 'year' => 2025, 'client' => 'Private Client', 'typology' => 'Residential',
            'land_area' => 129.7, 'building_area' => 175.8, 'storeys' => 2, 'stage' => 'Complete',
        ]);

        $this->get(route('projects.show', $project->slug))->assertOk()
            ->assertSeeInOrder(['Client', 'Private Client', 'Typology', 'Residential', 'Land area', '129,7 m²', 'Building area', '175,8 m²', 'Storey', '2 floors', 'Status', 'Complete']);
    }

    public function test_empty_facts_are_not_shown_and_whole_numbers_drop_the_decimal(): void
    {
        $project = $this->project(['land_area' => 98, 'storeys' => 1]);

        $this->get(route('projects.show', $project->slug))->assertOk()
            ->assertSee('98 m²')
            ->assertSee('1 floor')
            ->assertDontSee('Client')
            ->assertDontSee('Building area');
    }

    public function test_editing_a_fact_follows_the_draft_and_publish_flow(): void
    {
        $project = $this->project(['land_area' => 100, 'stage' => 'On going']);

        $project = app(SaveProjectDraft::class)->handle($project, ['stage' => 'Complete'], $project->draft_version);

        $this->assertSame(ProjectStatus::UnpublishedChanges, $project->status);
        $this->get(route('projects.show', $project->slug))->assertSee('On going')->assertDontSee('Complete');

        // Dikembalikan seperti semula: status kembali ke Published (fakta ikut dibandingkan).
        $project = app(SaveProjectDraft::class)->handle($project, ['stage' => 'On going'], $project->draft_version);
        $this->assertSame(ProjectStatus::Published, $project->status);

        app(SaveProjectDraft::class)->handle($project, ['stage' => 'Complete'], $project->draft_version);
        app(PublishProject::class)->handle($project->refresh());

        $this->get(route('projects.show', $project->slug))->assertSee('Complete');
    }

    public function test_areas_and_storeys_are_validated(): void
    {
        $project = $this->project([], publish: false);

        foreach ([['land_area' => -5], ['building_area' => 'luas'], ['storeys' => 0], ['storeys' => 500]] as $invalid) {
            try {
                app(SaveProjectDraft::class)->handle($project->refresh(), $invalid, $project->draft_version);
                $this->fail('Nilai tidak valid seharusnya ditolak: '.json_encode($invalid));
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_project_page_has_entrance_motion_hooks_and_navigation_links_for_feedback(): void
    {
        $first = $this->project(['year' => 2024]);
        $second = app(PublishProject::class)->handle(
            app(SaveProjectDraft::class)->handle(
                $created = app(CreateProject::class)->handle(['name' => 'Second House', 'category_id' => $first->category_id]),
                [],
                $created->draft_version,
            )
        );

        $html = $this->get(route('projects.show', $second->slug))->assertOk()->getContent();

        $this->assertStringContainsString('data-enter', $html);
        $this->assertStringContainsString('data-project-nav', $html);

        $this->get('/')->assertOk()->assertSee('data-project-link', false)->assertSee('data-cover', false);
    }

    // ---- Bagian Team di About ---------------------------------------------------------------------

    private function publishTeam(array $team): void
    {
        $page = AboutPage::current();
        $page = app(SaveAboutDraft::class)->handle($page, ['overview' => ['heading' => 'About', 'body' => 'Studio.'], 'team' => $team], $page->draft_version);
        app(PublishAbout::class)->handle($page);
    }

    public function test_team_page_lists_members_with_position_and_full_bio(): void
    {
        $bio = str_repeat('Biografi panjang pendiri. ', 80);

        $this->publishTeam([
            ['id' => 'hilmi0000001', 'name' => 'Hilmi Haerul Umam', 'position' => 'Founder', 'bio' => $bio],
            ['id' => 'nisa00000002', 'name' => 'Nisa Yasyfa Faridan', 'position' => 'Architect', 'bio' => null],
        ]);

        $this->get('/about')->assertOk()
            ->assertSee('Hilmi Haerul Umam')->assertSee('Founder')
            ->assertSee('Nisa Yasyfa Faridan')->assertSee('Architect')
            ->assertSee(trim($bio));
    }

    public function test_team_section_only_appears_on_about_when_there_are_members(): void
    {
        $this->publishTeam([]);
        $this->get('/about')->assertOk()->assertDontSee('id="team"', false);

        $this->publishTeam([['id' => 'siti00000001', 'name' => 'Siti', 'position' => 'Architect']]);
        $this->get('/about')->assertOk()->assertSee('id="team"', false)->assertSee('Siti');
    }

    public function test_old_team_url_redirects_to_the_team_section_of_about(): void
    {
        $this->get('/team')->assertStatus(301)->assertRedirect(route('about').'#team');
    }

    public function test_draft_team_changes_are_not_on_the_team_page_before_publish(): void
    {
        $this->publishTeam([['id' => 'siti00000001', 'name' => 'Siti Lama', 'position' => 'Architect']]);

        $page = AboutPage::current();
        app(SaveAboutDraft::class)->handle($page, ['overview' => ['heading' => 'About', 'body' => 'Studio.'], 'team' => [['id' => 'siti00000001', 'name' => 'Siti Baru', 'position' => 'Architect']]], $page->draft_version);

        $this->get('/about')->assertSee('Siti Lama')->assertDontSee('Siti Baru');
    }

    public function test_team_starts_as_cards_only_with_every_profile_in_the_page_ready_to_open(): void
    {
        $this->publishTeam([
            ['id' => 'hilmi0000001', 'name' => 'Hilmi', 'position' => 'Founder', 'bio' => 'Bio Hilmi'],
            ['id' => 'nisa00000002', 'name' => 'Nisa', 'position' => 'Architect', 'bio' => 'Bio Nisa'],
            ['id' => 'aldy00000003', 'name' => 'Aldy', 'position' => 'Architect', 'bio' => 'Bio Aldy'],
        ]);

        $html = $this->get('/about')->assertOk()->getContent();

        // Awalnya tidak ada profil terbuka: laci tertutup, tiga profil tersembunyi, tiga kartu tampil.
        $this->assertStringContainsString('data-open="false"', $html);
        $this->assertSame(3, substr_count($html, 'data-profile='));
        $this->assertSame(3, preg_match_all('/data-profile="[a-z0-9]+"\s+hidden/', $html));
        $this->assertSame(3, substr_count($html, 'data-member-card='));
        $this->assertSame(0, preg_match_all('/data-member-card="[a-z0-9]+"\s+hidden/', $html));
        $this->assertStringContainsString('?member=nisa00000002#profile', $html);

        // Isi profil sudah ada di HTML supaya bisa dibuka tanpa memuat ulang.
        $this->assertStringContainsString('Bio Nisa', $html);
        $this->assertStringContainsString('Bio Aldy', $html);
    }

    public function test_member_query_opens_that_profile_and_unknown_ids_leave_everything_closed(): void
    {
        $this->publishTeam([
            ['id' => 'hilmi0000001', 'name' => 'Hilmi', 'position' => 'Founder'],
            ['id' => 'nisa00000002', 'name' => 'Nisa', 'position' => 'Architect'],
        ]);

        $selected = $this->get('/about?member=nisa00000002')->getContent();
        $this->assertStringContainsString('data-open="true"', $selected);
        $this->assertSame(1, preg_match_all('/data-profile="hilmi0000001"\s+hidden/', $selected));
        $this->assertSame(0, preg_match_all('/data-profile="nisa00000002"\s+hidden/', $selected));
        $this->assertStringContainsString('aria-current="true"', $selected);

        $unknown = $this->get('/about?member=tidak-ada')->getContent();
        $this->assertStringContainsString('data-open="false"', $unknown);
        $this->assertSame(2, preg_match_all('/data-profile="[a-z0-9]+"\s+hidden/', $unknown));
    }

    public function test_bio_longer_than_a_thousand_characters_is_accepted(): void
    {
        $this->publishTeam([['id' => 'hilmi0000001', 'name' => 'Hilmi', 'position' => 'Founder', 'bio' => str_repeat('a', 3500)]]);

        $this->assertSame(3500, strlen(AboutPage::current()->publishedContent()['team'][0]['bio']));
    }

    // ---- Menu dan footer ------------------------------------------------------------------

    public function test_menu_follows_the_company_profile_and_careers_lives_under_news(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $menu = substr($html, strpos($html, 'id="site-menu"'), strpos($html, '</nav>', strpos($html, 'id="site-menu"')) - strpos($html, 'id="site-menu"'));

        foreach (['Home', 'About', 'News', 'Contact'] as $item) {
            $this->assertStringContainsString($item, $menu);
        }

        $this->assertStringNotContainsString('Careers', $menu);
        $this->assertStringNotContainsString('/team', $menu, 'Team ada di dalam About, bukan item menu sendiri.');
        $this->assertStringContainsString('aria-current="page"', $this->get('/careers')->getContent(), 'Careers menandai News sebagai menu aktif.');

        $category = Category::create(['type' => CategoryType::Article, 'name' => 'Insights']);
        Article::factory()->create(['category_id' => $category->id]);

        $this->get('/news')->assertSee(route('careers'), false);
    }

    public function test_footer_shows_location_email_and_phone_from_settings_and_a_two_line_wordmark(): void
    {
        Settings::setMany([
            'site_name' => 'Progressive Architect',
            'company_address' => 'Suwarna Mas Residence No.15',
            'contact_email' => 'studio@example.test',
            'contact_phone' => '+62 821-1743-8719',
        ]);

        $this->get('/')->assertOk()
            ->assertSee('Location')->assertSee('Suwarna Mas Residence No.15')
            ->assertSee('studio@example.test')->assertSee('General information')->assertSee('+62 821-1743-8719')
            ->assertSee('Back to top')
            ->assertSeeInOrder(['Progressive', '<br>', 'Architect'], false);
    }
}
