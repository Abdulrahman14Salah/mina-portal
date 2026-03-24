# Implementation Plan: Task Type Behavior

**Branch**: `015-task-type-behavior` | **Date**: 2026-03-23 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/015-task-type-behavior/spec.md`

## Summary

Phase 3 of the Task-Based Visa Workflow System. Adds type-specific backend behavior to `ApplicationTask`:
- **Question tasks**: clients submit free-text answers stored in a new `task_answers` table; reviewer marks complete.
- **Payment tasks**: clients upload receipts (reusing `DocumentService`); reviewer verifies and marks complete.
- **Info tasks**: display-only; no client input; reviewer marks complete.

Two new DB tables, two new models, one new service (`TaskAnswerService`), two new Form Requests, two new routes, and one new test file.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 11
**Primary Dependencies**: `spatie/laravel-permission` v6+, `AuditLogService` (internal), `DocumentService` (internal)
**Storage**: MySQL (MAMP local, port 8889) for dev; SQLite in-memory for tests; private disk for receipt files
**Testing**: PHPUnit via `php artisan test`; `RefreshDatabase` trait
**Target Platform**: Laravel web application (server-rendered Blade)
**Project Type**: Web application — visa client portal
**Performance Goals**: Standard web request latency; file uploads synchronous
**Constraints**: Must not break existing 184+ tests; reuse `DocumentService` for file storage; no Stripe integration
**Scale/Scope**: One answer set per task; one receipt per payment task

## Constitution Check

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Modular Architecture | ✅ Pass | New `TaskAnswerService` in `App\Services\Tasks`; Form Requests in `App\Http\Requests\Client` |
| II. Separation of Concerns | ✅ Pass | All business logic in `TaskAnswerService`; `TaskController` delegates only |
| III. Database-Driven Workflows | ✅ Pass | Questions stored in `task_questions` table, not hardcoded |
| IV. API-Ready Design | ✅ Pass | All logic in service classes; controller returns structured data |
| V. Roles & Permissions | ✅ Pass | Existing `approveTask()` is reviewer-only; new client routes use `auth` + client policy |
| VII. Secure Document Handling | ✅ Pass | Receipts stored via existing `DocumentService` (private disk, validated MIME/size) |
| IX. Security by Default | ✅ Pass | Two new Form Request classes for validation; `$fillable` on new models |
| XI. Observability & Activity Logging | ✅ Pass | Audit log entries: `task_answers_submitted`, `document_uploaded`, `document_deleted` |
| XII. Testing Standards | ✅ Pass | New `TaskTypeBehaviorTest` covers all 10 acceptance scenarios |

**Complexity violations**: None.

## Project Structure

### Documentation (this feature)

```text
specs/015-task-type-behavior/
├── plan.md              ← This file
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output
├── quickstart.md        ← Phase 1 output
└── tasks.md             ← Phase 2 output (/speckit.tasks — NOT created here)
```

### Source Code

```text
app/
├── Models/
│   ├── TaskQuestion.php                           ← NEW
│   ├── TaskAnswer.php                             ← NEW
│   ├── WorkflowTask.php                           ← MODIFY (add questions() relationship)
│   └── ApplicationTask.php                        ← MODIFY (add answers() relationship)
├── Services/
│   └── Tasks/
│       ├── TaskAnswerService.php                  ← NEW
│       └── WorkflowService.php                    ← NO CHANGE (approveTask reused as-is)
├── Http/
│   ├── Controllers/
│   │   └── Client/
│   │       └── TaskController.php                 ← MODIFY (add submitAnswers, uploadReceipt)
│   └── Requests/
│       └── Client/
│           ├── SubmitTaskAnswersRequest.php        ← NEW
│           └── UploadReceiptRequest.php            ← NEW

database/
└── migrations/
    ├── YYYY_MM_DD_create_task_questions_table.php  ← NEW
    └── YYYY_MM_DD_create_task_answers_table.php    ← NEW

routes/
└── web.php                                         ← MODIFY (2 new POST routes)

tests/
└── Feature/
    └── Tasks/
        └── TaskTypeBehaviorTest.php                ← NEW
