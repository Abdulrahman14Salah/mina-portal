<?php

namespace Tests\Feature\Tasks;

use App\Models\ApplicationTask;
use App\Models\TaskAnswer;
use App\Models\TaskQuestion;
use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use App\Models\WorkflowSection;
use App\Models\WorkflowTask;
use App\Services\Tasks\TaskAnswerService;
use App\Services\Tasks\WorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VisaTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VisaTypeSeeder::class);
    }

    private function makeApplicationWithTask(string $taskType): array
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

        $section = WorkflowSection::create(['visa_type_id' => $visaType->id, 'name' => 'Test Section', 'position' => 99]);
        $workflowTask = WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'Test Task', 'type' => $taskType, 'position' => 99]);

        app(WorkflowService::class)->seedTasksForApplication($application);

        $appTask = ApplicationTask::where('application_id', $application->id)->first();

        return [$client, $application, $workflowTask, $appTask];
    }

    // ── Pending redirect ───────────────────────────────────────────────────────

    public function test_pending_task_redirects_to_dashboard(): void
    {
        [$client, $application, $workflowTask, $firstTask] = $this->makeApplicationWithTask('info');

        $pendingTask = ApplicationTask::create([
            'application_id' => $application->id,
            'workflow_task_id' => $workflowTask->id,
            'position' => 2,
            'name' => 'Locked Task',
            'type' => 'info',
            'status' => 'pending',
        ]);

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $pendingTask]))
            ->assertRedirect(route('client.dashboard'));
    }

    // ── Question task ──────────────────────────────────────────────────────────

    public function test_question_task_shows_questions_form(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('question');

        TaskQuestion::create(['workflow_task_id' => $workflowTask->id, 'prompt' => 'What is your name?', 'required' => true, 'position' => 1]);

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertOk()
            ->assertSee('What is your name?')
            ->assertSee('name="answers[', false);
    }

    public function test_question_task_prepopulates_existing_answers(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('question');

        $q = TaskQuestion::create(['workflow_task_id' => $workflowTask->id, 'prompt' => 'Q?', 'required' => true, 'position' => 1]);
        TaskAnswer::create(['application_task_id' => $appTask->id, 'task_question_id' => $q->id, 'answer' => 'My stored answer']);

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertOk()
            ->assertSee('My stored answer');
    }

    public function test_question_task_approved_shows_readonly_answers(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('question');

        $q = TaskQuestion::create(['workflow_task_id' => $workflowTask->id, 'prompt' => 'Q?', 'required' => true, 'position' => 1]);
        TaskAnswer::create(['application_task_id' => $appTask->id, 'task_question_id' => $q->id, 'answer' => 'Final answer']);
        $appTask->update(['status' => 'approved']);

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertOk()
            ->assertSee('Final answer')
            ->assertDontSee('name="answers[', false);
    }

    public function test_question_task_with_no_questions_shows_message(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('question');

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertOk()
            ->assertSee(__('tasks.no_questions_defined'));
    }

    public function test_rejected_question_task_auto_reopens_on_submit(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('question');

        $q = TaskQuestion::create(['workflow_task_id' => $workflowTask->id, 'prompt' => 'Q?', 'required' => true, 'position' => 1]);
        $appTask->update(['status' => 'rejected', 'rejection_reason' => 'Please redo']);

        $this->actingAs($client)
            ->post(route('client.tasks.answers.submit', [$application, $appTask]), [
                'answers' => [$q->id => 'Revised answer'],
            ])
            ->assertRedirect();

        $this->assertSame('in_progress', $appTask->fresh()->status);
        $this->assertNull($appTask->fresh()->rejection_reason);
    }

    // ── Payment task ───────────────────────────────────────────────────────────

    public function test_payment_task_shows_upload_form(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('payment');

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertOk()
            ->assertSee(route('client.tasks.receipt.upload', [$application, $appTask]), false);
    }

    public function test_payment_task_shows_existing_receipt_as_download_link(): void
    {
        Storage::fake('local');

        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('payment');

        $file = UploadedFile::fake()->create('my-receipt.pdf', 100, 'application/pdf');
        app(TaskAnswerService::class)->uploadReceipt($appTask, $file, $client);

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertOk()
            ->assertSee('my-receipt.pdf');
    }

    public function test_payment_task_approved_shows_readonly_receipt(): void
    {
        Storage::fake('local');

        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('payment');

        $file = UploadedFile::fake()->create('approved-receipt.pdf', 100, 'application/pdf');
        app(TaskAnswerService::class)->uploadReceipt($appTask, $file, $client);
        $appTask->update(['status' => 'approved']);

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertOk()
            ->assertSee('approved-receipt.pdf')
            ->assertDontSee('name="receipt"', false);
    }

    public function test_rejected_payment_task_auto_reopens_on_receipt_upload(): void
    {
        Storage::fake('local');

        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('payment');
        $appTask->update(['status' => 'rejected', 'rejection_reason' => 'Wrong receipt']);

        $file = UploadedFile::fake()->create('new-receipt.pdf', 100, 'application/pdf');

        $this->actingAs($client)
            ->post(route('client.tasks.receipt.upload', [$application, $appTask]), [
                'receipt' => $file,
            ])
            ->assertRedirect();

        $this->assertSame('in_progress', $appTask->fresh()->status);
        $this->assertNull($appTask->fresh()->rejection_reason);
    }

    // ── Info task ──────────────────────────────────────────────────────────────

    public function test_info_task_shows_no_form(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('info');

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertOk()
            ->assertSee(__('tasks.info_task_note'));
    }

    // ── Security ───────────────────────────────────────────────────────────────

    public function test_client_cannot_view_another_clients_task(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('info');

        $otherClient = User::factory()->create()->assignRole('client');

        $this->actingAs($otherClient)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertForbidden();
    }
}
