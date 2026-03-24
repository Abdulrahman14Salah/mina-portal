# Tasks: Reviewer Validation (018)

**Input**: Design documents from `specs/018-reviewer-validation/`
**Note**: Every task includes the **exact file content** to write or the exact diff to apply. A cheaper LLM can implement each task without additional context.
**Context**: Tasks include a final deep-review phase to verify implementation correctness.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to

---

## Phase 1: Setup (Migrations)

**Purpose**: Add new columns required by all user stories.

- [ ] T001 Create migration `add_approval_mode_to_workflow_tasks` — run `php artisan make:migration add_approval_mode_to_workflow_tasks --table=workflow_tasks` then write the following content to the generated file in `database/migrations/`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_tasks', function (Blueprint $table) {
            $table->string('approval_mode', 10)->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_tasks', function (Blueprint $table) {
            $table->dropColumn('approval_mode');
        });
    }
};
```

- [ ] T002 Create migration `add_reviewer_fields_to_application_tasks` — run `php artisan make:migration add_reviewer_fields_to_application_tasks --table=application_tasks` then write:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_tasks', function (Blueprint $table) {
            $table->string('approval_mode', 10)->nullable()->after('type');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete()->after('rejection_reason');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('application_tasks', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['approval_mode', 'reviewed_by', 'reviewed_at']);
        });
    }
};
```

- [ ] T003 Run migrations — execute `php artisan migrate` and verify both new columns exist

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core model, service, and permission changes that all user stories depend on.

**⚠️ CRITICAL**: Complete this entire phase before starting Phase 3+.

- [ ] T004 [P] Update `app/Models/WorkflowTask.php` — add `approval_mode` to `$fillable`. Replace the current file with:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class WorkflowTask extends Model
{
    use HasFactory;

    public const VALID_TYPES = ['upload', 'question', 'payment', 'info'];
    public const VALID_APPROVAL_MODES = ['auto', 'manual'];

    protected $fillable = ['workflow_section_id', 'name', 'description', 'type', 'position', 'approval_mode'];

    protected $casts = [
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $task): void {
            if (! in_array($task->type, self::VALID_TYPES, true)) {
                throw new InvalidArgumentException(
                    "Invalid workflow task type '{$task->type}'. Must be one of: " . implode(', ', self::VALID_TYPES)
                );
            }

            if ($task->approval_mode !== null && ! in_array($task->approval_mode, self::VALID_APPROVAL_MODES, true)) {
                throw new InvalidArgumentException(
                    "Invalid approval_mode '{$task->approval_mode}'. Must be one of: " . implode(', ', self::VALID_APPROVAL_MODES)
                );
            }
        });
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(WorkflowSection::class, 'workflow_section_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(TaskQuestion::class)->orderBy('position');
    }
}
```

- [ ] T005 [P] Update `app/Models/ApplicationTask.php` — add `approval_mode`, `reviewed_by`, `reviewed_at`, and `reviewer()` relationship. Replace the current file with:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'workflow_step_template_id',
        'workflow_task_id',
        'position',
        'name',
        'description',
        'type',
        'approval_mode',
        'status',
        'reviewer_note',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'completed_at',
    ];

    protected $casts = [
        'position'    => 'integer',
        'completed_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'application_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowStepTemplate::class, 'workflow_step_template_id');
    }

    public function workflowTask(): BelongsTo
    {
        return $this->belongsTo(WorkflowTask::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'application_task_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TaskAnswer::class);
    }
}
```

