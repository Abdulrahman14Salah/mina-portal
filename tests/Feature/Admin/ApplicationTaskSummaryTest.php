<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use App\Services\Tasks\WorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VisaTypeSeeder;
use Database\Seeders\WorkflowStepTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationTaskSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VisaTypeSeeder::class);
        $this->seed(WorkflowStepTemplateSeeder::class);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create()->assignRole('admin');
    }

    private function makeApplication(): VisaApplication
    {
        $client = User::factory()->create()->assignRole('client');

        $application = VisaApplication::create([
            'user_id'                => $client->id,
            'visa_type_id'           => VisaType::first()->id,
            'status'                 => 'pending_review',
            'full_name'              => $client->name,
            'email'                  => $client->email,
            'phone'                  => '+1555000123',
            'nationality'            => 'Jordanian',
            'country_of_residence'   => 'UAE',
            'job_title'              => 'Engineer',
            'employment_type'        => 'employed',
            'monthly_income'         => 5000,
            'adults_count'           => 1,
            'children_count'         => 0,
            'application_start_date' => now()->addDays(30)->toDateString(),
            'agreed_to_terms'        => true,
        ]);

        app(WorkflowService::class)->seedTasksForApplication($application);

        return $application->fresh(['tasks']);
    }

    public function test_admin_applications_index_shows_task_summary(): void
    {
        $admin    = $this->makeAdmin();
        $app      = $this->makeApplication(); // seeds 6 tasks, first is in_progress
        $workflow = app(WorkflowService::class);

        // Advance 2 tasks to completed
        $task1 = $app->tasks->firstWhere('position', 1);
        $workflow->advanceTask($task1, null);

        $app   = $app->fresh(['tasks']);
        $task2 = $app->tasks->firstWhere('position', 2);
        $workflow->advanceTask($task2, null);

        $this->actingAs($admin)
            ->get(route('admin.applications.index'))
            ->assertOk()
            ->assertSee('2 / 6');
    }

    public function test_admin_applications_index_shows_zero_summary_when_no_tasks(): void
    {
        $admin  = $this->makeAdmin();
        $client = User::factory()->create()->assignRole('client');

        // Create application but deliberately skip task seeding
        VisaApplication::create([
            'user_id'                => $client->id,
            'visa_type_id'           => VisaType::first()->id,
            'status'                 => 'pending_review',
            'full_name'              => $client->name,
            'email'                  => $client->email,
            'phone'                  => '+1555000123',
            'nationality'            => 'Jordanian',
            'country_of_residence'   => 'UAE',
            'job_title'              => 'Engineer',
            'employment_type'        => 'employed',
            'monthly_income'         => 5000,
            'adults_count'           => 1,
            'children_count'         => 0,
            'application_start_date' => now()->addDays(30)->toDateString(),
            'agreed_to_terms'        => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.applications.index'))
            ->assertOk()
            ->assertSee('0 / 0');
    }

    public function test_non_admin_cannot_access_applications_index(): void
    {
        $client = User::factory()->create()->assignRole('client');

        $this->actingAs($client)
            ->get(route('admin.applications.index'))
            ->assertForbidden();
    }
}
