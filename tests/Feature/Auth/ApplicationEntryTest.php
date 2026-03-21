<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\VisaType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    // -----------------------------------------------------------------------
    // US1: Root route redirects to apply form
    // -----------------------------------------------------------------------

    public function test_root_url_redirects_guest_to_apply_form(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('onboarding.show'));
    }

    public function test_root_url_redirects_authenticated_user_to_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_apply_route_renders_onboarding_form(): void
    {
        VisaType::factory()->create(['name' => 'Test Visa', 'is_active' => true]);

        $response = $this->get(route('onboarding.show'));

        $response->assertOk();
        $response->assertViewIs('client.onboarding.form');
    }

    // -----------------------------------------------------------------------
    // US1: Duplicate email shows specific error and login link is present
    // -----------------------------------------------------------------------

    public function test_apply_form_shows_specific_error_for_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);
        $visaType = VisaType::factory()->create(['is_active' => true]);

        $response = $this->post(route('onboarding.store'), [
            'full_name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
            'phone' => '0501234567',
            'nationality' => 'Saudi',
            'country_of_residence' => 'Saudi Arabia',
            'visa_type_id' => $visaType->id,
            'adults_count' => 1,
            'children_count' => 0,
            'application_start_date' => now()->addDays(10)->toDateString(),
            'job_title' => 'Engineer',
            'employment_type' => 'employed',
            'monthly_income' => 5000,
            'agreed_to_terms' => '1',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'An account with this email already exists.',
        ]);
    }

    public function test_apply_form_login_toggle_link_is_rendered(): void
    {
        VisaType::factory()->create(['name' => 'Test Visa', 'is_active' => true]);

        $response = $this->get(route('onboarding.show'));

        $response->assertSee(route('login'));
    }

    // -----------------------------------------------------------------------
    // US2: Login page has apply toggle link
    // -----------------------------------------------------------------------

    public function test_login_page_shows_apply_now_toggle_link(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee(route('onboarding.show'));
    }

    // -----------------------------------------------------------------------
    // US3: Authenticated users cannot access entry pages
    // -----------------------------------------------------------------------

    public function test_authenticated_user_cannot_access_login_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_authenticated_user_cannot_access_apply_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $response = $this->actingAs($user)->get(route('onboarding.show'));

        $response->assertRedirect(route('dashboard'));
    }
}
