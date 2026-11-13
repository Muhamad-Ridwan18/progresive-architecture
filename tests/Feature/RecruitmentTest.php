<?php

namespace Tests\Feature;

use App\Actions\Recruitment\ChangeApplicationStatus;
use App\Enums\ApplicationStatus;
use App\Enums\FormFieldType;
use App\Filament\Resources\JobApplications\Pages\ListJobApplications;
use App\Filament\Resources\JobApplications\Pages\ViewJobApplication;
use App\Filament\Resources\JobApplications\Widgets\ApplicantStats;
use App\Filament\Resources\JobCategories\Pages\ListJobCategories;
use App\Filament\Resources\JobVacancies\Pages\CreateJobVacancy;
use App\Filament\Resources\JobVacancies\Pages\ListJobVacancies;
use App\Models\JobApplication;
use App\Models\JobApplicationFile;
use App\Models\JobCategory;
use App\Models\JobVacancy;
use App\Models\User;
use App\Support\ApplicationForm;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class RecruitmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
    }

    /** Data kiriman valid untuk formulir bawaan (nama, email, telepon, CV, surat lamaran). */
    private function validSubmission(array $override = []): array
    {
        return array_replace_recursive([
            'answers' => [
                'full_name' => 'Sinta Rahayu',
                'email' => 'sinta@example.com',
                'phone' => '0812-0000-0002',
                'cover_letter' => 'Saya tertarik.',
            ],
            'files' => ['cv' => UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf')],
            'consent' => '1',
        ], $override);
    }

    private function vacancy(array $attributes = []): JobVacancy
    {
        return JobVacancy::factory()->create($attributes + [
            'form_fields' => ApplicationForm::defaultFields(),
        ]);
    }

    private function admin(): User
    {
        Filament::setCurrentPanel('admin');

        return tap(User::factory()->create(), fn (User $user) => $this->actingAs($user));
    }

    // ---- Halaman publik -------------------------------------------------------------------

    public function test_careers_lists_published_and_closed_vacancies_but_never_drafts(): void
    {
        $this->vacancy(['title' => 'Site Engineer']);
        $this->vacancy(['title' => 'Staf HSE'])->update(['status' => 'closed']);
        $this->vacancy(['title' => 'Rahasia Draft'])->update(['status' => 'draft']);

        $this->get('/careers')
            ->assertOk()
            ->assertSee('Site Engineer')
            ->assertSee('Staf HSE')
            ->assertDontSee('Rahasia Draft');
    }

    public function test_careers_filters_by_category_and_open_status(): void
    {
        $engineering = JobCategory::factory()->create(['name' => 'Engineering']);
        $this->vacancy(['title' => 'Site Engineer', 'job_category_id' => $engineering->id]);
        $this->vacancy(['title' => 'Arsitek Junior']);
        $this->vacancy(['title' => 'Drafter Lama', 'job_category_id' => $engineering->id, 'deadline' => today()->subDay()]);

        $this->get('/careers?category='.$engineering->slug)
            ->assertSee('Site Engineer')
            ->assertDontSee('Arsitek Junior');

        $this->get('/careers?status=open')
            ->assertSee('Site Engineer')
            ->assertDontSee('Drafter Lama');

        $this->get('/careers?status=closed')
            ->assertSee('Drafter Lama')
            ->assertDontSee('Site Engineer');
    }

    public function test_draft_vacancy_detail_is_not_found(): void
    {
        $vacancy = $this->vacancy();
        $vacancy->update(['status' => 'draft']);

        $this->get(route('careers.show', $vacancy))->assertNotFound();
    }

    public function test_detail_shows_the_application_form_for_open_vacancy_only(): void
    {
        $open = $this->vacancy(['title' => 'Site Engineer']);
        $closed = $this->vacancy(['title' => 'Staf HSE', 'deadline' => today()->subDay()]);

        $this->get(route('careers.show', $open))
            ->assertOk()
            ->assertSee('Nama lengkap')
            ->assertSee('Kirim lamaran');

        $this->get(route('careers.show', $closed))
            ->assertOk()
            ->assertDontSee('Kirim lamaran')
            ->assertSee('sudah ditutup');
    }

    // ---- Mengirim lamaran -----------------------------------------------------------------

    public function test_application_is_saved_with_reference_snapshot_private_file_and_history(): void
    {
        $vacancy = $this->vacancy();

        $this->post(route('careers.apply', $vacancy), $this->validSubmission())
            ->assertRedirect(route('careers.show', $vacancy))
            ->assertSessionHas('application_reference');

        $application = JobApplication::query()->firstOrFail();

        $this->assertMatchesRegularExpression('/^APP-\d{4}-\d{6}$/', $application->reference_number);
        $this->assertSame('Sinta Rahayu', $application->applicant_name);
        $this->assertSame('sinta@example.com', $application->applicant_email);
        $this->assertSame(ApplicationStatus::Submitted, $application->status);
        $this->assertSame('Saya tertarik.', $application->answers['cover_letter']);
        $this->assertCount(5, $application->form_snapshot);

        $file = $application->files()->firstOrFail();
        $this->assertSame('cv', $file->field_key);
        $this->assertSame('cv.pdf', $file->original_name);
        Storage::disk('local')->assertExists($file->path);

        $this->assertNull($application->statusChanges()->firstOrFail()->from_status);
        $this->assertSame(ApplicationStatus::Submitted, $application->statusChanges()->first()->to_status);
    }

    public function test_success_page_shows_the_reference_number(): void
    {
        $vacancy = $this->vacancy();

        $this->followingRedirects()
            ->post(route('careers.apply', $vacancy), $this->validSubmission())
            ->assertSee(JobApplication::query()->firstOrFail()->reference_number);
    }

    public function test_application_keeps_its_own_form_snapshot_when_the_form_changes_later(): void
    {
        $vacancy = $this->vacancy();
        $this->post(route('careers.apply', $vacancy), $this->validSubmission());

        $vacancy->update(['form_fields' => [ApplicationForm::field(FormFieldType::Text, 'Hanya satu field', true, 'only')]]);

        $this->assertCount(5, JobApplication::query()->firstOrFail()->form_snapshot);
    }

    public function test_required_fields_and_consent_are_validated(): void
    {
        $vacancy = $this->vacancy();

        $this->post(route('careers.apply', $vacancy), [])
            ->assertSessionHasErrors(['answers.full_name', 'answers.email', 'answers.phone', 'files.cv', 'consent']);

        $this->assertSame(0, JobApplication::query()->count());
    }

    public function test_file_type_and_size_are_restricted_by_the_field_settings(): void
    {
        $vacancy = $this->vacancy();

        $this->post(route('careers.apply', $vacancy), $this->validSubmission([
            'files' => ['cv' => UploadedFile::fake()->create('cv.exe', 100, 'application/octet-stream')],
        ]))->assertSessionHasErrors('files.cv');

        $this->post(route('careers.apply', $vacancy), $this->validSubmission([
            'files' => ['cv' => UploadedFile::fake()->create('besar.pdf', 6 * 1024, 'application/pdf')],
        ]))->assertSessionHasErrors('files.cv');

        $this->assertSame(0, JobApplication::query()->count());
    }

    public function test_closed_or_expired_vacancies_reject_applications(): void
    {
        $closed = $this->vacancy();
        $closed->update(['status' => 'closed']);
        $expired = $this->vacancy(['deadline' => today()->subDay()]);

        foreach ([$closed, $expired] as $vacancy) {
            $this->post(route('careers.apply', $vacancy), $this->validSubmission())
                ->assertSessionHasErrors('form');
        }

        $this->assertSame(0, JobApplication::query()->count());
    }

    public function test_deadline_day_itself_still_accepts_applications(): void
    {
        $vacancy = $this->vacancy(['deadline' => today()]);

        $this->post(route('careers.apply', $vacancy), $this->validSubmission())->assertSessionDoesntHaveErrors();

        $this->assertSame(1, JobApplication::query()->count());
    }

    public function test_honeypot_submissions_get_a_fake_success_and_store_nothing(): void
    {
        $vacancy = $this->vacancy();

        $this->post(route('careers.apply', $vacancy), $this->validSubmission() + ['website_url' => 'http://spam.test'])
            ->assertRedirect(route('careers.show', $vacancy));

        $this->assertSame(0, JobApplication::query()->count());
    }

    public function test_dropdown_answers_must_be_one_of_the_options_and_repeater_rows_are_saved(): void
    {
        $vacancy = $this->vacancy(['form_fields' => ApplicationForm::normalize([
            ApplicationForm::field(FormFieldType::Text, 'Nama', true, 'name'),
            ApplicationForm::field(FormFieldType::Dropdown, 'Pendidikan', true, 'edu', ['options' => "SMA\nS1"]),
            ApplicationForm::field(FormFieldType::Repeater, 'Pengalaman', false, 'exp', ['columns' => "Perusahaan\nJabatan"]),
        ])]);

        $this->post(route('careers.apply', $vacancy), [
            'answers' => ['name' => 'Budi', 'edu' => 'S3'],
            'consent' => '1',
        ])->assertSessionHasErrors('answers.edu');

        $this->post(route('careers.apply', $vacancy), [
            'answers' => [
                'name' => 'Budi',
                'edu' => 'S1',
                'exp' => [['PT Maju', 'Engineer'], ['', '']],
            ],
            'consent' => '1',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame([['PT Maju', 'Engineer']], JobApplication::query()->firstOrFail()->answers['exp']);
    }

    // ---- Dokumen privat -------------------------------------------------------------------

    public function test_application_documents_are_only_available_to_active_admins(): void
    {
        $vacancy = $this->vacancy();
        $this->post(route('careers.apply', $vacancy), $this->validSubmission());
        $file = JobApplicationFile::query()->firstOrFail();

        $this->get(route('admin.application-files.show', $file))->assertNotFound();

        $this->actingAs(User::factory()->create(['is_active' => false]))
            ->get(route('admin.application-files.show', $file))->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.application-files.show', $file))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            // Dokumen dibaca di dalam halaman pelamar lewat iframe (viewer), jadi tidak boleh diblokir untuk di-frame.
            ->assertHeaderMissing('X-Frame-Options');

        $this->assertStringStartsWith('attachment', $this->get(route('admin.application-files.show', [$file, 'download' => 1]))->headers->get('Content-Disposition'));
    }

    public function test_documents_are_read_inside_the_applicant_page_without_downloading(): void
    {
        $this->admin();
        $application = JobApplication::factory()->create();
        $pdf = $application->files()->create(['field_key' => 'cv', 'original_name' => 'CV Dina.pdf', 'path' => 'x/cv.pdf', 'mime_type' => 'application/pdf', 'size' => 1200000]);
        $image = $application->files()->create(['field_key' => 'ijazah', 'original_name' => 'Ijazah.jpg', 'path' => 'x/ijazah.jpg', 'mime_type' => 'image/jpeg', 'size' => 900000]);
        $docx = $application->files()->create(['field_key' => 'lain', 'original_name' => 'Surat.docx', 'path' => 'x/surat.docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'size' => 50000]);

        $html = Livewire::test(ViewJobApplication::class, ['record' => $application->getKey()])
            ->assertSuccessful()
            ->assertSee('Tidak perlu mengunduh')
            ->html();

        // PDF memakai penampil bawaan browser lewat iframe, gambar tampil langsung, DOCX hanya bisa diunduh.
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString(e(route('admin.application-files.show', $pdf)), $html);
        $this->assertStringContainsString('<img src="'.e(route('admin.application-files.show', $image)).'"', $html);
        $this->assertStringContainsString('tidak dapat dipratinjau', $html);
        $this->assertStringContainsString(e(route('admin.application-files.show', [$docx, 'download' => 1])), $html);
    }

    public function test_pipeline_shows_the_stage_reached_and_the_final_decision(): void
    {
        $this->admin();

        $interview = JobApplication::factory()->create(['status' => ApplicationStatus::Interview]);
        Livewire::test(ViewJobApplication::class, ['record' => $interview->getKey()])->assertSee('Decision')->assertSee('is-now', false);

        $rejected = JobApplication::factory()->create(['status' => ApplicationStatus::Rejected]);
        Livewire::test(ViewJobApplication::class, ['record' => $rejected->getKey()])->assertSee('is-bad', false)->assertDontSee('Decision');
    }

    public function test_list_has_status_tabs_with_counts_and_a_summary(): void
    {
        $this->admin();
        $submitted = JobApplication::factory()->create(['status' => ApplicationStatus::Submitted]);
        $accepted = JobApplication::factory()->create(['status' => ApplicationStatus::Accepted]);

        Livewire::test(ApplicantStats::class)->assertSee('Total applicants')->assertSee('Perlu ditinjau');

        Livewire::test(ListJobApplications::class)
            ->assertCanSeeTableRecords([$submitted, $accepted])
            ->set('activeTab', ApplicationStatus::Accepted->value)
            ->assertCanSeeTableRecords([$accepted])
            ->assertCanNotSeeTableRecords([$submitted]);
    }

    // ---- Status dan riwayat ---------------------------------------------------------------

    public function test_changing_status_records_history_and_activity(): void
    {
        $admin = $this->admin();
        $application = JobApplication::factory()->create();

        $changed = app(ChangeApplicationStatus::class)->handle($application, ApplicationStatus::Reviewing, $admin);

        $this->assertTrue($changed);
        $this->assertSame(ApplicationStatus::Reviewing, $application->fresh()->status);

        $change = $application->statusChanges()->firstOrFail();
        $this->assertSame(ApplicationStatus::Submitted, $change->from_status);
        $this->assertSame(ApplicationStatus::Reviewing, $change->to_status);
        $this->assertSame($admin->id, $change->user_id);
        $this->assertTrue(Activity::query()->where('event', 'status_changed')->exists());
    }

    public function test_setting_the_same_status_changes_nothing(): void
    {
        $application = JobApplication::factory()->create();

        $this->assertFalse(app(ChangeApplicationStatus::class)->handle($application, ApplicationStatus::Submitted, null));
        $this->assertSame(0, $application->statusChanges()->count());
    }

    // ---- Dashboard ------------------------------------------------------------------------

    public function test_admin_lists_render_and_filter_applicants_by_vacancy(): void
    {
        $this->admin();
        $mine = JobApplication::factory()->create(['applicant_name' => 'Pelamar Satu']);
        $other = JobApplication::factory()->create(['applicant_name' => 'Pelamar Dua']);
        JobCategory::factory()->create();

        Livewire::test(ListJobVacancies::class)->assertSuccessful();
        Livewire::test(ListJobCategories::class)->assertSuccessful();

        Livewire::withQueryParams(['vacancy' => $mine->job_vacancy_id])
            ->test(ListJobApplications::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_applicant_detail_changes_status_and_stores_notes(): void
    {
        $this->admin();
        $application = JobApplication::factory()->create();

        Livewire::test(ViewJobApplication::class, ['record' => $application->getKey()])
            ->assertSuccessful()
            ->callAction('changeStatus', ['status' => 'interview'])
            ->callAction('addNote', ['body' => 'Portofolio kuat.']);

        $this->assertSame(ApplicationStatus::Interview, $application->fresh()->status);
        $this->assertSame('Portofolio kuat.', $application->notes()->firstOrFail()->body);
    }

    public function test_new_vacancy_inherits_the_category_form_template(): void
    {
        $this->admin();
        $category = JobCategory::factory()->create([
            'default_field_keys' => ['edu', 'major'],
            'default_required_keys' => ['edu'],
        ]);

        Livewire::test(CreateJobVacancy::class)
            ->fillForm(['job_category_id' => $category->id])
            ->assertSchemaStateSet(fn (array $state): bool => in_array('edu', $state['field_keys'], true)
                && in_array('major', $state['field_keys'], true)
                && ! in_array('cv', $state['field_keys'], true)
                && $state['required_keys'] === ['edu']);
    }

    public function test_vacancy_with_applicants_and_category_with_vacancies_cannot_be_deleted(): void
    {
        $application = JobApplication::factory()->create();
        $vacancy = $application->vacancy;

        $this->assertFalse($vacancy->delete());
        $this->assertFalse($vacancy->category->delete());
        $this->assertDatabaseHas('job_vacancies', ['id' => $vacancy->id]);
        $this->assertDatabaseHas('job_applications', ['id' => $application->id]);
    }

    public function test_new_category_gets_the_default_checklist_and_unique_slug(): void
    {
        $a = JobCategory::create(['name' => 'Engineering']);
        $b = JobCategory::create(['name' => 'Engineering']);

        $this->assertSame('engineering', $a->slug);
        $this->assertSame('engineering-2', $b->slug);
        $this->assertSame(['cv', 'cover_letter'], $a->default_field_keys);
        $this->assertSame(['cv'], $a->default_required_keys);
    }
}
