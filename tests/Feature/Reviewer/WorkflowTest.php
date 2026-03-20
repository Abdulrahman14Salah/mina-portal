<?php

namespace Tests\Feature\Reviewer;

use App\Models\ApplicationTask;
use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use App\Services\Tasks\WorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VisaTypeSeeder;
use Database\Seeders\WorkflowStepTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VisaTypeSeeder::class);
        $this->seed(WorkflowStepTemplateSeeder::class);
    }

    protected function makeReviewer(): User
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('reviewer');

        return $reviewer;
    }

    protected function makeOnboardedApplication(): VisaApplication
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $application = VisaApplication::create([
            'user_id' => $client->id,
            'visa_type_id' => VisaType::first()->id,
            'status' => 'pending_review',
            'full_name' => $client->name,
            'email' => $client->email,
            'phone' => '+1555000123',
            'nationality' => 'Jordanian',
            'country_of_residence' => 'UAE',
            'job_title' => 'Engineer',
            'employment_type' => 'employed',
            'monthly_income' => 5000,
            'adults_count' => 1,
            'children_count' => 0,
            'application_start_date' => now()->addDays(30)->toDateString(),
            'notes' => 'Review me',
            'agreed_to_terms' => true,
        ]);

        app(WorkflowService::class)->seedTasksForApplication($application);

        return $application->fresh(['tasks', 'user', 'visaType']);
    }

    public function test_reviewer_sees_active_applications_queue(): void
    {
        $reviewer = $this->makeReviewer();
        $application = $this->makeOnboardedApplication();

        $this->actingAs($reviewer)->get('/reviewer/dashboard')->assertOk()->assertSee($application->reference_number);
    }

    public function test_reviewer_can_view_application_detail(): void
    {
        $reviewer = $this->makeReviewer();
        $application = $this->makeOnboardedApplication();

        $this->actingAs($reviewer)->get(route('reviewer.applications.show', $application))->assertOk()->assertSee('Application Received');
    }

    public function test_reviewer_can_advance_task(): void
    {
        $reviewer = $this->makeReviewer();
        $application = $this->makeOnboardedApplication();
        $task = $application->tasks->firstWhere('status', 'in_progress');

        $this->actingAs($reviewer)->post(route('reviewer.applications.tasks.advance', [$application, $task]), ['note' => 'Done'])->assertRedirect();

        $this->assertSame('completed', $task->fresh()->status);
        $this->assertSame('in_progress', ApplicationTask::where('application_id', $application->id)->where('position', 2)->first()->status);
    }

    public function test_all_tasks_completed_sets_application_approved(): void
    {
        $reviewer = $this->makeReviewer();
        $application = $this->makeOnboardedApplication();

        foreach (range(1, 6) as $position) {
            $task = ApplicationTask::where('application_id', $application->id)->where('status', 'in_progress')->first();
            $this->actingAs($reviewer)->post(route('reviewer.applications.tasks.advance', [$application, $task]), ['note' => 'Done']);
        }

        $this->assertSame('approved', $application->fresh()->status);
    }

    public function test_reviewer_can_reject_task(): void
    {
        $reviewer = $this->makeReviewer();
        $application = $this->makeOnboardedApplication();
        $task = $application->tasks->firstWhere('status', 'in_progress');

        $this->actingAs($reviewer)->post(route('reviewer.applications.tasks.reject', [$application, $task]), ['note' => 'Insufficient information'])->assertRedirect();

        $this->assertSame('rejected', $task->fresh()->status);
        $this->assertSame('rejected', $application->fresh()->status);
    }

    public function test_client_cannot_advance_tasks(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');
        $application = $this->makeOnboardedApplication();
        $task = $application->tasks->firstWhere('status', 'in_progress');

        $this->actingAs($client)->post(route('reviewer.applications.tasks.advance', [$application, $task]), ['note' => 'Nope'])->assertForbidden();
    }

    public function test_unauthenticated_redirected_from_reviewer_dashboard(): void
    {
        $this->get('/reviewer/dashboard')->assertRedirect(route('login'));
    }

    public function test_reject_task_requires_reason(): void
    {
        $reviewer = $this->makeReviewer();
        $application = $this->makeOnboardedApplication();
        $task = $application->tasks->firstWhere('status', 'in_progress');

        $this->actingAs($reviewer)
            ->post(route('reviewer.applications.tasks.reject', [$application, $task]), ['note' => ''])
            ->assertSessionHasErrors('note');

        $this->assertSame('in_progress', $task->fresh()->status);
    }
}