- [ ] T006 Update `database/seeders/RolePermissionSeeder.php` — add `tasks.submit-for-review` permission and assign it to the client role. Replace the full file with:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $admin    = Role::firstOrCreate(['name' => 'admin']);
        $client   = Role::firstOrCreate(['name' => 'client']);
        $reviewer = Role::firstOrCreate(['name' => 'reviewer']);

        $permissions = [
            'users.view',
            'users.create',
            'users.edit',
            'users.deactivate',
            'roles.assign',
            'dashboard.admin',
            'dashboard.client',
            'dashboard.reviewer',
            'tasks.view',
            'tasks.advance',
            'tasks.reject',
            'tasks.submit-for-review',
            'documents.upload',
            'documents.download',
            'documents.admin-upload',
            'documents.reviewer-upload',
            'payments.pay',
            'payments.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin->syncPermissions($permissions);
        $client->syncPermissions(['dashboard.client', 'documents.upload', 'payments.pay', 'tasks.submit-for-review']);
        $reviewer->syncPermissions(['dashboard.reviewer', 'tasks.view', 'tasks.advance', 'tasks.reject', 'documents.download', 'documents.reviewer-upload']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
```

After saving, run: `php artisan db:seed --class=RolePermissionSeeder`

- [ ] T007 Replace `app/Services/Tasks/WorkflowService.php` with the full updated version — this adds `submitForReview()`, `autoCompleteTask()`, updates guards on `approveTask()` and `rejectTaskWithReason()` to accept `pending_review`, copies `approval_mode` during seeding, and sets `reviewed_by`/`reviewed_at` on approve/reject:

```php
<?php

namespace App\Services\Tasks;

use App\Mail\TaskSubmittedForReviewMail;
use App\Models\ApplicationTask;
use App\Models\User;
use App\Models\VisaApplication;
use App\Models\WorkflowSection;
use App\Models\WorkflowStepTemplate;
use App\Services\Auth\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class WorkflowService
{
    public function __construct(private AuditLogService $auditLog) {}

    public function seedTasksForApplication(VisaApplication $application): void
    {
        if ($application->tasks()->exists()) {
            return;
        }

        $hasSections = WorkflowSection::where('visa_type_id', $application->visa_type_id)->exists();

        $seeded = false;

        DB::transaction(function () use ($application, $hasSections, &$seeded): void {
            $tasks = [];
            $position = 1;

            if ($hasSections) {
                $sections = WorkflowSection::where('visa_type_id', $application->visa_type_id)
                    ->orderBy('position')
                    ->with(['tasks' => fn ($q) => $q->orderBy('position')])
                    ->get();

                foreach ($sections as $section) {
                    foreach ($section->tasks as $workflowTask) {
                        $tasks[] = ApplicationTask::create([
                            'application_id'           => $application->id,
                            'workflow_step_template_id' => null,
                            'workflow_task_id'          => $workflowTask->id,
                            'position'                 => $position++,
                            'name'                     => $workflowTask->name,
                            'description'              => $workflowTask->description,
                            'type'                     => $workflowTask->type,
                            'approval_mode'            => $workflowTask->approval_mode,
                            'status'                   => 'pending',
                        ]);
                    }
                }
            } else {
                $templates = WorkflowStepTemplate::where('visa_type_id', $application->visa_type_id)
                    ->orderBy('position')
                    ->get();

                if ($templates->isEmpty()) {
                    return;
                }

                foreach ($templates as $template) {
                    $tasks[] = ApplicationTask::create([
                        'application_id'           => $application->id,
                        'workflow_step_template_id' => $template->id,
                        'position'                 => $template->position,
                        'name'                     => $template->name,
                        'description'              => $template->description,
                        'type'                     => 'upload',
                        'status'                   => 'pending',
                    ]);
                }
            }

            if (! empty($tasks)) {
                $tasks[0]->update(['status' => 'in_progress']);
                $application->update(['status' => 'in_progress']);
                $seeded = true;
            }
        });

        if ($seeded) {
            $this->auditLog->log('workflow_started', $application->user()->first(), ['reference' => $application->reference_number]);
        }
    }

    /**
     * Client explicitly submits a task for reviewer attention.
     * Transitions: in_progress → pending_review
     * Sends email notification to assigned reviewer.
     */
    public function submitForReview(ApplicationTask $task): void
    {
        DB::transaction(function () use ($task): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'in_progress') {
                throw new InvalidArgumentException('Only an in_progress task can be submitted for review.');
            }

            $task->update(['status' => 'pending_review']);
        });

        $this->auditLog->log('task_submitted_for_review', $this->actor(), [
            'task_id'    => $task->id,
            'task_name'  => $task->name,
            'reference'  => $task->application->reference_number,
        ]);

        $reviewer = $task->application->assignedReviewer;

        if ($reviewer) {
            Mail::to($reviewer->email)->queue(new TaskSubmittedForReviewMail($task));
        }
    }

    /**
     * Auto-completes a question task when approval_mode = 'auto'.
     * Transitions: in_progress → approved (bypasses pending_review entirely).
     * Called internally by TaskAnswerService after answer submission.
     */
    public function autoCompleteTask(ApplicationTask $task): void
    {
        $workflowComplete = false;

        DB::transaction(function () use ($task, &$workflowComplete): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'in_progress') {
                throw new InvalidArgumentException('Only an in_progress task can be auto-completed.');
            }

            $task->update([
                'status'      => 'approved',
                'completed_at' => now(),
                'reviewed_at' => now(),
            ]);

            $nextTask = ApplicationTask::where('application_id', $task->application_id)
                ->where('position', '>', $task->position)
                ->orderBy('position')
                ->first();

            if ($nextTask) {
                $nextTask->update(['status' => 'in_progress']);
            } else {
                $task->application->update(['status' => 'workflow_complete']);
                $workflowComplete = true;
            }
        });

        $this->auditLog->log('task_auto_completed', $this->actor(), [
            'task'      => $task->name,
            'reference' => $task->application->reference_number,
        ]);

        if ($workflowComplete) {
            $this->auditLog->log('workflow_tasks_complete', $this->actor(), [
                'reference' => $task->application->reference_number,
            ]);
        }
    }

    public function advanceTask(ApplicationTask $task, ?string $note): void
    {
        $workflowComplete = false;

        DB::transaction(function () use ($task, $note, &$workflowComplete): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'in_progress') {
                throw new InvalidArgumentException('Only an in_progress task can be advanced.');
            }

            $task->update([
                'status'      => 'approved',
                'completed_at' => now(),
                'reviewer_note' => $note,
            ]);

            $nextTask = ApplicationTask::where('application_id', $task->application_id)
                ->where('position', '>', $task->position)
                ->orderBy('position')
                ->first();

            if ($nextTask) {
                $nextTask->update(['status' => 'in_progress']);
            } else {
                $task->application->update(['status' => 'workflow_complete']);
                $workflowComplete = true;
            }
        });

        $this->auditLog->log('task_completed', $this->actor(), [
            'task'      => $task->name,
            'reference' => $task->application->reference_number,
        ]);

        if ($workflowComplete) {
            $this->auditLog->log('workflow_tasks_complete', $this->actor(), [
                'reference' => $task->application->reference_number,
            ]);
        }
    }

    /**
     * Reviewer approves a task.
     * Requires: pending_review status.
     * Sets reviewed_by and reviewed_at for audit trail.
     */
    public function approveTask(ApplicationTask $task, ?string $note): void
    {
        $workflowComplete = false;
        $actor = $this->actor();

        DB::transaction(function () use ($task, $note, $actor, &$workflowComplete): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'pending_review') {
                throw new InvalidArgumentException('Only a pending_review task can be approved.');
            }

            $task->update([
                'status'        => 'approved',
                'completed_at'  => now(),
                'reviewer_note' => $note,
                'reviewed_by'   => $actor?->id,
                'reviewed_at'   => now(),
            ]);

            $nextTask = ApplicationTask::where('application_id', $task->application_id)
                ->where('position', '>', $task->position)
                ->orderBy('position')
                ->first();

            if ($nextTask) {
                $nextTask->update(['status' => 'in_progress']);
            } else {
                $task->application->update(['status' => 'workflow_complete']);
                $workflowComplete = true;
            }
        });

        $this->auditLog->log('task_approved', $actor, [
            'task'      => $task->name,
            'reference' => $task->application->reference_number,
        ]);

        if ($workflowComplete) {
            $this->auditLog->log('workflow_tasks_complete', $actor, [
                'reference' => $task->application->reference_number,
            ]);
        }
    }

    public function rejectTask(ApplicationTask $task, ?string $note): void
    {
        DB::transaction(function () use ($task, $note): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'in_progress') {
                throw new InvalidArgumentException('Only an in_progress task can be rejected.');
            }

            $task->update([
                'status'       => 'rejected',
                'completed_at' => now(),
                'reviewer_note' => $note,
            ]);
        });

        $this->auditLog->log('task_rejected', $this->actor(), ['task' => $task->name, 'reference' => $task->application->reference_number]);
    }

    /**
     * Reviewer rejects a task with a reason.
     * Requires: pending_review status.
     * Sets reviewed_by and reviewed_at for audit trail.
     */
    public function rejectTaskWithReason(ApplicationTask $task, string $reason): void
    {
        $actor = $this->actor();

        DB::transaction(function () use ($task, $reason, $actor): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'pending_review') {
                throw new InvalidArgumentException('Only a pending_review task can be rejected.');
            }

            $task->update([
                'status'           => 'rejected',
                'rejection_reason' => $reason,
                'completed_at'     => now(),
                'reviewed_by'      => $actor?->id,
                'reviewed_at'      => now(),
            ]);
        });

        $this->auditLog->log('task_rejected', $actor, [
            'task'      => $task->name,
            'reference' => $task->application->reference_number,
        ]);
    }

    public function reopenTask(ApplicationTask $task): void
    {
        DB::transaction(function () use ($task): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'rejected') {
                throw new InvalidArgumentException('Only a rejected task can be re-opened.');
            }

            $task->update([
                'status'           => 'in_progress',
                'reviewer_note'    => null,
                'rejection_reason' => null,
                'completed_at'     => null,
            ]);
        });

        $this->auditLog->log('task_reopened', $this->actor(), [
            'task_id'        => $task->id,
            'application_id' => $task->application_id,
            'task_name'      => $task->name,
        ]);
    }

    private function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
