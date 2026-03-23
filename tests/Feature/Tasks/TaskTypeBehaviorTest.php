<?php

namespace Tests\Feature\Tasks;

use App\Models\ApplicationTask;
use App\Models\Document;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class TaskTypeBehaviorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VisaTypeSeeder::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeApplicationWithTask(string $taskType): array
    {
        $visaType = VisaType::first();
        $client   = User::factory()->create()->assignRole('client');

        $application = VisaApplication::create([
            'user_id'                => $client->id,
            'visa_type_id'           => $visaType->id,
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

        $section      = WorkflowSection::create(['visa_type_id' => $visaType->id, 'name' => 'Test Section', 'position' => 1]);
        $workflowTask = WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'Test Task', 'type' => $taskType, 'position' => 1]);

        app(WorkflowService::class)->seedTasksForApplication($application);

        $appTask = ApplicationTask::where('application_id', $application->id)->first();

        return [$client, $application, $workflowTask, $appTask];
    }

    // ── US1: Question task answer submission ──────────────────────────────────

    public function test_client_can_submit_answers_for_question_task(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('question');

        $q1 = TaskQuestion::create(['workflow_task_id' => $workflowTask->id, 'prompt' => 'What is your name?', 'required' => true, 'position' => 1]);
        $q2 = TaskQuestion::create(['workflow_task_id' => $workflowTask->id, 'prompt' => 'What is your job?',  'required' => true, 'position' => 2]);

        app(TaskAnswerService::class)->submitAnswers($appTask, [
            $q1->id => 'John Doe',
            $q2->id => 'Engineer',
        ], $client);

        $this->assertSame(2, TaskAnswer::where('application_task_id', $appTask->id)->count());
        $this->assertSame('John Doe', TaskAnswer::where('application_task_id', $appTask->id)->where('task_question_id', $q1->id)->value('answer'));
        $this->assertSame('Engineer', TaskAnswer::where('application_task_id', $appTask->id)->where('task_question_id', $q2->id)->value('answer'));
    }

    public function test_submitting_answers_does_not_change_task_status(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('question');

        $q = TaskQuestion::create(['workflow_task_id' => $workflowTask->id, 'prompt' => 'Q?', 'required' => true, 'position' => 1]);

        app(TaskAnswerService::class)->submitAnswers($appTask, [$q->id => 'Answer'], $client);

        $this->assertSame('in_progress', $appTask->fresh()->status);
    }

    public function test_client_can_update_answers_while_task_is_in_progress(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('question');

        $q = TaskQuestion::create(['workflow_task_id' => $workflowTask->id, 'prompt' => 'Q?', 'required' => true, 'position' => 1]);

        app(TaskAnswerService::class)->submitAnswers($appTask, [$q->id => 'First answer'], $client);
        app(TaskAnswerService::class)->submitAnswers($appTask, [$q->id => 'Updated answer'], $client);

        $this->assertSame(1, TaskAnswer::where('application_task_id', $appTask->id)->count());
        $this->assertSame('Updated answer', TaskAnswer::where('application_task_id', $appTask->id)->value('answer'));
    }

    public function test_submitting_answers_to_approved_task_throws_exception(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('question');

        $q = TaskQuestion::create(['workflow_task_id' => $workflowTask->id, 'prompt' => 'Q?', 'required' => true, 'position' => 1]);

        $appTask->update(['status' => 'approved']);

        $this->expectException(InvalidArgumentException::class);

        app(TaskAnswerService::class)->submitAnswers($appTask->fresh(), [$q->id => 'Late answer'], $client);
    }

    public function test_audit_log_created_on_answer_submission(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('question');

        $q = TaskQuestion::create(['workflow_task_id' => $workflowTask->id, 'prompt' => 'Q?', 'required' => true, 'position' => 1]);

        app(TaskAnswerService::class)->submitAnswers($appTask, [$q->id => 'Answer'], $client);

        $this->assertSame(1, DB::table('audit_logs')->where('event', 'task_answers_submitted')->count());
    }

    // ── US2: Payment task receipt upload ──────────────────────────────────────

    public function test_client_can_upload_receipt_for_payment_task(): void
    {
        Storage::fake('local');

        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('payment');

        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

        app(TaskAnswerService::class)->uploadReceipt($appTask, $file, $client);

        $this->assertSame(1, Document::where('application_task_id', $appTask->id)->count());
    }

    public function test_uploading_receipt_replaces_existing_receipt(): void
    {
        Storage::fake('local');

        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('payment');

        $file1 = UploadedFile::fake()->create('receipt1.pdf', 100, 'application/pdf');
        $file2 = UploadedFile::fake()->create('receipt2.pdf', 200, 'application/pdf');

        app(TaskAnswerService::class)->uploadReceipt($appTask, $file1, $client);
        app(TaskAnswerService::class)->uploadReceipt($appTask, $file2, $client);

        $this->assertSame(1, Document::where('application_task_id', $appTask->id)->count());
        $this->assertSame('receipt2.pdf', Document::where('application_task_id', $appTask->id)->value('original_filename'));
    }

    public function test_uploading_receipt_to_approved_task_throws_exception(): void
    {
        Storage::fake('local');

        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('payment');

        $appTask->update(['status' => 'approved']);

        $file = UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf');

        $this->expectException(InvalidArgumentException::class);

        app(TaskAnswerService::class)->uploadReceipt($appTask->fresh(), $file, $client);
    }

    // ── US3: Info task ────────────────────────────────────────────────────────

    public function test_info_task_has_no_answers_or_documents_after_seeding(): void
    {
        [, $application, , $appTask] = $this->makeApplicationWithTask('info');

        $this->assertSame(0, TaskAnswer::where('application_task_id', $appTask->id)->count());
        $this->assertSame(0, Document::where('application_task_id', $appTask->id)->count());
        $this->assertSame('in_progress', $appTask->status);
    }

    // ── Model relationships ───────────────────────────────────────────────────

    public function test_workflow_task_has_questions_relationship(): void
    {
        $visaType = VisaType::first();
        $section  = WorkflowSection::create(['visa_type_id' => $visaType->id, 'name' => 'S', 'position' => 1]);
        $wfTask   = WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'Q Task', 'type' => 'question', 'position' => 1]);

        TaskQuestion::create(['workflow_task_id' => $wfTask->id, 'prompt' => 'What?', 'required' => true, 'position' => 1]);
        TaskQuestion::create(['workflow_task_id' => $wfTask->id, 'prompt' => 'Why?',  'required' => false, 'position' => 2]);

        $this->assertCount(2, $wfTask->questions);
        $this->assertSame('What?', $wfTask->questions[0]->prompt);
    }
}
