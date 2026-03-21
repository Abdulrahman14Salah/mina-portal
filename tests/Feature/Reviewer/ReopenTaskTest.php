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

class ReopenTaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VisaTypeSeeder::class);
        $this->seed(WorkflowStepTemplateSeeder::class);
    }

    private function makeReviewer(): User
    {
        return User::factory()->create()->assignRole('reviewer');
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

    public function test_reviewer_can_reopen_rejected_task(): void
    {
        $reviewer = $this->makeReviewer();
        $app      = $this->makeApplication();
        $task     = $app->tasks->firstWhere('status', 'in_progress');

        app(WorkflowService::class)->rejectTask($task, 'Docs missing');
        $task->refresh();

        $this->actingAs($reviewer)
            ->post(route('reviewer.applications.tasks.reopen', [$app, $task]))
            ->assertRedirect(route('reviewer.applications.show', $app))
            ->assertSessionHas('success');

        $task->refresh();
        $this->assertEquals('in_progress', $task->status);
        $this->assertNull($task->reviewer_note);
        $this->assertNull($task->completed_at);
    }

    public function test_reopen_fails_when_task_is_not_rejected(): void
    {
        $reviewer = $this->makeReviewer();
        $app      = $this->makeApplication();
        $task     = $app->tasks->firstWhere('status', 'in_progress');

        $this->actingAs($reviewer)
            ->post(route('reviewer.applications.tasks.reopen', [$app, $task]))
            ->assertRedirect(route('reviewer.applications.show', $app))
            ->assertSessionHas('error');
    }

    public function test_reviewer_without_permission_cannot_reopen(): void
    {
        $client = User::factory()->create()->assignRole('client');
        $app    = $this->makeApplication();
        $task   = $app->tasks->firstWhere('status', 'in_progress');

        app(WorkflowService::class)->rejectTask($task, 'Docs missing');
        $task->refresh();

        $this->actingAs($client)
            ->post(route('reviewer.applications.tasks.reopen', [$app, $task]))
            ->assertForbidden();
    }

    public function test_reviewer_cannot_reopen_task_belonging_to_another_application(): void
    {
        $reviewer  = $this->makeReviewer();
        $appA      = $this->makeApplication();
        $appB      = $this->makeApplication();
        $taskFromB = $appB->tasks->firstWhere('status', 'in_progress');

        app(WorkflowService::class)->rejectTask($taskFromB, 'Docs missing');
        $taskFromB->refresh();

        $this->actingAs($reviewer)
            ->post(route('reviewer.applications.tasks.reopen', [$appA, $taskFromB]))
            ->assertNotFound();
    }

    public function test_advance_task_does_not_auto_approve_application(): void
    {
        $reviewer = $this->makeReviewer();
        $app      = $this->makeApplication();
        $task     = $app->tasks->firstWhere('status', 'in_progress');

        $this->actingAs($reviewer)
            ->post(route('reviewer.applications.tasks.advance', [$app, $task]), ['note' => null])
            ->assertRedirect();

        $this->assertNotEquals('approved', $app->fresh()->status);
    }

    public function test_reject_task_does_not_auto_reject_application(): void
    {
        $reviewer = $this->makeReviewer();
        $app      = $this->makeApplication();
        $task     = $app->tasks->firstWhere('status', 'in_progress');

        $this->actingAs($reviewer)
            ->post(route('reviewer.applications.tasks.reject', [$app, $task]), ['note' => 'Missing documents'])
            ->assertRedirect();

        $this->assertNotEquals('rejected', $app->fresh()->status);
    }
}
