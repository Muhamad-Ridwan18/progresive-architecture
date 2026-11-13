<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Services\Settings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_active_admin_can_open_all_system_pages(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['/admin', '/admin/settings', '/admin/users', '/admin/activity-logs', '/admin/profile'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_inactive_user_cannot_access_panel(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => false]));

        $this->get('/admin')->assertForbidden();
    }

    public function test_last_active_admin_cannot_be_deactivated(): void
    {
        $only = User::factory()->create();
        $this->actingAs($only);

        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('deactivate')->table($only));

        $this->assertTrue($only->fresh()->is_active);
    }

    public function test_admin_can_deactivate_another_admin_and_it_is_logged(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($me);

        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('deactivate')->table($other));

        $this->assertFalse($other->fresh()->is_active);
        $this->assertTrue(
            Activity::query()->where('subject_id', $other->id)->where('event', 'updated')->exists()
        );
    }

    public function test_password_change_is_logged_without_the_password(): void
    {
        $me = User::factory()->create();
        $target = User::factory()->create();
        $this->actingAs($me);

        Livewire::test(ListUsers::class)
            ->callAction(
                TestAction::make('resetPassword')->table($target),
                ['password' => 'SuperSecret12345'],
            );

        $log = Activity::query()->where('event', 'password_changed')->first();

        $this->assertNotNull($log);
        $this->assertStringNotContainsString('SuperSecret12345', json_encode($log->toArray()));
        $this->assertStringNotContainsString('password', json_encode($log->properties));
    }

    public function test_website_settings_has_no_seo_fields_because_seo_is_automatic(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageSettings::class)
            ->assertSuccessful()
            ->assertDontSee('Meta Title')
            ->assertDontSee('Meta Description')
            ->assertDontSee('Default SEO Image');
    }

    public function test_settings_are_saved_and_logged_with_before_and_after(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageSettings::class)
            ->fillForm(['site_name' => 'Acme Contractors', 'tagline' => 'Building well'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Acme Contractors', Settings::get('site_name'));

        $log = Activity::query()->where('log_name', 'settings')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('Acme Contractors', $log->properties['attributes']['site_name']);
    }
}