```

- [ ] T008 Update `app/Services/Tasks/TaskAnswerService.php` — inject `WorkflowService` and call `autoCompleteTask()` after answer submission when `approval_mode === 'auto'`. Replace the full file:

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
        private WorkflowService $workflowService,
    ) {}

    public function submitAnswers(ApplicationTask $task, array $answers, User $client): void
    {
        if (! in_array($task->status, ['in_progress', 'rejected'], true)) {
            throw new InvalidArgumentException('Only an in_progress or rejected task can accept answer submissions.');
        }

        DB::transaction(function () use ($task, $answers): void {
            foreach ($answers as $questionId => $answerText) {
                TaskAnswer::updateOrCreate(
                    ['application_task_id' => $task->id, 'task_question_id' => (int) $questionId],
                    ['answer' => $answerText]
                );
            }

            if ($task->status === 'rejected') {
                $task->update(['status' => 'in_progress', 'rejection_reason' => null]);
            }
        });

        $this->auditLog->log('task_answers_submitted', $client, [
            'task_id'   => $task->id,
            'reference' => $task->application->reference_number,
        ]);

        // Auto-complete question tasks that don't require reviewer approval
        if ($task->type === 'question' && $task->approval_mode === 'auto') {
            $task->refresh();
            $this->workflowService->autoCompleteTask($task);
        }
    }

    public function uploadReceipt(ApplicationTask $task, UploadedFile $file, User $client): Document
    {
        if (! in_array($task->status, ['in_progress', 'rejected'], true)) {
            throw new InvalidArgumentException('Only an in_progress or rejected task can receive a receipt upload.');
        }

        $existing = $task->documents()
            ->where('source_type', 'client')
            ->whereNull('archived_at')
            ->first();

        $document = $this->documentService->upload(
            $task->application,
            $task,
            $file,
            $client,
            'client'
        );

        if ($existing) {
            $this->documentService->delete($existing, $client);
        }

        return $document;
    }
}
```

**Checkpoint**: Migrations applied, models updated, WorkflowService and TaskAnswerService updated. Run `php artisan test` — existing tests should still pass (only `approveTask` guard changed; `rejectTask` guard kept as `in_progress` for legacy `advance` path).

---

## Phase 3: User Story 1 — Reviewer Approves a Payment Task (Priority: P1) 🎯 MVP

**Goal**: Reviewer can approve a `pending_review` payment task; next task unlocks automatically.

**Independent Test**: Create application with a payment task at `pending_review` status → log in as reviewer → click Approve → verify next task becomes `in_progress`.

- [ ] T009 [P] [US1] Add `submitForReview` method to `app/Policies/ApplicationTaskPolicy.php`. Replace the full file:

```php
<?php

namespace App\Policies;

use App\Models\ApplicationTask;
use App\Models\User;

class ApplicationTaskPolicy
{
    public function advance(User $user, ApplicationTask $task): bool
    {
        return $user->can('tasks.advance');
    }

    public function approve(User $user, ApplicationTask $task): bool
    {
        if ($user->can('dashboard.admin')) {
            return true;
        }

        if (! $user->can('tasks.advance')) {
            return false;
        }

        return $task->application->assigned_reviewer_id === $user->id;
    }

    public function reject(User $user, ApplicationTask $task): bool
    {
        if ($user->can('dashboard.admin')) {
            return true;
        }

        if (! $user->can('tasks.reject')) {
            return false;
        }

        return $task->application->assigned_reviewer_id === $user->id;
    }

    public function reopen(User $user, ApplicationTask $task): bool
    {
        return $user->can('tasks.advance');
    }

    public function submitForReview(User $user, ApplicationTask $task): bool
    {
        return $user->can('tasks.submit-for-review')
            && $task->application->user_id === $user->id
            && $task->status === 'in_progress';
    }
}
```

- [ ] T010 [P] [US1] Create `app/Http/Requests/Client/SubmitTaskForReviewRequest.php`:

```php
<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class SubmitTaskForReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via $this->authorize()
    }

    public function rules(): array
    {
        return []; // No body required — the action itself is the intent
    }
}
```

- [ ] T011 [US1] Update `app/Http/Controllers/Client/TaskController.php` — inject `WorkflowService` and add `submitForReview()` action. Replace the full file:

```php
<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\SubmitTaskForReviewRequest;
use App\Http\Requests\Client\SubmitTaskAnswersRequest;
use App\Http\Requests\Client\UploadReceiptRequest;
use App\Models\ApplicationTask;
use App\Models\VisaApplication;
use App\Services\Tasks\TaskAnswerService;
use App\Services\Tasks\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(
        private TaskAnswerService $taskAnswerService,
        private WorkflowService $workflowService,
    ) {}

    public function show(VisaApplication $application, ApplicationTask $task): View|RedirectResponse
    {
        if ($task->status === 'pending') {
            return redirect()->route('client.dashboard');
        }

        $this->authorize('view', $application);

        abort_if($task->application_id !== $application->id, 404);

        $task->loadMissing(['documents', 'answers', 'workflowTask.questions']);

        $answers = $task->answers->keyBy('task_question_id');

        return view('client.tasks.show', compact('application', 'task', 'answers'));
    }

    public function submitAnswers(SubmitTaskAnswersRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
    {
        $this->authorize('view', $application);
        abort_if($task->application_id !== $application->id, 404);

        if ($task->status === 'pending') {
            return redirect()->back()->with('error', __('tasks.task_locked'));
        }

        $this->taskAnswerService->submitAnswers($task, $request->input('answers'), Auth::user());

        return redirect()->back()->with('success', __('tasks.answers_submitted'));
    }

    public function uploadReceipt(UploadReceiptRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
    {
        $this->authorize('view', $application);
        abort_if($task->application_id !== $application->id, 404);

        if ($task->status === 'pending') {
            return redirect()->back()->with('error', __('tasks.task_locked'));
        }

        $this->taskAnswerService->uploadReceipt($task, $request->file('receipt'), Auth::user());

        return redirect()->back()->with('success', __('tasks.receipt_uploaded'));
    }

    public function submitForReview(SubmitTaskForReviewRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
    {
        $this->authorize('view', $application);
        $this->authorize('submitForReview', $task);
        abort_if($task->application_id !== $application->id, 404);

        try {
            $this->workflowService->submitForReview($task);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('tasks.submitted_for_review'));
    }
}
```

- [ ] T012 [US1] Add the `submit-for-review` route to `routes/web.php`. Add this line immediately after the existing `receipt` route (line 48), inside the `auth, verified` middleware group:

```php
// ADD THIS LINE after the uploadReceipt route:
Route::post('/client/applications/{application}/tasks/{task}/submit-for-review', [ClientTaskController::class, 'submitForReview'])->middleware('active')->name('client.tasks.submit-for-review');
```

The relevant section of `routes/web.php` after the change:
```php
Route::post('/client/applications/{application}/tasks/{task}/answers', [ClientTaskController::class, 'submitAnswers'])->middleware('active')->name('client.tasks.answers.submit');
Route::post('/client/applications/{application}/tasks/{task}/receipt', [ClientTaskController::class, 'uploadReceipt'])->middleware('active')->name('client.tasks.receipt.upload');
Route::post('/client/applications/{application}/tasks/{task}/submit-for-review', [ClientTaskController::class, 'submitForReview'])->middleware('active')->name('client.tasks.submit-for-review');
```

- [ ] T013 [US1] Create `app/Mail/TaskSubmittedForReviewMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\ApplicationTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskSubmittedForReviewMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ApplicationTask $task) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('tasks.submitted_for_review_subject', [
                'reference' => $this->task->application->reference_number,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.task-submitted-for-review',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
```

- [ ] T014 [US1] Create `resources/views/mail/task-submitted-for-review.blade.php`:

