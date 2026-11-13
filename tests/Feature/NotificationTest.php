<?php

namespace Tests\Feature;

use App\Actions\Articles\PublishArticle;
use App\Actions\Projects\CreateProject;
use App\Actions\Projects\PublishProject;
use App\Actions\Projects\SaveProjectDraft;
use App\Enums\CategoryType;
use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Models\Article;
use App\Models\Category;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\Project;
use App\Models\User;
use App\Support\ApplicationForm;
use Filament\Facades\Filament;
use Filament\Livewire\DatabaseNotifications;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        $this->admin = User::factory()->create();
    }

    /** @return list<string> judul notifikasi milik pengguna */
    private function titles(User $user): array
    {
        return $user->notifications()->get()->map(fn (DatabaseNotification $n): string => $n->data['title'])->all();
    }

    private function liveProject(): void
    {
        $category = Category::create(['type' => CategoryType::Project, 'name' => 'Komersial']);
        $project = app(CreateProject::class)->handle(['name' => 'Menara Uji', 'category_id' => $category->id]);

        app(PublishProject::class)->handle($project);
    }

    // ---- New Application ------------------------------------------------------------------

    public function test_new_application_notifies_every_active_admin_but_not_inactive_ones(): void
    {
        $other = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);
        $vacancy = JobVacancy::factory()->create(['title' => 'Site Engineer', 'form_fields' => ApplicationForm::defaultFields()]);

        $this->post(route('careers.apply', $vacancy), [
            'answers' => ['full_name' => 'Sinta Rahayu', 'email' => 's@example.com', 'phone' => '0812-0000-0002'],
            'files' => ['cv' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')],
            'consent' => '1',
        ])->assertSessionDoesntHaveErrors();

        foreach ([$this->admin, $other] as $user) {
            $this->assertSame(['New application'], $this->titles($user));
        }

        $this->assertSame([], $this->titles($inactive));

        $data = $this->admin->notifications()->first()->data;
        $this->assertStringContainsString('Sinta Rahayu', $data['body']);
        $this->assertStringContainsString('Site Engineer', $data['body']);
        $this->assertSame(
            JobApplicationResource::getUrl('view', ['record' => JobApplication::query()->firstOrFail()]),
            $data['actions'][0]['url'],
        );
    }

    public function test_rejected_application_sends_no_notification(): void
    {
        $vacancy = JobVacancy::factory()->create(['form_fields' => ApplicationForm::defaultFields()]);

        $this->post(route('careers.apply', $vacancy), [])->assertSessionHasErrors();

        $this->assertSame([], $this->titles($this->admin));
    }

    // ---- Published ------------------------------------------------------------------------

    public function test_publishing_a_project_notifies_and_republishing_says_updated(): void
    {
        $this->liveProject();

        $this->assertSame(['Project published'], $this->titles($this->admin));

        $project = Project::query()->firstOrFail();
        $project = app(SaveProjectDraft::class)->handle($project, ['location' => 'Bandung'], $project->draft_version);
        app(PublishProject::class)->handle($project);

        $this->assertEqualsCanonicalizing(['Project published', 'Project updated'], $this->titles($this->admin));
    }

    public function test_publishing_an_article_notifies_including_when_the_scheduler_does_it(): void
    {
        Article::factory()->scheduledAt(now()->subMinute())->create(['title' => 'Terjadwal']);

        $this->artisan('articles:publish-scheduled')->assertSuccessful();

        $this->assertSame(['Article published'], $this->titles($this->admin));

        $draft = Article::factory()->draft()->create(['title' => 'Langsung']);
        app(PublishArticle::class)->handle($draft);

        $this->assertCount(2, $this->titles($this->admin));
    }

    public function test_failed_publish_sends_no_notification(): void
    {
        $article = Article::factory()->draft()->create();
        $article->forceFill(['draft_data' => [...$article->draftFields(), 'slug' => 'Rusak!']])->save();

        try {
            app(PublishArticle::class)->handle($article->refresh());
        } catch (ValidationException) {
        }

        $this->assertSame([], $this->titles($this->admin));
    }

    // ---- Job deadline ---------------------------------------------------------------------

    public function test_deadline_reminders_go_out_at_three_days_and_on_the_day_once_each(): void
    {
        $threeDays = JobVacancy::factory()->create(['title' => 'Tiga Hari', 'deadline' => today()->addDays(3)]);
        $today = JobVacancy::factory()->create(['title' => 'Hari Ini', 'deadline' => today()]);
        JobVacancy::factory()->create(['title' => 'Jauh', 'deadline' => today()->addDays(10)]);
        JobVacancy::factory()->draft()->create(['title' => 'Draft', 'deadline' => today()->addDays(3)]);
        JobVacancy::factory()->closed()->create(['title' => 'Tutup', 'deadline' => today()]);
        JobVacancy::factory()->create(['title' => 'Tanpa Deadline', 'deadline' => null]);

        $this->artisan('jobs:notify-deadlines')->assertSuccessful();

        $bodies = $this->admin->notifications()->get()->pluck('data.body')->implode(' | ');
        $this->assertStringContainsString('Tiga Hari berakhir 3 hari lagi', $bodies);
        $this->assertStringContainsString('Hari Ini berakhir hari ini', $bodies);
        $this->assertStringNotContainsString('Jauh', $bodies);
        $this->assertStringNotContainsString('Draft', $bodies);
        $this->assertStringNotContainsString('Tutup', $bodies);
        $this->assertStringNotContainsString('Tanpa Deadline', $bodies);
        $this->assertCount(2, $this->titles($this->admin));

        // Dijalankan lagi pada hari yang sama: tidak ada duplikat.
        $this->artisan('jobs:notify-deadlines')->assertSuccessful();
        $this->assertCount(2, $this->titles($this->admin));

        $this->assertNotNull($threeDays->refresh()->deadline_h3_notified_at);
        $this->assertNotNull($today->refresh()->deadline_h0_notified_at);
    }

    public function test_changing_the_deadline_rearms_the_reminders(): void
    {
        $vacancy = JobVacancy::factory()->create(['deadline' => today()->addDays(3)]);
        $this->artisan('jobs:notify-deadlines');
        $this->assertCount(1, $this->titles($this->admin));

        // Deadline diundur lalu kembali tiga hari: pengingat dikirim lagi.
        $vacancy->update(['deadline' => today()->addDays(8)]);
        $this->assertNull($vacancy->refresh()->deadline_h3_notified_at);

        $vacancy->update(['deadline' => today()->addDays(3)]);
        $this->artisan('jobs:notify-deadlines');

        $this->assertCount(2, $this->titles($this->admin));
    }

    public function test_deadline_command_is_scheduled_daily(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'jobs:notify-deadlines'));

        $this->assertNotNull($event);
        $this->assertSame('0 7 * * *', $event->expression);
    }

    // ---- Lonceng di dashboard -------------------------------------------------------------

    public function test_bell_shows_unread_count_history_and_marks_as_read(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->admin);
        $this->liveProject();

        $this->assertSame(1, $this->admin->unreadNotifications()->count());

        // Badge belum dibaca ada pada tombol lonceng; daftar isi dimuat saat panel dibuka.
        Livewire::test(DatabaseNotifications::class)
            ->assertSeeHtml('1 unread notification')
            ->call('markAllNotificationsAsRead');

        $this->assertSame(0, $this->admin->refresh()->unreadNotifications()->count());

        // Riwayat tetap ada setelah dibaca, lengkap dengan isinya.
        $this->assertSame(['Project published'], $this->titles($this->admin));
        $this->assertStringContainsString('Menara Uji', $this->admin->notifications()->first()->data['body']);
    }

    public function test_each_admin_has_their_own_read_state(): void
    {
        $other = User::factory()->create();
        $this->liveProject();

        $this->admin->unreadNotifications->markAsRead();

        $this->assertSame(0, $this->admin->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_dashboard_renders_the_notification_bell_for_admins(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->admin);

        $this->get('/admin')->assertOk()->assertSeeLivewire(DatabaseNotifications::class);
    }
}
