<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VisaTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(VisaTypeSeeder::class);
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)->get('/admin/dashboard')->assertOk();
    }

    public function test_client_gets_403_on_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_reviewer_gets_403_on_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('reviewer');

        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_client_can_access_client_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        VisaApplication::create([
            'user_id' => $user->id,
            'visa_type_id' => VisaType::first()->id,
            'status' => 'pending_review',
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => '+1555000123',
            'nationality' => 'Jordanian',
            'country_of_residence' => 'UAE',
            'job_title' => 'Engineer',
            'employment_type' => 'employed',
            'monthly_income' => 5000,
            'adults_count' => 1,
            'children_count' => 0,
            'application_start_date' => now()->addDays(30)->toDateString(),
            'notes' => 'Test note',
            'agreed_to_terms' => true,
        ]);

        $this->actingAs($user)->get('/client/dashboard')->assertOk();
    }

    public function test_reviewer_can_access_reviewer_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('reviewer');

        $this->actingAs($user)->get('/reviewer/dashboard')->assertOk();
    }

    public function test_unauthenticated_user_is_redirected_to_login_for_dashboards(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->get('/client/dashboard')->assertRedirect(route('login'));
        $this->get('/reviewer/dashboard')->assertRedirect(route('login'));
    }
}
