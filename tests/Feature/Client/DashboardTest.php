<?php

namespace Tests\Feature\Client;

use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VisaTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(VisaTypeSeeder::class);
    }

    protected function makeOnboardedClient(string $reference = 'APP-00001'): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $application = VisaApplication::create([
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

        $application->forceFill(['reference_number' => $reference])->saveQuietly();

        return $user;
    }

    public function test_authenticated_client_sees_dashboard(): void
    {
        $client = $this->makeOnboardedClient();

        $this->actingAs($client)
            ->get('/client/dashboard')
            ->assertOk()
            ->assertSee('APP-00001');
    }

    public function test_all_8_tabs_accessible(): void
    {
        $client = $this->makeOnboardedClient();

        foreach (['overview', 'documents', 'tasks', 'payments', 'timeline', 'messages', 'profile', 'support'] as $tab) {
            $this->actingAs($client)->get('/client/dashboard/' . $tab)->assertOk();
        }
    }

    public function test_invalid_tab_falls_back_to_overview(): void
    {
        $client = $this->makeOnboardedClient();

        $this->actingAs($client)->get('/client/dashboard/nonexistent')->assertOk()->assertSee('APP-00001');
    }

    public function test_unauthenticated_visitor_redirected_to_login(): void
    {
        $this->get('/client/dashboard')->assertRedirect(route('login'));
    }

    public function test_client_without_application_sees_no_application_view(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)
            ->get('/client/dashboard')
            ->assertOk()
            ->assertSee(__('client.no_application_title'));
    }

    public function test_client_cannot_view_other_clients_application(): void
    {
        $clientA = $this->makeOnboardedClient('APP-00001');
        $clientB = $this->makeOnboardedClient('APP-00002');

        $this->actingAs($clientA)
            ->get('/client/dashboard')
            ->assertDontSee($clientB->email);
    }
}