```html
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('tasks.submitted_for_review_subject', ['reference' => $task->application->reference_number]) }}</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 32px; border: 1px solid #e5e7eb;">

        <h2 style="color: #1f2937; margin-top: 0;">{{ __('tasks.submitted_for_review_heading') }}</h2>

        <p style="color: #6b7280;">{{ __('tasks.submitted_for_review_intro', ['client' => $task->application->user->name]) }}</p>

        <table style="width: 100%; border-collapse: collapse; margin: 16px 0;">
            <tr>
                <td style="padding: 8px 12px; border: 1px solid #e5e7eb; font-weight: bold; color: #374151; background: #f9fafb; width: 40%;">
                    {{ __('reviewer.reference') }}
                </td>
                <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #1f2937; font-family: monospace;">
                    {{ $task->application->reference_number }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 12px; border: 1px solid #e5e7eb; font-weight: bold; color: #374151; background: #f9fafb;">
                    {{ __('tasks.task_name_label') }}
                </td>
                <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #1f2937;">
                    {{ $task->name }}
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 12px; border: 1px solid #e5e7eb; font-weight: bold; color: #374151; background: #f9fafb;">
                    {{ __('tasks.task_type_label') }}
                </td>
                <td style="padding: 8px 12px; border: 1px solid #e5e7eb; color: #1f2937;">
                    {{ ucfirst($task->type) }}
                </td>
            </tr>
        </table>

        <p style="color: #6b7280;">{{ __('tasks.submitted_for_review_cta') }}</p>

        <p style="margin-top: 32px; color: #9ca3af; font-size: 12px;">{{ config('app.name') }}</p>
    </div>
</body>
</html>
```

- [ ] T015 [US1] Update `app/Http/Controllers/Reviewer/ApplicationController.php` — change `$activeTask` detection from `in_progress` to `pending_review`. Replace only the `show()` method body (exact replacement):

**Find** (line 26):
```php
$activeTask = $application->tasks->firstWhere('status', 'in_progress');
```

**Replace with**:
```php
$activeTask = $application->tasks->firstWhere('status', 'pending_review');
```

The full updated `show()` method:
```php
public function show(VisaApplication $application): View
{
    $this->authorize('view', $application);

    $application->loadMissing(['visaType', 'user', 'tasks' => fn ($q) => $q->orderBy('position')->with(['template', 'documents' => fn ($d) => $d->with('uploader')])]);
    $activeTask = $application->tasks->firstWhere('status', 'pending_review');

    return view('reviewer.applications.show', compact('application', 'activeTask'));
}
```

- [ ] T016 [US1] Update `resources/views/reviewer/applications/show.blade.php` — add `pending_review` styling to the task card border and status badge, and update the active task conditional. Replace the full file:

```blade
<x-reviewer-layout>
    <div class="py-12">
        <div class="max-w-4xl mx-auto space-y-6 sm:px-6 lg:px-8">

            {{-- Application Header --}}
            <div class="grid gap-4 rounded-lg bg-white p-6 shadow-sm text-sm text-gray-700 md:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('reviewer.reference') }}</p>
                    <p class="mt-1 font-medium text-gray-900 font-mono">{{ $application->reference_number }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('reviewer.client_label') }}</p>
                    <p class="mt-1 font-medium text-gray-900">{{ $application->user->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('reviewer.visa_type_label') }}</p>
                    <p class="mt-1 font-medium text-gray-900">{{ $application->visaType->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('reviewer.status_label') }}</p>
                    <p class="mt-1 inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">{{ __('client.status_' . $application->status) }}</p>
                </div>
            </div>

            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
            @endif

            @if (session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
            @endif

            {{-- Workflow Tasks --}}
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('reviewer.workflow_progress') }}</h3>

                @foreach ($application->tasks->sortBy('position') as $task)
                    <div class="rounded-lg bg-white p-6 shadow-sm
                        {{ $task->status === 'approved'        ? 'border-l-4 border-green-500' : '' }}
                        {{ $task->status === 'in_progress'     ? 'border-l-4 border-indigo-500 ring-1 ring-indigo-100' : '' }}
                        {{ $task->status === 'pending_review'  ? 'border-l-4 border-amber-500 ring-1 ring-amber-100' : '' }}
                        {{ $task->status === 'rejected'        ? 'border-l-4 border-red-500' : '' }}">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs text-gray-400 mb-1">{{ __('tasks.step_number', ['number' => $task->position]) }}</p>
                                <h4 class="font-semibold text-gray-900">{{ $task->name }}</h4>
                                @if ($task->description)
                                    <p class="mt-1 text-sm text-gray-500">{{ $task->description }}</p>
                                @endif
                                @if ($task->status === 'approved' && $task->completed_at)
                                    <p class="mt-2 text-xs text-green-600">{{ __('tasks.completed_on', ['date' => $task->completed_at->format('d M Y')]) }}</p>
                                @endif
                                @if ($task->reviewer_note)
                                    <div class="mt-3 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600">
                                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('reviewer.reviewer_note') }}</p>
                                        <p>{{ $task->reviewer_note }}</p>
                                    </div>
                                @endif
                                @if ($task->rejection_reason)
                                    <div class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-red-400">{{ __('tasks.rejection_reason') }}</p>
                                        <p>{{ $task->rejection_reason }}</p>
                                    </div>
                                @endif
                            </div>
                            <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium
                                {{ $task->status === 'approved'       ? 'bg-green-100 text-green-700'  : '' }}
                                {{ $task->status === 'in_progress'    ? 'bg-indigo-100 text-indigo-700': '' }}
                                {{ $task->status === 'pending_review' ? 'bg-amber-100 text-amber-700'  : '' }}
                                {{ $task->status === 'pending'        ? 'bg-gray-100 text-gray-600'    : '' }}
                                {{ $task->status === 'rejected'       ? 'bg-red-100 text-red-700'      : '' }}">
                                {{ __('tasks.status_' . $task->status) }}
                            </span>
                        </div>

                        @if ($task->status === 'rejected')
                            <div class="mt-4">
                                <form method="POST" action="{{ route('reviewer.applications.tasks.reopen', [$application, $task]) }}">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-500">
                                        {{ __('tasks.reopen') }}
                                    </button>
                                </form>
                            </div>
                        @elseif ($activeTask && $task->id === $activeTask->id)
                            <div class="mt-4 rounded-lg bg-amber-50/60 p-4">
                                <p class="mb-4 text-sm font-medium text-amber-900">{{ __('tasks.awaiting_your_review') }}</p>
                                <div class="grid gap-4 md:grid-cols-2">
                                    {{-- Approve Form --}}
                                    <form method="POST" action="{{ route('reviewer.tasks.approve', $task) }}" class="space-y-3">
                                        @csrf
                                        <label class="block text-sm font-medium text-gray-700" for="approve-note-{{ $task->id }}">{{ __('reviewer.note_label') }}</label>
                                        <textarea id="approve-note-{{ $task->id }}" name="note" placeholder="{{ __('reviewer.note_placeholder') }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                        <button type="submit" class="rounded-md bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-600">{{ __('tasks.approve') }}</button>
                                    </form>

                                    {{-- Reject Form --}}
                                    <form method="POST" action="{{ route('reviewer.tasks.reject', $task) }}" class="space-y-3">
                                        @csrf
                                        <label class="block text-sm font-medium text-gray-700" for="reject-reason-{{ $task->id }}">
                                            {{ __('tasks.rejection_reason') }} <span class="text-red-500">*</span>
                                        </label>
                                        <textarea id="reject-reason-{{ $task->id }}" name="rejection_reason" required minlength="5"
                                            placeholder="{{ __('tasks.rejection_reason_placeholder') }}"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"></textarea>
                                        @error('rejection_reason')
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                        <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500">{{ __('tasks.confirm_reject') }}</button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Documents Section --}}
            @php($allDocuments = $application->tasks->flatMap(fn ($t) => $t->documents)->sortByDesc('created_at'))
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('documents.documents_section') }}</h3>
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    @if ($allDocuments->isNotEmpty())
                        <ul class="divide-y divide-gray-100">
                            @foreach ($allDocuments as $doc)
                                <li class="flex flex-col gap-2 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="break-all font-medium text-gray-900">{{ $doc->original_filename }}</p>
                                        <p class="text-xs text-gray-400 flex items-center gap-2">
                                            <span>{{ $doc->task?->name ?? __('documents.application_documents') }} · {{ $doc->created_at->format('d M Y') }} · {{ $doc->uploader->name }}</span>
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
                                                {{ $doc->source_type === 'reviewer' ? 'bg-indigo-50 text-indigo-700' : '' }}
                                                {{ $doc->source_type === 'admin'    ? 'bg-amber-50 text-amber-700'   : '' }}
                                                {{ $doc->source_type === 'client'   ? 'bg-gray-100 text-gray-600'    : '' }}">
                                                {{ __('reviewer.source_' . $doc->source_type) }}
                                            </span>
                                        </p>
                                    </div>
                                    <a href="{{ route('documents.download', $doc) }}" class="font-medium text-indigo-600 hover:underline">{{ __('documents.download') }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">{{ __('documents.no_documents') }}</p>
                    @endif
                </div>
            </div>

            {{-- Reviewer Document Upload --}}
            @can('reviewerUpload', [App\Models\Document::class, $application])
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900">{{ __('reviewer.upload_section_title') }}</h3>
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <form method="POST" action="{{ route('reviewer.applications.documents.store', $application) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="upload-task">{{ __('reviewer.upload_select_task') }}</label>
                            <select id="upload-task" name="application_task_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">{{ __('reviewer.upload_select_task') }}</option>
                                @foreach ($application->tasks->sortBy('position') as $task)
                                    <option value="{{ $task->id }}">{{ $task->position }}. {{ $task->name }}</option>
                                @endforeach
                            </select>
                            @error('application_task_id')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <input id="upload-file" type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.docx"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-gray-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-gray-700">
                            @error('file')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{{ __('reviewer.upload_submit') }}</button>
                    </form>
                </div>
            </div>
            @endcan

        </div>
    </div>
</x-reviewer-layout>
```

