# Tasks: Task Type Behavior (015)

**Branch**: `015-task-type-behavior`
**Input**: Design documents from `/specs/015-task-type-behavior/`

## Format: `[ID] [P?] [Story] Description`

> **Route pattern note**: Follow existing convention `/client/applications/{application}/tasks/{task}/...` — not `/client/tasks/{task}/...`.

---

## Phase 1: Setup (Verification — No Code Changes)

- [X] T001 Read `app/Http/Controllers/Client/TaskController.php` and `routes/web.php` to confirm existing route is `/client/applications/{application}/tasks/{task}`. Verify `show()` uses `$this->authorize('view', $application)` then `abort_if`. **No code changes.**

---

## Phase 2: Foundational (Migrations)

**⚠️ CRITICAL**: Run migrations before any model work.

- [X] T002 Create `database/migrations/2026_03_23_100001_create_task_questions_table.php` with the following exact content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_task_id')->constrained('workflow_tasks')->cascadeOnDelete();
            $table->string('prompt', 500);
            $table->boolean('required')->default(true);
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_questions');
    }
};
```

- [X] T003 Create `database/migrations/2026_03_23_100002_create_task_answers_table.php` with the following exact content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_task_id')->constrained('application_tasks')->cascadeOnDelete();
            $table->foreignId('task_question_id')->constrained('task_questions')->cascadeOnDelete();
            $table->text('answer');
            $table->timestamps();

            $table->unique(['application_task_id', 'task_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_answers');
    }
};
```

- [X] T004 Run `php artisan migrate` and confirm both tables are created with no errors.

**Checkpoint**: `task_questions` and `task_answers` tables exist in the database.

---

## Phase 3: User Story 1 — Client Submits Answers for a Question Task (Priority: P1) 🎯 MVP

**Goal**: Clients can submit answers to question tasks; answers are stored per-question; task status is unchanged until reviewer approves.

**Independent Test**: `php artisan test --filter=TaskTypeBehaviorTest` — US1 tests pass after T013.

### Implementation for User Story 1

- [X] T005 [P] [US1] Create `app/Models/TaskQuestion.php` with the following exact content:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskQuestion extends Model
{
    use HasFactory;

    protected $fillable = ['workflow_task_id', 'prompt', 'required', 'position'];

    protected $casts = [
        'required' => 'boolean',
        'position' => 'integer',
    ];

    public function workflowTask(): BelongsTo
    {
        return $this->belongsTo(WorkflowTask::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TaskAnswer::class);
    }
}
```

- [X] T006 [P] [US1] Create `app/Models/TaskAnswer.php` with the following exact content:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAnswer extends Model
{
    use HasFactory;

    protected $fillable = ['application_task_id', 'task_question_id', 'answer'];

    public function applicationTask(): BelongsTo
    {
        return $this->belongsTo(ApplicationTask::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(TaskQuestion::class, 'task_question_id');
    }
}
```

- [X] T007 [US1] Add the `questions()` relationship to `app/Models/WorkflowTask.php`. Add these two use statements at the top (after existing uses):

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

Then add this method after the existing `section()` method:

```php
    public function questions(): HasMany
    {
        return $this->hasMany(TaskQuestion::class)->orderBy('position');
    }
```

- [X] T008 [US1] Add the `answers()` relationship to `app/Models/ApplicationTask.php`. Add this use statement at the top if not already present:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

Then add this method after the existing `documents()` method:

```php
    public function answers(): HasMany
    {
        return $this->hasMany(TaskAnswer::class);
    }
```

- [X] T009 [US1] Create `app/Services/Tasks/TaskAnswerService.php` with the following exact content:

```php
<?php

namespace App\Services\Tasks;

use App\Models\ApplicationTask;
use App\Models\Document;
use App\Models\TaskAnswer;
use App\Models\User;
use App\Services\Auth\AuditLogService;
use App\Services\Documents\DocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaskAnswerService
{
    public function __construct(
        private AuditLogService $auditLog,
        private DocumentService $documentService,
    ) {}

    public function submitAnswers(ApplicationTask $task, array $answers, User $client): void
    {
        if ($task->status !== 'in_progress') {
            throw new InvalidArgumentException('Only an in_progress task can accept answer submissions.');
        }

        DB::transaction(function () use ($task, $answers): void {
            foreach ($answers as $questionId => $answerText) {
                TaskAnswer::updateOrCreate(
                    ['application_task_id' => $task->id, 'task_question_id' => (int) $questionId],
                    ['answer' => $answerText]
                );
            }
        });

        $this->auditLog->log('task_answers_submitted', $client, [
            'task_id'   => $task->id,
            'reference' => $task->application->reference_number,
        ]);
    }

    public function uploadReceipt(ApplicationTask $task, UploadedFile $file, User $client): Document
    {
        if ($task->status !== 'in_progress') {
            throw new InvalidArgumentException('Only an in_progress task can receive a receipt upload.');
        }

        $existing = $task->documents()
            ->where('source_type', 'client')
            ->whereNull('archived_at')
            ->first();

        if ($existing) {
            $this->documentService->delete($existing, $client);
        }

        return $this->documentService->upload(
            $task->application,
            $task,
            $file,
            $client,
            'client'
        );
    }
}
```

- [X] T010 [US1] Create `app/Http/Requests/Client/SubmitTaskAnswersRequest.php` with the following exact content:

```php
<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTaskAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers'   => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'string', 'max:5000'],
        ];
    }
}
```

- [X] T011 [US1] Replace the entire content of `app/Http/Controllers/Client/TaskController.php` with the following:

```php
<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\SubmitTaskAnswersRequest;
use App\Http\Requests\Client\UploadReceiptRequest;
use App\Models\ApplicationTask;
use App\Models\VisaApplication;
use App\Services\Tasks\TaskAnswerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(private TaskAnswerService $taskAnswerService) {}

    public function show(VisaApplication $application, ApplicationTask $task): View
    {
        $this->authorize('view', $application);

        abort_if($task->application_id !== $application->id, 404);

        $task->loadMissing('documents');

        return view('client.tasks.show', compact('application', 'task'));
    }

    public function submitAnswers(SubmitTaskAnswersRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
    {
        $this->authorize('view', $application);
        abort_if($task->application_id !== $application->id, 404);

        $this->taskAnswerService->submitAnswers($task, $request->input('answers'), Auth::user());

        return redirect()->back()->with('success', __('tasks.answers_submitted'));
    }

    public function uploadReceipt(UploadReceiptRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
    {
        $this->authorize('view', $application);
        abort_if($task->application_id !== $application->id, 404);

        $this->taskAnswerService->uploadReceipt($task, $request->file('receipt'), Auth::user());

        return redirect()->back()->with('success', __('tasks.receipt_uploaded'));
    }
}
```

- [X] T012 [US1] Add two new routes inside the existing `client` middleware group in `routes/web.php` immediately after the existing `client.tasks.show` route (line 46):

```php
    Route::post('/client/applications/{application}/tasks/{task}/answers', [ClientTaskController::class, 'submitAnswers'])->middleware('active')->name('client.tasks.answers.submit');
    Route::post('/client/applications/{application}/tasks/{task}/receipt', [ClientTaskController::class, 'uploadReceipt'])->middleware('active')->name('client.tasks.receipt.upload');
```

**Checkpoint**: Run `php artisan test --filter=TaskTypeBehaviorTest` — US1 tests pass.

---

## Phase 4: User Story 2 — Client Uploads Payment Receipt for a Payment Task (Priority: P1)

**Goal**: Clients can upload a receipt for a payment task; receipt is stored securely; can be replaced; reviewer marks complete.

**Independent Test**: `php artisan test --filter=TaskTypeBehaviorTest` — US2 tests pass after T013.

### Implementation for User Story 2

- [X] T013 [US2] Create `app/Http/Requests/Client/UploadReceiptRequest.php` with the following exact content:

```php
<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class UploadReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
```

> Note: `uploadReceipt()` action was already added to `TaskController` in T011 and `uploadReceipt()` service method was already added to `TaskAnswerService` in T009. Route was added in T012. No additional code changes needed for US2.

**Checkpoint**: Run `php artisan test --filter=TaskTypeBehaviorTest` — all tests pass.

---

## Phase 5: User Story 3 — Client Views an Info Task (Priority: P2)

**Goal**: Info tasks display content only; no input required; reviewer marks complete using existing `approveTask()`.

> US3 requires **no new code**. The existing `TaskController::show()` already displays the task (including `description`). The existing `WorkflowService::approveTask()` already handles reviewer completion. The Blade view determines rendering — that is Phase 4 scope. US3 is complete once the models and service exist.

**Checkpoint**: Info tasks work with existing infrastructure. No tasks for this phase.

---

## Phase 6: Polish — Tests

- [X] T014 Create `tests/Feature/Tasks/TaskTypeBehaviorTest.php` with the following exact content:

```php
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
```

- [X] T015 Run `php artisan test --filter=TaskTypeBehaviorTest` and confirm all 9 tests pass with 0 failures.

- [X] T016 Run `php artisan test` (full suite) and confirm all existing tests still pass alongside the new tests.

---

## Dependencies & Execution Order

- **T002–T004** (migrations) MUST complete before T005–T009 (models/service reference table names)
- **T005, T006** [P] can run in parallel — different files
- **T007, T008** [P] can run in parallel — different files
- **T009** depends on T005, T006 (imports TaskAnswer model)
- **T010, T013** [P] can run in parallel — different files
- **T011** depends on T009, T010, T013 (imports both Form Requests and TaskAnswerService)
- **T014** depends on all implementation tasks (tests reference all new classes)

---

## Final File State

| File | Status |
|------|--------|
| `database/migrations/2026_03_23_100001_create_task_questions_table.php` | NEW |
| `database/migrations/2026_03_23_100002_create_task_answers_table.php` | NEW |
| `app/Models/TaskQuestion.php` | NEW |
| `app/Models/TaskAnswer.php` | NEW |
| `app/Models/WorkflowTask.php` | MODIFIED — `questions()` relationship added |
| `app/Models/ApplicationTask.php` | MODIFIED — `answers()` relationship added |
| `app/Services/Tasks/TaskAnswerService.php` | NEW |
| `app/Http/Requests/Client/SubmitTaskAnswersRequest.php` | NEW |
| `app/Http/Requests/Client/UploadReceiptRequest.php` | NEW |
| `app/Http/Controllers/Client/TaskController.php` | MODIFIED — `submitAnswers()`, `uploadReceipt()` added |
| `routes/web.php` | MODIFIED — 2 new POST routes added |
| `tests/Feature/Tasks/TaskTypeBehaviorTest.php` | NEW |