```

## Phase 0: Research Summary

See [research.md](research.md) for full decisions. Key findings:

1. **Receipts reuse DocumentService** — no new storage system; `Document` model already links to `application_task_id`.
2. **Receipt replacement** — delete-then-upload using `DocumentService::delete()` + `DocumentService::upload()`.
3. **Two new tables** — `task_questions` (blueprint-linked) and `task_answers` (unique per task+question).
4. **Answer upsert** — unique constraint on `(application_task_id, task_question_id)`; updates are safe on resubmission.
5. **New service** — `TaskAnswerService` keeps `WorkflowService` focused on status transitions only.
6. **Reviewer completion** — `WorkflowService::approveTask()` reused unchanged for all task types.
7. **Question type** — free text only in Phase 3; `input_type` column deferred.
8. **Payment instructions** — stored in `workflow_tasks.description`, already copied to `application_tasks.description`.

## Phase 1: Design

### New Migrations

#### `create_task_questions_table`

```php
Schema::create('task_questions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workflow_task_id')->constrained('workflow_tasks')->cascadeOnDelete();
    $table->string('prompt', 500);
    $table->boolean('required')->default(true);
    $table->unsignedSmallInteger('position')->default(1);
    $table->timestamps();
});
```

#### `create_task_answers_table`

```php
Schema::create('task_answers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('application_task_id')->constrained('application_tasks')->cascadeOnDelete();
    $table->foreignId('task_question_id')->constrained('task_questions')->cascadeOnDelete();
    $table->text('answer');
    $table->timestamps();

    $table->unique(['application_task_id', 'task_question_id']);
});
```

### New Models

#### `App\Models\TaskQuestion`

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

#### `App\Models\TaskAnswer`

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

### Model Modifications

#### `App\Models\WorkflowTask` — add after existing `section()` method

```php
public function questions(): HasMany
{
    return $this->hasMany(TaskQuestion::class)->orderBy('position');
}
```

#### `App\Models\ApplicationTask` — add after existing `documents()` method

```php
public function answers(): HasMany
{
    return $this->hasMany(TaskAnswer::class);
}
```

### New Service: `App\Services\Tasks\TaskAnswerService`

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

### New Form Requests

#### `App\Http\Requests\Client\SubmitTaskAnswersRequest`

```php
<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTaskAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via Policy
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

#### `App\Http\Requests\Client\UploadReceiptRequest`

```php
<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class UploadReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via Policy
    }

    public function rules(): array
    {
        return [
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
```

### Controller: `Client\TaskController` additions

Add to the existing `TaskController` class:

```php
public function submitAnswers(SubmitTaskAnswersRequest $request, ApplicationTask $task): RedirectResponse
{
    $this->authorize('submit', $task);
    $this->taskAnswerService->submitAnswers($task, $request->input('answers'), Auth::user());
    return redirect()->back()->with('success', __('tasks.answers_submitted'));
}

public function uploadReceipt(UploadReceiptRequest $request, ApplicationTask $task): RedirectResponse
{
    $this->authorize('submit', $task);
    $this->taskAnswerService->uploadReceipt($task, $request->file('receipt'), Auth::user());
    return redirect()->back()->with('success', __('tasks.receipt_uploaded'));
}
```

The constructor must inject `TaskAnswerService`:

```php
public function __construct(private TaskAnswerService $taskAnswerService) {}
```

### New Routes (add to existing client route group in `routes/web.php`)

```php
Route::post('/tasks/{task}/answers', [TaskController::class, 'submitAnswers'])
    ->name('client.tasks.answers.submit');
Route::post('/tasks/{task}/receipt', [TaskController::class, 'uploadReceipt'])
    ->name('client.tasks.receipt.upload');
```

### ApplicationTaskPolicy — add `submit` method

The existing `ApplicationTaskPolicy` needs a `submit` method so the controller's `authorize('submit', $task)` call works:

```php
public function submit(User $user, ApplicationTask $task): bool
{
    // Client can only submit to their own in-progress tasks
    return $task->application->user_id === $user->id
        && $task->status === 'in_progress';
}
```

## Complexity Tracking

No constitution violations. No complexity tracking entries required.
