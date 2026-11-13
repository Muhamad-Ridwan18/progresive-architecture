<?php

namespace Tests\Feature;

use App\Actions\Projects\CreateProject;
use App\Actions\Projects\PublishProject;
use App\Actions\Projects\SaveProjectDraft;
use App\Enums\ApplicationStatus;
use App\Enums\ArticleStatus;
use App\Enums\CategoryType;
use App\Filament\Widgets\AttentionOverview;
use App\Filament\Widgets\ContentChart;
use App\Filament\Widgets\ContentStatus;
use App\Filament\Widgets\DashboardStats;
use App\Filament\Widgets\QuickActions;
use App\Filament\Widgets\RecentActivities;
use App\Filament\Widgets\RecentApplications;
use App\Filament\Widgets\RecentArticles;
use App\Filament\Widgets\RecentProjects;
use App\Filament\Widgets\VacancyDeadlines;
use App\Models\Article;
use App\Models\Category;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\Project;
use App\Models\User;
use App\Services\DashboardAttention;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SearchAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function liveProject(string $name, array $fields = []): Project
    {
        $category = Category::query()->firstOrCreate(['type' => CategoryType::Project->value, 'slug' => 'komersial'], ['name' => 'Komersial']);
        $project = app(CreateProject::class)->handle(['name' => $name, 'category_id' => $category->id]);
        $project = app(SaveProjectDraft::class)->handle($project, $fields, $project->draft_version);

        return app(PublishProject::class)->handle($project);
    }

    private function admin(): User
    {
        Filament::setCurrentPanel('admin');

        return tap(User::factory()->create(), fn (User $user) => $this->actingAs($user));
    }

    // ---- Pencarian publik -----------------------------------------------------------------

    public function test_search_groups_results_by_content_type(): void
    {
        $this->liveProject('Kampus Terpadu Surabaya');
        Article::factory()->create(['title' => 'Pelajaran dari proyek kampus']);
        JobVacancy::factory()->create(['title' => 'Site Engineer Proyek Kampus']);

        $response = $this->get('/search?q=kampus')->assertOk();

        $response->assertSee('Projects')->assertSee('News & Articles')->assertSee('Careers')
            ->assertSee('Kampus Terpadu Surabaya')
            ->assertSee('Pelajaran dari proyek kampus')
            ->assertSee('Site Engineer Proyek Kampus');
    }

    public function test_search_only_returns_content_visible_on_the_website(): void
    {
        $draft = app(CreateProject::class)->handle([
            'name' => 'Kampus Draft',
            'category_id' => Category::create(['type' => CategoryType::Project, 'name' => 'Publik'])->id,
        ]);
        Article::factory()->draft()->create(['title' => 'Kampus artikel draft']);
        Article::factory()->scheduledAt(now()->addDay())->create(['title' => 'Kampus artikel jadwal']);
        JobVacancy::factory()->draft()->create(['title' => 'Kampus lowongan draft']);
        JobVacancy::factory()->closed()->create(['title' => 'Kampus lowongan tutup']);

        $this->assertNotNull($draft);

        $this->get('/search?q=kampus')->assertOk()
            ->assertDontSee('Kampus Draft')
            ->assertDontSee('Kampus artikel draft')
            ->assertDontSee('Kampus artikel jadwal')
            ->assertDontSee('Kampus lowongan draft')
            ->assertSee('Kampus lowongan tutup');
    }

    public function test_search_never_shows_unpublished_edits_of_a_live_project(): void
    {
        $project = $this->liveProject('Menara Lama');
        app(SaveProjectDraft::class)->handle($project, ['name' => 'Menara Rahasia Baru'], $project->draft_version);

        $this->get('/search?q=Menara')->assertSee('Menara Lama')->assertDontSee('Menara Rahasia Baru');
    }

    public function test_search_matches_excerpt_and_location_but_treats_like_wildcards_literally(): void
    {
        $this->liveProject('Gedung A', ['location' => 'Semarang']);
        Article::factory()->create(['title' => 'Judul biasa', 'excerpt' => 'Ringkasan tentang fasad modular']);

        $this->get('/search?q=semarang')->assertSee('Gedung A');
        $this->get('/search?q=fasad')->assertSee('Judul biasa');

        // "%" dari pengguna adalah teks biasa, bukan pencocok semua.
        $this->get('/search?q=%25%25')->assertSee('Tidak ada hasil');
    }

    public function test_short_queries_return_nothing_and_ask_for_more_letters(): void
    {
        $this->liveProject('Gedung A');

        $this->get('/search?q=g')->assertOk()->assertSee('minimal 2 huruf')->assertDontSee('Gedung A');
        $this->get('/search')->assertOk()->assertSee('mulai mencari');
    }

    public function test_type_filter_limits_the_full_results_page(): void
    {
        $this->liveProject('Kampus Terpadu');
        Article::factory()->create(['title' => 'Artikel kampus']);

        $this->get('/search?q=kampus&type=articles')->assertSee('Artikel kampus')->assertDontSee('Kampus Terpadu');
    }

    public function test_live_endpoint_returns_a_fragment_and_escapes_the_query(): void
    {
        $this->liveProject('Kampus Terpadu');

        $this->get('/search/live?q=kampus')->assertOk()->assertSee('Kampus Terpadu')->assertDontSee('<html', false);

        $this->get('/search/live?q='.urlencode('<script>alert(1)</script>'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_search_page_is_not_indexed_and_the_search_link_is_in_the_navbar(): void
    {
        $this->get('/search?q=kampus')->assertHeader('X-Robots-Tag', 'noindex, follow');
        $this->get('/')->assertSee('id="search-toggle"', false)->assertSee('id="search-overlay"', false);
    }

    public function test_search_is_a_small_dropdown_panel_with_compact_live_results(): void
    {
        $this->liveProject('Kampus Terpadu');

        // Panel kecil di kanan atas (bukan layar penuh): tidak ada kelas inset-0 pada panel, dan hasil live ringkas.
        $this->get('/')->assertOk()
            ->assertDontSee('Global Search')
            ->assertSee('w-[min(28rem', false);

        $this->get('/search/live?q=kampus')->assertOk()->assertSee('py-3', false)->assertDontSee('text-h3', false);
        $this->get('/search?q=kampus')->assertOk()->assertSee('text-h3', false);
    }

    public function test_search_is_rate_limited(): void
    {
        foreach (range(1, 60) as $i) {
            $this->get('/search/live?q=kampus')->assertOk();
        }

        $this->get('/search/live?q=kampus')->assertStatus(429);
    }

    // ---- Dashboard ------------------------------------------------------------------------

    public function test_dashboard_stats_show_the_four_metrics(): void
    {
        $this->admin();
        $this->liveProject('Proyek Satu');
        Article::factory()->create();
        Article::factory()->draft()->create();
        JobVacancy::factory()->create();
        JobVacancy::factory()->draft()->create();
        JobVacancy::factory()->create(['deadline' => today()->subDay()]);
        JobApplication::factory()->count(3)->create();

        Livewire::test(DashboardStats::class)
            ->assertSee('Total Projects')->assertSee('Published Articles')
            ->assertSee('Active Job Vacancies')->assertSee('Total Applications')
            ->assertSeeInOrder(['Total Projects', '1', 'Published Articles', '1', 'Active Job Vacancies']);
    }

    public function test_active_vacancies_count_excludes_drafts_closed_and_expired(): void
    {
        $this->admin();
        JobVacancy::factory()->create(['title' => 'Aktif A']);
        JobVacancy::factory()->create(['title' => 'Aktif Tanpa Deadline', 'deadline' => null]);
        JobVacancy::factory()->draft()->create();
        JobVacancy::factory()->closed()->create();
        JobVacancy::factory()->expired()->create();

        $component = Livewire::test(DashboardStats::class);
        $stats = (new \ReflectionMethod(DashboardStats::class, 'getStats'))->invoke($component->instance());

        $this->assertSame(2, (new \ReflectionProperty($stats[2], 'value'))->getValue($stats[2]));
    }

    public function test_recent_applications_lists_the_five_newest_with_links_to_detail(): void
    {
        $this->admin();
        $newest = JobApplication::factory()->create(['applicant_name' => 'Pelamar Terbaru', 'submitted_at' => now(), 'status' => ApplicationStatus::Reviewing]);
        JobApplication::factory()->count(5)->create(['submitted_at' => now()->subDay()]);
        $oldest = JobApplication::factory()->create(['applicant_name' => 'Pelamar Terlama', 'submitted_at' => now()->subYear()]);

        Livewire::test(RecentApplications::class)
            ->assertSee('Pelamar Terbaru')
            ->assertDontSee('Pelamar Terlama')
            ->assertSeeHtml('/admin/job-applications/'.$newest->getKey());

        $this->assertNotNull($oldest);
    }

    public function test_recent_activities_show_who_did_what_to_which_object(): void
    {
        $admin = $this->admin();
        $project = app(CreateProject::class)->handle([
            'name' => 'Proyek Dicatat',
            'category_id' => Category::create(['type' => CategoryType::Project, 'name' => 'Publik'])->id,
        ], $admin->id);

        activity('project')->performedOn($project)->causedBy($admin)->log('Project published');

        Livewire::test(RecentActivities::class)
            ->assertSee($admin->name)
            ->assertSee('Project published')
            ->assertSee('Project: Proyek Dicatat');
    }

    public function test_dashboard_page_renders_all_widgets(): void
    {
        $this->admin();

        // Widget dimuat malas, jadi yang diperiksa adalah komponennya terpasang di halaman.
        $this->get('/admin')->assertOk()
            ->assertSeeLivewire(DashboardStats::class)
            ->assertSeeLivewire(RecentActivities::class)
            ->assertSeeLivewire(RecentApplications::class);
    }

    public function test_dashboard_is_a_greeting_plus_action_centre_focused_on_content(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Hilmi Haerul Umam']));

        $this->get('/admin')->assertOk()
            ->assertSee('Hilmi')
            ->assertSee('Lihat website')
            ->assertSee('New article')
            ->assertSeeLivewire(AttentionOverview::class)
            ->assertSeeLivewire(ContentChart::class)
            ->assertSeeLivewire(RecentProjects::class)
            ->assertSeeLivewire(RecentArticles::class)
            ->assertSeeLivewire(ContentStatus::class)
            ->assertSeeLivewire(QuickActions::class)
            ->assertSeeLivewire(VacancyDeadlines::class)
            ->assertSeeLivewire(RecentApplications::class);
    }

    public function test_attention_cards_put_content_first_and_recruitment_last(): void
    {
        $this->liveProject('Proyek Berubah', ['description' => 'Awal']);
        $project = Project::query()->firstOrFail();
        app(SaveProjectDraft::class)->handle($project, ['description' => 'Diubah'], $project->draft_version);

        Article::factory()->draft()->create();
        Article::factory()->create(['status' => ArticleStatus::Scheduled, 'scheduled_at' => now()->addDays(2)]);
        Article::factory()->create(['status' => ArticleStatus::Scheduled, 'scheduled_at' => now()->addDays(30)]);

        JobApplication::factory()->create(['status' => ApplicationStatus::Submitted]);
        JobApplication::factory()->create(['status' => ApplicationStatus::Accepted]);

        $items = DashboardAttention::all();

        $this->assertSame(
            ['proyek berubah, belum di-publish', 'artikel draft atau berubah, belum tayang', 'artikel terjadwal minggu ini', 'lamaran menunggu ditinjau'],
            array_column($items, 'label'),
        );
        $this->assertSame([1, 1, 1, 1], array_column($items, 'count'));
        $this->assertCount(4, DashboardAttention::pending());
    }

    public function test_recent_projects_and_articles_list_the_newest_with_links_to_the_editors(): void
    {
        $this->admin();
        $project = $this->liveProject('Rumah Terbaru');
        $article = Article::factory()->create(['title' => 'Artikel Terbaru']);

        Livewire::test(RecentProjects::class)->assertSee('Rumah Terbaru')->assertSeeHtml('/admin/projects/'.$project->getRouteKey().'/edit');
        Livewire::test(RecentArticles::class)->assertSee('Artikel Terbaru')->assertSeeHtml('/admin/articles/'.$article->getRouteKey().'/edit');
    }

    public function test_content_chart_counts_publications_per_month_for_six_months(): void
    {
        $this->admin();
        Article::factory()->create(['published_at' => now()]);
        Article::factory()->create(['published_at' => now()->subMonths(2)]);
        Article::factory()->create(['published_at' => now()->subMonths(9)]);

        $component = Livewire::test(ContentChart::class);
        $data = (new \ReflectionMethod(ContentChart::class, 'getData'))->invoke($component->instance());

        $this->assertCount(6, $data['labels']);
        $this->assertSame(1, $data['datasets'][1]['data'][5]);
        $this->assertSame(1, $data['datasets'][1]['data'][3]);
        $this->assertSame(2, array_sum($data['datasets'][1]['data']));
    }

    public function test_vacancy_deadlines_show_days_left(): void
    {
        $this->admin();
        JobVacancy::factory()->create(['title' => 'Supervisor Lapangan', 'deadline' => today()->addDays(5)]);

        Livewire::test(VacancyDeadlines::class)->assertSee('Supervisor Lapangan')->assertSee('5 hari lagi');
    }

    // ---- Pencarian dashboard --------------------------------------------------------------

    public function test_dashboard_global_search_covers_projects_articles_categories_jobs_applicants_and_users(): void
    {
        $this->admin();
        $this->liveProject('Beton Proyek');
        Article::factory()->create(['title' => 'Beton artikel']);
        Category::create(['type' => CategoryType::Article, 'name' => 'Beton kategori']);
        JobVacancy::factory()->create(['title' => 'Beton lowongan']);
        JobApplication::factory()->create(['applicant_name' => 'Beton pelamar']);
        User::factory()->create(['name' => 'Beton pengguna']);

        $categories = Filament::getGlobalSearchProvider()->getResults('Beton')->getCategories();
        $titles = collect($categories)->flatten()->map(fn ($result) => $result->title)->all();

        foreach (['Beton Proyek', 'Beton artikel', 'Beton kategori', 'Beton lowongan', 'Beton pelamar', 'Beton pengguna'] as $expected) {
            $this->assertContains($expected, $titles);
        }

        // Hasil dikelompokkan per jenis konten (satu grup per resource).
        $this->assertGreaterThanOrEqual(6, count($categories));
    }

    public function test_dashboard_global_search_finds_applicants_by_email_and_reference(): void
    {
        $this->admin();
        $application = JobApplication::factory()->create(['applicant_name' => 'Nama Lain', 'applicant_email' => 'unik-cari@example.com']);

        $titles = collect(Filament::getGlobalSearchProvider()->getResults('unik-cari@example.com')->getCategories())->flatten()->map(fn ($r) => $r->title);
        $this->assertContains('Nama Lain', $titles->all());

        $byReference = collect(Filament::getGlobalSearchProvider()->getResults((string) $application->reference_number)->getCategories())->flatten()->map(fn ($r) => $r->title);
        $this->assertContains('Nama Lain', $byReference->all());
    }
}
