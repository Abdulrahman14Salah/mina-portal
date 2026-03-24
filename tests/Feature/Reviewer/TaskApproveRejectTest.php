<?php

namespace Tests\Feature\Reviewer;

use App\Models\ApplicationTask;
use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use App\Services\Documents\DocumentService;
use App\Services\Tasks\WorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VisaTypeSeeder;
use Database\Seeders\WorkflowStepTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskApproveRejectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VisaTypeSeeder::class);
        $this->seed(WorkflowStepTemplateSeeder::class);
    }

    private function makeReviewer(): User
    {
        return User::factory()->create()->assignRole('reviewer');
    }

    private function makeApplication(User $reviewer): VisaApplication
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
            'assigned_reviewer_id'   => $reviewer->id,
        ]);

        app(WorkflowService::class)->seedTasksForApplication($application);

        return $application->fresh(['tasks']);
    }

    // ── Approve ────────────────────────────────────────────────────────────

    public function test_assigned_reviewer_can_approve_task(): void
    {
        $reviewer    = $this->makeReviewer();
        $application = $this->makeApplication($reviewer);
        $task        = $application->tasks->firstWhere('status', 'in_progress');
        $task->update(['status' => 'pending_review']);

        $this->actingAs($reviewer)
            ->post(route('reviewer.tasks.approve', $task))
            ->assertRedirect();

        $this->assertSame('approved', $task->fresh()->status);
    }

    public function test_approve_advances_next_task_to_in_progress(): void
    {
        $reviewer    = $this->makeReviewer();
        $application = $this->makeApplication($reviewer);
        $task        = $application->tasks->firstWhere('status', 'in_progress');
        $task->update(['status' => 'pending_review']);

        $this->actingAs($reviewer)
            ->post(route('reviewer.tasks.approve', $task));

        $next = ApplicationTask::where('application_id', $application->id)
            ->where('position', $task->position + 1)
            ->first();

        $this->assertSame('in_progress', $next->status);
    }

    public function test_unassigned_reviewer_cannot_approve_task(): void
    {
        $assignedReviewer   = $this->makeReviewer();
        $unassignedReviewer = $this->makeReviewer();
        $application        = $this->makeApplication($assignedReviewer);
        $task               = $application->tasks->firstWhere('status', 'in_progress');
        $task->update(['status' => 'pending_review']);

        $this->actingAs($unassignedReviewer)
            ->post(route('reviewer.tasks.approve', $task))
            ->assertForbidden();

        $this->assertSame('pending_review', $task->fresh()->status);
    }

    public function test_client_cannot_approve_task(): void
    {
        $reviewer    = $this->makeReviewer();
        $application = $this->makeApplication($reviewer);
        $task        = $application->tasks->firstWhere('status', 'in_progress');
        $task->update(['status' => 'pending_review']);
        $client      = $application->user;

        $this->actingAs($client)
            ->post(route('reviewer.tasks.approve', $task))
            ->assertForbidden();
    }

    public function test_admin_can_approve_task(): void
    {
        $admin    = User::factory()->create()->assignRole('admin');
        $reviewer = $this->makeReviewer();
        $application = $this->makeApplication($reviewer);
        $task     = $application->tasks->firstWhere('status', 'in_progress');
        $task->update(['status' => 'pending_review']);

        $this->actingAs($admin)
            ->post(route('reviewer.tasks.approve', $task))
            ->assertRedirect();

        $this->assertSame('approved', $task->fresh()->status);
    }

    // ── Reject ─────────────────────────────────────────────────────────────

    public function test_assigned_reviewer_can_reject_task_with_reason(): void
    {
        $reviewer    = $this->makeReviewer();
        $application = $this->makeApplication($reviewer);
        $task        = $application->tasks->firstWhere('status', 'in_progress');
        $task->update(['status' => 'pending_review']);

        $this->actingAs($reviewer)
            ->post(route('reviewer.tasks.reject', $task), [
                'rejection_reason' => 'Passport scan is blurry.',
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame('rejected', $task->status);
        $this->assertSame('Passport scan is blurry.', $task->rejection_reason);
    }

    public function test_reject_requires_rejection_reason(): void
    {
        $reviewer    = $this->makeReviewer();
        $application = $this->makeApplication($reviewer);
        $task        = $application->tasks->firstWhere('status', 'in_progress');
        $task->update(['status' => 'pending_review']);

        $this->actingAs($reviewer)
            ->post(route('reviewer.tasks.reject', $task), [
                'rejection_reason' => '',
            ])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame('pending_review', $task->fresh()->status);
    }

    public function test_unassigned_reviewer_cannot_reject_task(): void
    {
        $assignedReviewer   = $this->makeReviewer();
        $unassignedReviewer = $this->makeReviewer();
        $application        = $this->makeApplication($assignedReviewer);
        $task               = $application->tasks->firstWhere('status', 'in_progress');
        $task->update(['status' => 'pending_review']);

        $this->actingAs($unassignedReviewer)
            ->post(route('reviewer.tasks.reject', $task), [
                'rejection_reason' => 'Not valid.',
            ])
            ->assertForbidden();
    }

    public function test_client_cannot_reject_task(): void
    {
        $reviewer    = $this->makeReviewer();
        $application = $this->makeApplication($reviewer);
        $task        = $application->tasks->firstWhere('status', 'in_progress');
        $task->update(['status' => 'pending_review']);
        $client      = $application->user;

        $this->actingAs($client)
            ->post(route('reviewer.tasks.reject', $task), [
                'rejection_reason' => 'Trying to self-reject.',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_reject_task(): void
    {
        $admin       = User::factory()->create()->assignRole('admin');
        $reviewer    = $this->makeReviewer();
        $application = $this->makeApplication($reviewer);
        $task        = $application->tasks->firstWhere('status', 'in_progress');
        $task->update(['status' => 'pending_review']);

        $this->actingAs($admin)
            ->post(route('reviewer.tasks.reject', $task), [
                'rejection_reason' => 'Missing documents.',
            ])
            ->assertRedirect();

        $this->assertSame('rejected', $task->fresh()->status);
    }

    // ── Client re-upload resets status ─────────────────────────────────────

    public function test_client_reupload_on_rejected_task_resets_status_to_in_progress(): void
    {
        $reviewer    = $this->makeReviewer();
        $application = $this->makeApplication($reviewer);
        $task        = $application->tasks->firstWhere('status', 'in_progress');
        $client      = $application->user;

        $task->update(['status' => 'pending_review']);
        app(WorkflowService::class)->rejectTaskWithReason($task, 'Document missing.');
        $task->refresh();
        $this->assertSame('rejected', $task->status);

        $this->actingAs($client)
            ->post(route('client.documents.store'), [
                'file'                => UploadedFile::fake()->create('new_passport.pdf', 100, 'application/pdf'),
                'application_task_id' => $task->id,
            ])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame('in_progress', $task->status);
        $this->assertNull($task->rejection_reason);
    }
}