**Checkpoint**: US1 complete. A reviewer can now see tasks in `pending_review` state and approve them with the amber-styled action panel.

---

## Phase 4: User Story 2 — Reviewer Rejects a Payment Task (Priority: P1)

**Goal**: Reviewer rejects a `pending_review` task with a mandatory reason; task reverts to `in_progress` for client correction.

**Independent Test**: Create a payment task at `pending_review` → log in as reviewer → submit rejection with reason → verify task status is `rejected`, reason visible to client.

- [ ] T017 [US2] Update `resources/views/client/tasks/show.blade.php` — add `pending_review` status badge, "Submit for Review" button when `in_progress`, and "Awaiting Review" state panel. Replace the full file:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $task->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Task header --}}
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs text-gray-400 mb-1">{{ __('tasks.step_number', ['number' => $task->position]) }}</p>
                        <h1 class="text-xl font-semibold text-gray-900">{{ $task->name }}</h1>
                        @if ($task->description)
                            <p class="mt-2 text-sm text-gray-600">{{ $task->description }}</p>
                        @endif
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium
                        {{ $task->status === 'approved'        ? 'bg-green-100 text-green-700'   : '' }}
                        {{ $task->status === 'in_progress'     ? 'bg-indigo-100 text-indigo-700' : '' }}
                        {{ $task->status === 'pending_review'  ? 'bg-amber-100 text-amber-700'   : '' }}
                        {{ $task->status === 'rejected'        ? 'bg-red-100 text-red-700'       : '' }}
                        {{ $task->status === 'pending'         ? 'bg-gray-100 text-gray-600'     : '' }}">
                        {{ __('tasks.status_' . $task->status) }}
                    </span>
                </div>

                @if ($task->reviewer_note)
                    <div class="mt-4 rounded-md bg-gray-50 border border-gray-100 px-4 py-3 text-sm text-gray-600">
                        <p class="font-semibold mb-1">{{ __('reviewer.reviewer_note') }}</p>
                        <p>{{ $task->reviewer_note }}</p>
                    </div>
                @endif

                @if ($task->rejection_reason)
                    <div class="mt-4 rounded-md bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700">
                        <p class="font-semibold mb-1">{{ __('tasks.rejection_reason') }}</p>
                        <p>{{ $task->rejection_reason }}</p>
                    </div>
                @endif
            </div>

            {{-- Awaiting Review state --}}
            @if ($task->status === 'pending_review')
                <div class="rounded-lg bg-amber-50 border border-amber-200 p-6">
                    <h2 class="font-semibold text-amber-900 mb-1">{{ __('tasks.awaiting_review') }}</h2>
                    <p class="text-sm text-amber-700">{{ __('tasks.awaiting_review_description') }}</p>
                </div>
            @endif

            {{-- Type-specific UI (only shown when in_progress or rejected) --}}
            @if ($task->type === 'question')
                @if (in_array($task->status, ['in_progress', 'rejected']))
                    @include('client.tasks.partials._question-form')
                @elseif ($task->status === 'approved')
                    @include('client.tasks.partials._answers-readonly')
                @endif
            @elseif ($task->type === 'payment')
                @if (in_array($task->status, ['in_progress', 'rejected']))
                    @include('client.tasks.partials._payment-form')
                @elseif ($task->status === 'approved')
                    @include('client.tasks.partials._receipt-readonly')
                @endif
            @elseif ($task->type === 'info')
                @include('client.tasks.partials._info-content')
            @endif

            {{-- Submit for Review button (shown only when in_progress and not info type) --}}
            @if ($task->status === 'in_progress' && $task->type !== 'info')
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <p class="text-sm text-gray-600 mb-4">{{ __('tasks.submit_for_review_help') }}</p>
                    <form method="POST" action="{{ route('client.tasks.submit-for-review', [$application, $task]) }}">
                        @csrf
                        <button type="submit"
                            class="rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            {{ __('tasks.submit_for_review') }}
                        </button>
                    </form>
                </div>
            @endif

            {{-- Back link --}}
            <div>
                <a href="{{ route('client.dashboard') }}" class="text-sm text-blue-600 hover:underline">
                    &larr; {{ __('client.back_to_dashboard') }}
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
```

**Checkpoint**: US2 complete. Client sees "Submit for Review" button when `in_progress`, "Awaiting Review" panel when `pending_review`, and rejection reason when `rejected`.

---

## Phase 5: User Story 3 — Reviewer Approves a Question Task with Auto-Complete (Priority: P2)

**Goal**: Question tasks with `approval_mode = 'auto'` complete immediately on client submission. Admin can set `approval_mode` in the task builder.

**Independent Test**: Set a question task's `approval_mode` to `'auto'` in DB → log in as client → submit answers → verify task is immediately `approved` and next task is `in_progress` (no reviewer action).

- [ ] T018 [P] [US3] Update `resources/views/admin/task-builder/index.blade.php` — fix task type options to use correct types (question, payment, info) and add `approval_mode` select for question tasks. Replace the full file:

```blade
<x-admin-layout :breadcrumbs="$breadcrumbs ?? []">
    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <h1 class="text-2xl font-semibold text-gray-800">{{ __('admin.nav_task_builder') }}</h1>

            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Add section form --}}
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <h2 class="text-base font-semibold text-gray-900 mb-4">{{ __('admin.add_section') }}</h2>
                <form method="POST" action="{{ route('admin.task-builder.sections.store') }}" class="flex items-end gap-3">
                    @csrf
                    <div>
                        <label for="visa_type_id" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('admin.visa_type_label') }}
                        </label>
                        <select name="visa_type_id" id="visa_type_id"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach($visaTypes as $vt)
                                <option value="{{ $vt->id }}">{{ $vt->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="section_name" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('admin.section_name') }}
                        </label>
                        <input type="text" name="name" id="section_name"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            placeholder="{{ __('admin.section_name_placeholder') }}" required>
                    </div>
                    <button type="submit"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        {{ __('admin.add_section') }}
                    </button>
                </form>
            </div>

            {{-- Visa types + sections list --}}
            @foreach ($visaTypes as $visaType)
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">{{ $visaType->name }}</h2>

                    @forelse ($visaType->workflowSections as $section)
                        <div class="mb-6 border rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-medium text-gray-800">{{ $section->position }}. {{ $section->name }}</h3>
                                <form method="POST" action="{{ route('admin.task-builder.sections.destroy', $section) }}"
                                    onsubmit="return confirm('{{ __('admin.confirm_delete_section') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:underline">{{ __('admin.delete') }}</button>
                                </form>
                            </div>

                            {{-- Tasks in this section --}}
                            @forelse ($section->tasks as $task)
                                <div class="flex items-center justify-between py-2 border-b text-sm text-gray-700">
                                    <span>
                                        {{ $task->position }}. {{ $task->name }}
                                        <span class="ml-2 text-xs text-gray-400">({{ $task->type }})</span>
                                        @if ($task->type === 'question' && $task->approval_mode)
                                            <span class="ml-1 text-xs text-indigo-500">[{{ $task->approval_mode }}]</span>
                                        @endif
                                    </span>
                                    <form method="POST" action="{{ route('admin.task-builder.tasks.destroy', $task) }}"
                                        onsubmit="return confirm('{{ __('admin.confirm_delete_task') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-600 hover:underline">{{ __('admin.delete') }}</button>
                                    </form>
                                </div>
                            @empty
                                <p class="text-sm text-gray-400 py-2">{{ __('admin.no_tasks_in_section') }}</p>
                            @endforelse

                            {{-- Add task to this section --}}
                            <form method="POST" action="{{ route('admin.task-builder.tasks.store', $section) }}"
                                class="mt-3 flex flex-wrap items-end gap-2" x-data="{ taskType: 'question' }">
                                @csrf
                                <div>
                                    <input type="text" name="name"
                                        class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="{{ __('admin.task_name_placeholder') }}" required>
                                </div>
                                <div>
                                    <input type="text" name="description"
                                        class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="{{ __('admin.task_description_placeholder') }}">
                                </div>
                                <div>
                                    <select name="type" x-model="taskType"
                                        class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="question">{{ __('admin.task_type_question') }}</option>
                                        <option value="payment">{{ __('admin.task_type_payment') }}</option>
                                        <option value="info">{{ __('admin.task_type_info') }}</option>
                                    </select>
                                </div>
                                <div x-show="taskType === 'question'">
                                    <select name="approval_mode"
                                        class="rounded-md border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="manual">{{ __('admin.approval_mode_manual') }}</option>
                                        <option value="auto">{{ __('admin.approval_mode_auto') }}</option>
                                    </select>
                                </div>
                                <button type="submit"
                                    class="inline-flex items-center rounded-md bg-gray-700 px-3 py-2 text-xs font-semibold text-white hover:bg-gray-600">
                                    {{ __('admin.add_task') }}
                                </button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">{{ __('admin.no_sections_yet') }}</p>
                    @endforelse
                </div>
            @endforeach

        </div>
    </div>
