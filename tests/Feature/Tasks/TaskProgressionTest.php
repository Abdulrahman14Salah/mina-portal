<?php

namespace Tests\Feature\Tasks;

use App\Models\ApplicationTask;
use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use App\Models\WorkflowSection;
use App\Models\WorkflowTask;
use App\Services\Tasks\WorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VisaTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskProgressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VisaTypeSeeder::class);
    }

    private function makeApplication(int $taskCount = 2, string $type = 'info'): array
    {
        $visaType = VisaType::first();
        $client = User::factory()->create()->assignRole('client');

        $application = VisaApplication::create([
            'user_id' => $client->id,
            'visa_type_id' => $visaType->id,
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
            'agreed_to_terms' => true,
        ]);

        $section = WorkflowSection::create([
            'visa_type_id' => $visaType->id,
            'name' => 'Test Section',
            'position' => 99,
        ]);

        $workflowTasks = [];
        for ($i = 1; $i <= $taskCount; $i++) {
            $workflowTasks[] = WorkflowTask::create([
                'workflow_section_id' => $section->id,
                'name' => "Task {$i}",
                'type' => $type,
                'position' => $i,
            ]);
        }

        app(WorkflowService::class)->seedTasksForApplication($application);

        $appTasks = ApplicationTask::where('application_id', $application->id)
            ->orderBy('position')
            ->get();

        $reviewer = User::factory()->create()->assignRole('reviewer');
        $application->update(['assigned_reviewer_id' => $reviewer->id]);

        return [$client, $reviewer, $application, $appTasks];
    }

    public function test_approving_task_activates_next_task(): void
    {
        [, , $application, $appTasks] = $this->makeApplication(2);
        $appTasks[0]->update(['status' => 'pending_review']);

        app(WorkflowService::class)->approveTask($appTasks[0], null);

        $this->assertSame('approved', $appTasks[0]->fresh()->status);
        $this->assertSame('in_progress', $appTasks[1]->fresh()->status);
        $this->assertSame('in_progress', $application->fresh()->status);
    }

    public function test_approving_last_task_sets_workflow_complete(): void
    {
        [, , $application, $appTasks] = $this->makeApplication(1);
        $appTasks[0]->update(['status' => 'pending_review']);

        app(WorkflowService::class)->approveTask($appTasks[0], null);

        $this->assertSame('approved', $appTasks[0]->fresh()->status);
        $this->assertSame('workflow_complete', $application->fresh()->status);
    }

    public function test_approving_last_task_does_not_throw(): void
    {
        [, , , $appTasks] = $this->makeApplication(1);
        $appTasks[0]->update(['status' => 'pending_review']);

        $this->expectNotToPerformAssertions();

        app(WorkflowService::class)->approveTask($appTasks[0], null);
    }

    public function test_advancing_task_activates_next_task(): void
    {
        [, , $application, $appTasks] = $this->makeApplication(2);

        app(WorkflowService::class)->advanceTask($appTasks[0], null);

        $this->assertSame('approved', $appTasks[0]->fresh()->status);
        $this->assertSame('in_progress', $appTasks[1]->fresh()->status);
        $this->assertSame('in_progress', $application->fresh()->status);
    }

    public function test_advancing_last_task_sets_workflow_complete(): void
    {
        [, , $application, $appTasks] = $this->makeApplication(1);

        app(WorkflowService::class)->advanceTask($appTasks[0], null);

        $this->assertSame('approved', $appTasks[0]->fresh()->status);
        $this->assertSame('workflow_complete', $application->fresh()->status);
    }

    public function test_pending_task_submit_answers_is_rejected(): void
    {
        [$client, , $application, $appTasks] = $this->makeApplication(2, 'question');

        $pendingTask = $appTasks[1];

        $this->actingAs($client)
            ->post(route('client.tasks.answers.submit', [$application, $pendingTask]), [
                'answers' => [1 => 'some answer'],
            ])
            ->assertRedirect()
            ->assertSessionHas('error', __('tasks.task_locked'));

        $this->assertSame('pending', $pendingTask->fresh()->status);
    }

    public function test_pending_task_receipt_upload_is_rejected(): void
    {
        Storage::fake('local');

        [$client, , $application, $appTasks] = $this->makeApplication(2, 'payment');

        $pendingTask = $appTasks[1];

        $this->actingAs($client)
            ->post(route('client.tasks.receipt.upload', [$application, $pendingTask]), [
                'receipt' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('error', __('tasks.task_locked'));

        $this->assertSame('pending', $pendingTask->fresh()->status);
    }

    public function test_dashboard_shows_active_task_link(): void
    {
        [$client, , $application, $appTasks] = $this->makeApplication(2);

        $activeTask = $appTasks[0];

        $this->actingAs($client)
            ->get(route('client.dashboard', ['tab' => 'tasks']))
            ->assertOk()
            ->assertSee(route('client.tasks.show', [$application, $activeTask]), false);
    }

    public function test_dashboard_pending_task_has_no_link(): void
    {
        [$client, , $application, $appTasks] = $this->makeApplication(2);

        $pendingTask = $appTasks[1];

        $this->actingAs($client)
            ->get(route('client.dashboard', ['tab' => 'tasks']))
            ->assertOk()
            ->assertDontSee(route('client.tasks.show', [$application, $pendingTask]), false);
    }

    public function test_dashboard_shows_workflow_complete_banner(): void
    {
        [$client, , $application, $appTasks] = $this->makeApplication(1);
        $appTasks[0]->update(['status' => 'pending_review']);

        app(WorkflowService::class)->approveTask($appTasks[0], null);

        $this->actingAs($client)
            ->get(route('client.dashboard', ['tab' => 'tasks']))
            ->assertOk()
            ->assertSee(__('tasks.workflow_complete_title'));
    }
}
