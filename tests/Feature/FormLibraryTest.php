<?php

namespace Tests\Feature;

use App\Enums\FormFieldType;
use App\Filament\Resources\FormFields\Pages\CreateFormField;
use App\Filament\Resources\FormFields\Pages\EditFormField;
use App\Filament\Resources\FormFields\Pages\ListFormFields;
use App\Filament\Resources\JobVacancies\Pages\CreateJobVacancy;
use App\Filament\Resources\JobVacancies\Pages\EditJobVacancy;
use App\Models\FormField;
use App\Models\JobApplication;
use App\Models\JobCategory;
use App\Models\JobVacancy;
use App\Models\User;
use App\Services\VacancyFormComposer;
use App\Support\ApplicationForm;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Pustaka isian formulir lamaran: admin mencentang isian per lowongan, bukan menyusun dari nol (SRS 6.2). */
class FormLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
    }

    private function admin(): User
    {
        Filament::setCurrentPanel('admin');

        return tap(User::factory()->create(), fn (User $user) => $this->actingAs($user));
    }

    private function composer(): VacancyFormComposer
    {
        return app(VacancyFormComposer::class);
    }

    /** @return list<string> */
    private function keys(array $fields): array
    {
        return array_column($fields, 'key');
    }

    // ---- Pustaka bawaan -------------------------------------------------------------------

    public function test_library_comes_with_standard_fields_and_locked_core_fields(): void
    {
        $this->assertGreaterThanOrEqual(15, FormField::query()->count());

        foreach (FormField::CORE_KEYS as $key) {
            $field = FormField::query()->where('key', $key)->firstOrFail();
            $this->assertTrue($field->is_core);
            $this->assertFalse($field->delete(), 'Isian inti tidak boleh dihapus.');
        }

        $this->assertSame(FormFieldType::File, FormField::query()->where('key', 'cv')->firstOrFail()->type);
    }

    // ---- Penyusun formulir ----------------------------------------------------------------

    public function test_composer_always_includes_core_fields_as_required_and_follows_library_order(): void
    {
        $fields = $this->composer()->compose(['portfolio', 'cv', 'edu'], ['edu']);
        $byKey = collect($fields)->keyBy('key');

        $this->assertSame(['full_name', 'email', 'phone', 'cv', 'portfolio', 'edu'], $this->keys($fields));
        $this->assertTrue($byKey['full_name']['required']);
        $this->assertTrue($byKey['edu']['required']);
        $this->assertFalse($byKey['cv']['required'], 'Wajib hanya bila dicentang pada daftar wajib.');
        $this->assertFalse($byKey['portfolio']['required']);
    }

    public function test_composer_keeps_custom_fields_that_are_not_in_the_library(): void
    {
        $existing = ApplicationForm::normalize([ApplicationForm::field(FormFieldType::Text, 'Isian lama', false, 'legacy_field')]);

        $fields = $this->composer()->compose(['cv'], [], $existing);

        $this->assertContains('legacy_field', $this->keys($fields));
    }

    public function test_selection_is_read_back_from_a_vacancy_form(): void
    {
        $fields = $this->composer()->compose(['cv', 'edu', 'portfolio'], ['cv', 'edu']);

        $selection = $this->composer()->selectionFrom($fields);

        $this->assertEqualsCanonicalizing(['cv', 'edu', 'portfolio'], $selection['keys'], 'Isian inti tidak ada di daftar centang.');
        $this->assertEqualsCanonicalizing(['cv', 'edu'], $selection['required']);
    }

    // ---- Admin: pustaka -------------------------------------------------------------------

    public function test_admin_adds_a_field_to_the_library_and_the_key_is_generated_once(): void
    {
        $this->admin();

        Livewire::test(CreateFormField::class)
            ->fillForm(['label' => 'Link Behance', 'type' => 'text', 'required_default' => false])
            ->call('create')
            ->assertHasNoFormErrors();

        $field = FormField::query()->where('label', 'Link Behance')->firstOrFail();
        $this->assertSame('link_behance', $field->key);

        Livewire::test(EditFormField::class, ['record' => $field->getKey()])
            ->fillForm(['label' => 'Behance atau Instagram'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('link_behance', $field->refresh()->key, 'Kunci tidak berubah walau label diganti.');
        $this->assertSame('Behance atau Instagram', $field->label);
    }

    public function test_dropdown_fields_need_options_and_irrelevant_settings_are_dropped(): void
    {
        $this->admin();

        Livewire::test(CreateFormField::class)
            ->fillForm(['label' => 'Status', 'type' => 'dropdown'])
            ->call('create')
            ->assertHasFormErrors(['options']);

        Livewire::test(CreateFormField::class)
            ->fillForm(['label' => 'Status', 'type' => 'dropdown', 'options' => "Lajang\nMenikah", 'max_mb' => 8])
            ->call('create')
            ->assertHasNoFormErrors();

        $field = FormField::query()->where('key', 'status')->firstOrFail();
        $this->assertNull($field->max_mb, 'Batas ukuran hanya untuk File Upload.');
    }

    public function test_library_list_renders_and_core_fields_cannot_be_deleted_from_the_ui(): void
    {
        $this->admin();
        $core = FormField::query()->where('key', 'email')->firstOrFail();
        $custom = FormField::query()->where('key', 'major')->firstOrFail();

        Livewire::test(ListFormFields::class)
            ->assertSuccessful()
            ->assertTableActionHidden('delete', $core)
            ->assertTableActionVisible('delete', $custom);
    }

    public function test_deleting_a_library_field_leaves_existing_vacancies_alone_and_cleans_category_defaults(): void
    {
        $category = JobCategory::factory()->create(['default_field_keys' => ['major'], 'default_required_keys' => ['major']]);
        $vacancy = JobVacancy::factory()->create([
            'job_category_id' => $category->id,
            'form_fields' => $this->composer()->compose(['major'], ['major']),
        ]);

        FormField::query()->where('key', 'major')->firstOrFail()->delete();

        $this->assertNotContains('major', $category->refresh()->default_field_keys);
        $this->assertNotContains('major', $category->default_required_keys);
        $this->assertContains('major', $this->keys($vacancy->refresh()->form_fields), 'Salinan di lowongan tidak berubah.');
    }

    // ---- Admin: lowongan ------------------------------------------------------------------

    public function test_new_vacancy_starts_from_the_category_checklist_and_saves_the_chosen_form(): void
    {
        $this->admin();
        $category = JobCategory::factory()->create([
            'default_field_keys' => ['cv', 'edu'],
            'default_required_keys' => ['cv', 'edu'],
        ]);

        Livewire::test(CreateJobVacancy::class)
            ->fillForm([
                'title' => 'Estimator',
                'job_category_id' => $category->id,
                'location' => 'Bandung',
                'employment_type' => 'Full-time',
            ])
            // Admin menambah dua isian dan melepas satu wajib.
            ->fillForm(['field_keys' => ['cv', 'edu', 'portfolio', 'expected_salary'], 'required_keys' => ['cv']])
            ->call('create')
            ->assertHasNoFormErrors();

        $vacancy = JobVacancy::query()->where('title', 'Estimator')->firstOrFail();
        $byKey = collect($vacancy->form_fields)->keyBy('key');

        $this->assertSame(['full_name', 'email', 'phone', 'cv', 'portfolio', 'edu', 'expected_salary'], $this->keys($vacancy->form_fields));
        $this->assertTrue($byKey['cv']['required']);
        $this->assertFalse($byKey['edu']['required']);
        $this->assertFalse($byKey['portfolio']['required']);
    }

    public function test_editing_a_vacancy_shows_its_current_checklist_and_saving_recomposes_the_form(): void
    {
        $this->admin();
        $vacancy = JobVacancy::factory()->create(['form_fields' => $this->composer()->compose(['cv', 'edu'], ['cv'])]);

        Livewire::test(EditJobVacancy::class, ['record' => $vacancy->getRouteKey()])
            ->assertSchemaStateSet(fn (array $state): bool => in_array('edu', $state['field_keys'], true) && $state['required_keys'] === ['cv'])
            ->fillForm(['field_keys' => ['cv', 'portfolio'], 'required_keys' => ['portfolio']])
            ->call('save')
            ->assertHasNoFormErrors();

        $form = collect($vacancy->refresh()->form_fields)->keyBy('key');

        $this->assertSame(['full_name', 'email', 'phone', 'cv', 'portfolio'], $form->keys()->all());
        $this->assertTrue($form['portfolio']['required']);
        $this->assertFalse($form['cv']['required']);
    }

    // ---- Publik ---------------------------------------------------------------------------

    public function test_applicants_only_see_and_must_fill_the_checked_fields(): void
    {
        $vacancy = JobVacancy::factory()->create([
            'form_fields' => $this->composer()->compose(['cv', 'edu', 'software'], ['cv', 'edu']),
        ]);

        $this->get(route('careers.show', $vacancy))->assertOk()
            ->assertSee('Pendidikan terakhir')->assertSee('Kemampuan software')
            ->assertDontSee('Ekspektasi gaji')->assertDontSee('Portofolio (PDF)');

        $this->post(route('careers.apply', $vacancy), ['consent' => '1'])
            ->assertSessionHasErrors(['answers.full_name', 'answers.edu', 'files.cv'])
            ->assertSessionDoesntHaveErrors(['answers.software']);

        $this->post(route('careers.apply', $vacancy), [
            'answers' => ['full_name' => 'Sinta', 'email' => 's@example.com', 'phone' => '0812-0000-0002', 'edu' => 'S1', 'software' => ['AutoCAD', 'Revit']],
            'files' => ['cv' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')],
            'consent' => '1',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(['AutoCAD', 'Revit'], JobApplication::query()->firstOrFail()->answers['software']);
    }
}