</x-admin-layout>
```

- [ ] T019 [US3] Update the admin TaskBuilderController's `storeTask` method to accept and save `approval_mode`. Read `app/Http/Controllers/Admin/TaskBuilderController.php` first, then find the `storeTask` method and ensure it passes `approval_mode` from the request to the WorkflowTask creation. The task creation call must include:

```php
'approval_mode' => $request->input('approval_mode'), // nullable — null for non-question tasks
```

If the method uses a Form Request, add `'approval_mode' => ['nullable', 'in:auto,manual']` to the rules. If it uses inline validation, add the same rule there.

**Checkpoint**: US3 complete. Admin can set `approval_mode` on question tasks. Auto-complete tasks skip `pending_review` on client submission.

---

## Phase 6: User Story 4 — Reviewer Views All Tasks with Badge (Priority: P2)

**Goal**: Reviewer dashboard shows pending review count; task list shows correct status for all states including `pending_review`.

**Independent Test**: Create 3 tasks with different statuses including `pending_review` → log in as reviewer → verify badge shows correct count, task list shows correct colors and labels.

- [ ] T020 [US4] Update `app/Http/Controllers/Reviewer/DashboardController.php` — query `pending_review` count and update task filter to show `pending_review` tasks. Replace the full file:

```php
<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Models\ApplicationTask;
use App\Models\VisaApplication;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function show(string $tab = 'applications'): View
    {
        $validTabs = ['applications'];

        if (! in_array($tab, $validTabs, true)) {
            $tab = 'applications';
        }

        $user = Auth::user();

        $applications = $tab === 'applications'
            ? VisaApplication::with(['visaType', 'user', 'tasks' => fn ($q) => $q->whereIn('status', ['in_progress', 'pending_review'])])
                ->where('assigned_reviewer_id', $user->id)
                ->whereIn('status', ['pending_review', 'in_progress'])
                ->orderBy('created_at')
                ->get()
            : collect();

        $pendingReviewCount = ApplicationTask::whereHas('application', function ($q) use ($user) {
                $q->where('assigned_reviewer_id', $user->id);
            })
            ->where('status', 'pending_review')
            ->count();

        return view('reviewer.dashboard.index', compact('tab', 'applications', 'pendingReviewCount'));
    }
}
```

- [ ] T021 [US4] Update `resources/views/reviewer/dashboard/index.blade.php` — add the `pendingReviewCount` badge. Replace the full file:

```blade
<x-reviewer-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto space-y-6 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between">
                <div class="overflow-x-auto rounded-lg bg-white p-2 shadow-sm">
                    <nav class="flex min-w-max gap-2">
                        <a href="{{ route('reviewer.dashboard', ['tab' => 'applications']) }}"
                            class="rounded-md px-4 py-2 text-sm font-medium {{ $tab === 'applications' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                            {{ __('reviewer.tab_applications') }}
                        </a>
                    </nav>
                </div>
                <div class="flex items-center gap-4">
                    @if ($pendingReviewCount > 0)
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800">
                            {{ __('reviewer.pending_review_count', ['count' => $pendingReviewCount]) }}
                        </span>
                    @endif
                    <p class="text-sm text-gray-500">{{ __('reviewer.queue_count') }}: {{ $applications->count() }}</p>
                </div>
            </div>

            @include('reviewer.dashboard.tabs.' . $tab, ['applications' => $applications])
        </div>
    </div>
