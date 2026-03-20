<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use App\Services\Admin\AdminDashboardService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_access_dashboard(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
    }

    public function test_client_is_forbidden_from_dashboard(): void
    {
        $client = User::factory()->create()->assignRole('client');

        $response = $this->actingAs($client)->get(route('admin.dashboard'));

        $response->assertForbidden();
    }

    public function test_reviewer_is_forbidden_from_dashboard(): void
    {
        $reviewer = User::factory()->create()->assignRole('reviewer');

        $response = $this->actingAs($reviewer)->get(route('admin.dashboard'));

        $response->assertForbidden();
    }

    public function test_unauthenticated_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_all_placeholder_sections_return_200_for_admin(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $routes = [
            'admin.visa-types.index',
            'admin.clients.index',
            'admin.task-builder.index',
            'admin.reviewers.index',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($admin)->get(route($route));
            $response->assertOk();
        }
    }

    public function test_client_is_forbidden_from_all_admin_routes(): void
    {
        $client = User::factory()->create()->assignRole('client');
        $routes = [
            'admin.visa-types.index',
            'admin.clients.index',
            'admin.task-builder.index',
            'admin.reviewers.index',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($client)->get(route($route));
            $response->assertForbidden();
        }
    }

    public function test_dashboard_shows_summary_cards(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $visaType = VisaType::factory()->create();

        VisaApplication::factory()->for($visaType)->create(['status' => 'in_progress']);
        VisaApplication::factory()->for($visaType)->create(['status' => 'pending_review']);
        User::factory()->create()->assignRole('client');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee(__('admin.active_applications'))
            ->assertSee(__('admin.pending_review'))
            ->assertSee(__('admin.total_clients'));
    }

    public function test_dashboard_shows_recent_applications(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $client = User::factory()->create()->assignRole('client');
        $visaType = VisaType::factory()->create();

        VisaApplication::factory()->count(3)->for($visaType)->for($client)->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee(__('admin.recent_applications'));
    }

    public function test_applications_list_is_searchable(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $visaType = VisaType::factory()->create();
        $app1 = VisaApplication::factory()->for($visaType)->create();
        $app2 = VisaApplication::factory()->for($visaType)->create();
        $app3 = VisaApplication::factory()->for($visaType)->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.applications.index', ['search' => $app1->reference_number]));

        $response->assertOk()
            ->assertSee($app1->reference_number)
            ->assertDontSee($app2->reference_number)
            ->assertDontSee($app3->reference_number);
    }

    public function test_applications_list_sorted_newest_first_by_default(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $visaType = VisaType::factory()->create();
        $older = VisaApplication::factory()->for($visaType)->create(['created_at' => now()->subDays(2)]);
        $newer = VisaApplication::factory()->for($visaType)->create(['created_at' => now()]);

        $response = $this->actingAs($admin)->get(route('admin.applications.index'));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertGreaterThan(
            strpos($content, $newer->reference_number),
            strpos($content, $older->reference_number),
            'Newer application should appear before older application'
        );
    }

    public function test_applications_list_paginates_at_15(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $visaType = VisaType::factory()->create();
        VisaApplication::factory()->count(16)->for($visaType)->create();

        $response = $this->actingAs($admin)->get(route('admin.applications.index'));

        $response->assertOk();
        $this->assertStringContainsString('Next', $response->getContent());
    }

    public function test_applications_list_shows_empty_state(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $response = $this->actingAs($admin)
            ->get(route('admin.applications.index', ['search' => 'NOTEXIST-99999']));

        $response->assertOk()->assertSee(__('admin.no_records'));
    }

    public function test_dashboard_widget_shows_error_when_service_fails(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $this->mock(AdminDashboardService::class, function ($mock) {
            $mock->shouldReceive('getActiveApplicationsCount')
                ->once()
                ->andReturn(['data' => null, 'error' => 'Unable to load']);
            $mock->shouldReceive('getPendingReviewCount')
                ->once()
                ->andReturn(['data' => 0, 'error' => null]);
            $mock->shouldReceive('getTotalClientsCount')
                ->once()
                ->andReturn(['data' => 0, 'error' => null]);
            $mock->shouldReceive('getRecentApplications')
                ->once()
                ->andReturn(['data' => collect(), 'error' => null]);
        });

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()->assertSee(__('admin.widget_error'));
    }
}