</x-reviewer-layout>
```

**Checkpoint**: US4 complete. Reviewer sees amber badge with pending review count; dashboard filters to assigned applications only.

---

## Phase 7: Polish — Translation Keys

**Purpose**: Add all new translation keys for both locales.

- [ ] T022 Update `resources/lang/en/tasks.php` — add new keys. Replace the full file:

```php
<?php

return [
    'status_pending'        => 'Pending',
    'status_in_progress'    => 'In Progress',
    'status_completed'      => 'Completed',
    'status_approved'       => 'Approved',
    'status_rejected'       => 'Rejected',
    'status_pending_review' => 'Awaiting Review',
    'no_tasks'              => 'No workflow tasks have been assigned to your application yet.',
    'current_step'          => 'Current Step',
    'completed_on'          => 'Completed on :date',
    'step_number'           => 'Step :number',
    'progress_summary'      => 'Workflow Progress',
    'reopen'                => 'Re-open',
    'task_summary'          => ':completed / :total tasks complete',
    'reopen_success'        => 'Task has been re-opened.',
    'approve'               => 'Approve',
    'reject'                => 'Reject',
    'rejection_reason'      => 'Rejection Reason',
    'rejection_reason_placeholder' => 'Explain why this task is being rejected...',
    'confirm_reject'        => 'Confirm Rejection',
    'task_approved'         => 'Task has been approved.',
    'task_rejected'         => 'Task has been rejected.',
    'text_input_label'      => 'Additional Information',
    'text_input_coming_soon' => 'Text submission will be available soon.',
    'answers_submitted'     => 'Your answers have been saved.',
    'receipt_uploaded'      => 'Your receipt has been uploaded.',
    'your_answers'          => 'Your Answers',
    'no_questions_defined'  => 'No questions are required for this task. Awaiting reviewer action.',
    'submit_answers'        => 'Submit Answers',
    'payment_receipt'       => 'Payment Receipt',
    'current_receipt'       => 'Current Receipt',
    'replace_receipt'       => 'Replace Receipt',
    'upload_receipt'        => 'Upload Receipt',
    'info_task_note'        => 'This task contains information only. No action is required from you.',
    'answers_readonly_title' => 'Submitted Answers',
    'task_locked'           => 'This task is not yet available.',
    'workflow_complete_title'   => 'All Tasks Complete',
    'workflow_complete_message' => 'All workflow tasks have been completed. Your application is now under final review.',

    // New keys for Phase 018
    'submit_for_review'         => 'Submit for Review',
    'submit_for_review_help'    => 'When ready, submit this task for reviewer approval.',
    'submitted_for_review'      => 'Your task has been submitted for review.',
    'awaiting_review'           => 'Awaiting Review',
    'awaiting_review_description' => 'Your submission is under review. You will be notified of the outcome.',
    'awaiting_your_review'      => 'This task is awaiting your review.',
    'submitted_for_review_subject'  => 'Task Requires Review — :reference',
    'submitted_for_review_heading'  => 'A Task Requires Your Review',
    'submitted_for_review_intro'    => ':client has submitted a task for your review.',
    'submitted_for_review_cta'      => 'Please log in to the portal to review and approve or reject the task.',
    'task_name_label'           => 'Task',
    'task_type_label'           => 'Type',
];
```

- [ ] T023 [P] Update `resources/lang/ar/tasks.php` — add Arabic translations for all new keys. Open the existing file and add these keys to the existing array:

```php
'status_pending_review'         => 'قيد المراجعة',
'submit_for_review'             => 'تقديم للمراجعة',
'submit_for_review_help'        => 'عندما تكون مستعداً، قدّم هذه المهمة للمراجعة.',
'submitted_for_review'          => 'تم تقديم مهمتك للمراجعة.',
'awaiting_review'               => 'بانتظار المراجعة',
'awaiting_review_description'   => 'طلبك قيد المراجعة. سيتم إعلامك بالنتيجة.',
'awaiting_your_review'          => 'هذه المهمة بانتظار مراجعتك.',
'submitted_for_review_subject'  => 'مهمة تحتاج إلى مراجعة — :reference',
'submitted_for_review_heading'  => 'مهمة تحتاج إلى مراجعتك',
'submitted_for_review_intro'    => 'قدّم :client مهمة لمراجعتك.',
'submitted_for_review_cta'      => 'يرجى تسجيل الدخول إلى البوابة لمراجعة المهمة والموافقة عليها أو رفضها.',
'task_name_label'               => 'المهمة',
'task_type_label'               => 'النوع',
```

- [ ] T024 [P] Update `resources/lang/en/admin.php` — add translation keys for admin task builder approval mode. Open the file and append:

```php
'task_type_question'     => 'Question',
'task_type_payment'      => 'Payment',
'task_type_info'         => 'Info',
'approval_mode_manual'   => 'Manual Approval',
'approval_mode_auto'     => 'Auto-Complete',
```

- [ ] T025 [P] Update `resources/lang/ar/admin.php` — add Arabic translations for admin keys:

```php
'task_type_question'     => 'سؤال',
'task_type_payment'      => 'دفع',
'task_type_info'         => 'معلومات',
'approval_mode_manual'   => 'موافقة يدوية',
'approval_mode_auto'     => 'إكمال تلقائي',
```

- [ ] T026 [P] Update `resources/lang/en/reviewer.php` — add the pending review count badge key. Append:

```php
'pending_review_count' => ':count tasks awaiting your review',
```

- [ ] T027 [P] Update `resources/lang/ar/reviewer.php` — add Arabic translation:

```php
'pending_review_count' => ':count مهام بانتظار مراجعتك',
```

---

## Phase 8: Deep Review

**Purpose**: Verify the entire implementation is correct before marking the feature complete. Each task is a specific verification check.

- [ ] T028 Verify WorkflowService guard correctness — open `app/Services/Tasks/WorkflowService.php` and confirm:
  - `approveTask()` throws if status is NOT `pending_review` (not `in_progress`)
  - `rejectTaskWithReason()` throws if status is NOT `pending_review` (not `in_progress`)
  - `submitForReview()` throws if status is NOT `in_progress`
  - `autoCompleteTask()` throws if status is NOT `in_progress`
  - `rejectTask()` (used by advance path) still checks `in_progress` — this is intentional, do NOT change it
  - All four methods that set `reviewed_by`/`reviewed_at` do so: `approveTask()`, `rejectTaskWithReason()`, `autoCompleteTask()` (sets `reviewed_at` only, `reviewed_by` = null for system action)

- [ ] T029 Verify authorization chain — confirm these three things are true:
  1. `ApplicationTaskPolicy::submitForReview()` returns `false` if task status is NOT `in_progress` (the policy itself checks status)
  2. `TaskController::submitForReview()` calls BOTH `$this->authorize('view', $application)` AND `$this->authorize('submitForReview', $task)` before calling the service
  3. `ReviewerApplicationController::approve()` still calls `$this->authorize('approve', $task)` — confirm this line exists

- [ ] T030 Verify email notification — open `WorkflowService::submitForReview()` and confirm:
  - `Mail::to($reviewer->email)->queue(new TaskSubmittedForReviewMail($task))` is called only when `$reviewer` is not null
  - The `use Illuminate\Support\Facades\Mail;` import exists at the top of the file
  - The `use App\Mail\TaskSubmittedForReviewMail;` import exists at the top of the file
  - `TaskSubmittedForReviewMail` implements `ShouldQueue` (check the class declaration)

- [ ] T031 Verify `pending_review` status badge coverage — search ALL Blade views for `status_` translation calls and confirm `pending_review` is handled everywhere. Run:

```bash
grep -rn "status_' \. \$task->status\|status_pending_review" resources/views/
```

Verify that `resources/views/client/tasks/show.blade.php` and `resources/views/reviewer/applications/show.blade.php` both have the amber badge class for `pending_review`. If any view outputs `tasks.status_pending_review` but the key is missing in `lang/`, the view will echo the key name. Confirm T022 is complete.

- [ ] T032 Verify auto-complete does not trigger for payment tasks — open `app/Services/Tasks/TaskAnswerService.php::submitAnswers()` and confirm the auto-complete condition is:

```php
if ($task->type === 'question' && $task->approval_mode === 'auto') {
```

NOT just `$task->approval_mode === 'auto'` — the `$task->type === 'question'` guard is critical to prevent payment tasks from ever auto-completing.

- [ ] T033 Verify migration column order and foreign key — run `php artisan migrate:status` and confirm both new migrations are listed as "Ran". Then verify the columns exist:

```bash
php artisan tinker --execute="echo implode(', ', \Schema::getColumnListing('application_tasks'));"
php artisan tinker --execute="echo implode(', ', \Schema::getColumnListing('workflow_tasks'));"
```

Confirm `approval_mode`, `reviewed_by`, `reviewed_at` appear in `application_tasks` output and `approval_mode` appears in `workflow_tasks` output.

- [ ] T034 Verify seeder copies `approval_mode` — open `WorkflowService::seedTasksForApplication()` and confirm the section-based task creation includes `'approval_mode' => $workflowTask->approval_mode`. The legacy template path does NOT need `approval_mode` (those templates don't have the column).

- [ ] T035 Run the full test suite and confirm zero regressions:

```bash
php artisan test
```

If any test fails, diagnose the root cause before marking this task complete. Common failure points:
- Tests that call `approveTask()` on an `in_progress` task will now fail because the guard requires `pending_review`. If such tests exist, update them to set task status to `pending_review` before calling `approveTask()`.
- Tests that call `rejectTaskWithReason()` on an `in_progress` task — same fix: set to `pending_review` first.
- TaskAnswerService tests that injected only 2 constructor arguments will fail because a 3rd (`WorkflowService`) was added. Update test DI to inject the service or mock it.

- [ ] T036 Manual acceptance test — follow the quickstart.md happy path exactly:
  1. Log in as admin → Task Builder → create a question task with `approval_mode = manual` and a payment task
  2. Log in as a client → onboard → navigate to the question task → save answers → click "Submit for Review" → confirm status shows "Awaiting Review"
  3. Log in as reviewer → confirm amber badge on dashboard → open application → confirm amber action panel on the pending_review task → click Approve → confirm next task is `in_progress` for the client
  4. Repeat steps 2-3 for the payment task (upload receipt → submit for review → reviewer approves)
  5. Log in as admin → create a question task with `approval_mode = auto` → verify that on client answer submission, task immediately becomes `approved` without reviewer action

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Migrations)**: No dependencies — start immediately
- **Phase 2 (Foundational)**: Requires Phase 1 complete — BLOCKS all user story phases
- **Phase 3 (US1)**: Requires Phase 2 complete
- **Phase 4 (US2)**: Requires Phase 2 complete; can run parallel with Phase 3
- **Phase 5 (US3)**: Requires Phase 2 complete; can run parallel with Phase 3 and 4
- **Phase 6 (US4)**: Requires Phase 2 complete; can run parallel with Phase 3, 4, 5
- **Phase 7 (Polish)**: Can run parallel with Phases 3-6 (translation files are independent)
- **Phase 8 (Deep Review)**: Requires ALL previous phases complete

### Within Each Story

- T009, T010 → parallel (different files)
- T011 → depends on T009 (policy method must exist for authorize call)
- T012 → depends on T011 (route points to new controller method)
- T013, T014 → parallel (class and view are independent)
- T015, T016 → parallel (different files)

### Critical Ordering Notes

1. **T007 must complete before T015/T016** — the service guard change must exist before the reviewer UI expects `pending_review` tasks to be acted on
2. **T006 (permission seeder) must run** — without `tasks.submit-for-review`, all client submit-for-review requests will 403
3. **T013 must exist before T007 is deployed** — WorkflowService imports `TaskSubmittedForReviewMail`; if the class doesn't exist, the service will throw a fatal error

---

## Parallel Opportunities

```
# Phase 2 — run all in parallel (different files):
T004: Update WorkflowTask model
T005: Update ApplicationTask model
T006: Update RolePermissionSeeder

# Phase 3 — first wave parallel:
T009: Update Policy
T010: Create SubmitTaskForReviewRequest
T013: Create TaskSubmittedForReviewMail
T014: Create mail template

# Phase 7 — all translation tasks parallel:
T022, T023, T024, T025, T026, T027

# Phase 8 — T028-T035 are verifications (can run in parallel, each checks a different concern):
T028: Guard verification
T029: Authorization verification
T030: Email verification
T031: Status badge verification
T032: Auto-complete guard verification
T033: Migration verification
T034: Seeder verification
T035: Test suite (must run after all verification tasks)
```

---

## Implementation Strategy

### MVP First (User Stories 1 + 2)

1. Complete Phase 1 (Migrations)
2. Complete Phase 2 (Foundational)
3. Complete Phase 3 (US1: Approve) + Phase 4 (US2: Reject)
4. **VALIDATE**: Reviewer can approve/reject `pending_review` tasks; client can submit for review
5. Add Phase 5 (US3: Auto-complete) + Phase 6 (US4: Dashboard badge)
6. Add Phase 7 (Polish)
7. Run Phase 8 (Deep Review)

### Notes

- [P] tasks use different files — safe to run in parallel
- Each phase checkpoint describes the minimum working state after that phase
- Phase 8 review tasks are verifications, not implementations — if a verification fails, fix the relevant implementation task first
- Never skip T035 (full test suite) — the guard changes in WorkflowService affect existing test scenarios
