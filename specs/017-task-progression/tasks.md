# Tasks: Task Progression (017)

**Input**: Design documents from `/specs/017-task-progression/`
**Branch**: `017-task-progression`
**Date**: 2026-03-23

---

## Phase 1: Setup

No new files, migrations, or tables required. All progression state uses existing columns.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Add required language keys before any UI or service changes.

**⚠️ CRITICAL**: T001 must be done before T006.

- [X] T001 Add `task_locked`, `workflow_complete_title`, `workflow_complete_message` to `resources/lang/en/tasks.php`

**Exact change — open `resources/lang/en/tasks.php` and append before the closing `];`:**

```php
    'task_locked' => 'This task is not yet available.',
    'workflow_complete_title' => 'All Tasks Complete',
    'workflow_complete_message' => 'All workflow tasks have been completed. Your application is now under final review.',
```

**Checkpoint**: Lang keys available — UI and controller changes can now be made.

---

## Phase 3: User Story 1 — Next Task Unlocks After Approval (Priority: P1) 🎯 MVP

**Goal**: When a reviewer approves a task, the system activates the next task. When the final task is approved, the application transitions to `workflow_complete`.

**Independent Test**: Approve task 1 of 2 → task 2 becomes `in_progress`. Approve task 2 → application status becomes `workflow_complete`.

### Implementation for User Story 1

- [X] T002 [US1] Add `workflow_complete` hook to `WorkflowService::approveTask` in `app/Services/Tasks/WorkflowService.php`

**Exact change — replace the entire `approveTask` method (lines 117–146):**

```php
public function approveTask(ApplicationTask $task, ?string $note): void
{
    $workflowComplete = false;

    DB::transaction(function () use ($task, $note, &$workflowComplete): void {
        $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

        if ($task->status !== 'in_progress') {
            throw new InvalidArgumentException('Only an in_progress task can be approved.');
        }

        $task->update([
            'status' => 'approved',
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

    $this->auditLog->log('task_approved', $this->actor(), [
        'task' => $task->name,
        'reference' => $task->application->reference_number,
    ]);

    if ($workflowComplete) {
        $this->auditLog->log('workflow_tasks_complete', $this->actor(), [
            'reference' => $task->application->reference_number,
        ]);
    }
}
```

- [X] T003 [US1] Add `workflow_complete` hook to `WorkflowService::advanceTask` in `app/Services/Tasks/WorkflowService.php`

**Exact change — replace the entire `advanceTask` method (lines 89–115):**

```php
public function advanceTask(ApplicationTask $task, ?string $note): void
{
    $workflowComplete = false;

    DB::transaction(function () use ($task, $note, &$workflowComplete): void {
        $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

        if ($task->status !== 'in_progress') {
            throw new InvalidArgumentException('Only an in_progress task can be advanced.');
        }

        $task->update([
            'status' => 'approved',
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
        'task' => $task->name,
        'reference' => $task->application->reference_number,
    ]);

    if ($workflowComplete) {
        $this->auditLog->log('workflow_tasks_complete', $this->actor(), [
            'reference' => $task->application->reference_number,
        ]);
    }
}
```

**Checkpoint**: Approving the last task now sets `visa_applications.status = 'workflow_complete'`. Run `php artisan test tests/Feature/Reviewer/WorkflowTest.php` to confirm existing tests still pass.

---

## Phase 4: User Story 2 — Locked Tasks Are Inaccessible to Clients (Priority: P2)

**Goal**: A client cannot submit answers or upload a receipt for a `pending` task. Graceful redirect instead of 500.

**Independent Test**: POST to `client.tasks.answers.submit` for a pending task → redirected back with error flash `tasks.task_locked`.

### Implementation for User Story 2

- [X] T004 [US2] Add pending-task guard to `TaskController::submitAnswers` in `app/Http/Controllers/Client/TaskController.php`

**Exact change — in `submitAnswers`, add the guard immediately after the `abort_if` line:**

Current code (lines 38–43):
```php
public function submitAnswers(SubmitTaskAnswersRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
{
    $this->authorize('view', $application);
    abort_if($task->application_id !== $application->id, 404);

    $this->taskAnswerService->submitAnswers($task, $request->input('answers'), Auth::user());
```

Replace with:
```php
public function submitAnswers(SubmitTaskAnswersRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
{
    $this->authorize('view', $application);
    abort_if($task->application_id !== $application->id, 404);

    if ($task->status === 'pending') {
        return redirect()->back()->with('error', __('tasks.task_locked'));
    }

    $this->taskAnswerService->submitAnswers($task, $request->input('answers'), Auth::user());
```

- [X] T005 [US2] Add pending-task guard to `TaskController::uploadReceipt` in `app/Http/Controllers/Client/TaskController.php`

**Exact change — in `uploadReceipt`, add the guard immediately after the `abort_if` line:**

Current code (lines 47–51):
```php
public function uploadReceipt(UploadReceiptRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
{
    $this->authorize('view', $application);
    abort_if($task->application_id !== $application->id, 404);

    $this->taskAnswerService->uploadReceipt($task, $request->file('receipt'), Auth::user());
```

Replace with:
```php
public function uploadReceipt(UploadReceiptRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
{
    $this->authorize('view', $application);
    abort_if($task->application_id !== $application->id, 404);

    if ($task->status === 'pending') {
        return redirect()->back()->with('error', __('tasks.task_locked'));
    }

    $this->taskAnswerService->uploadReceipt($task, $request->file('receipt'), Auth::user());
```

**Checkpoint**: POST to a pending task now returns a redirect with error flash rather than a 500.

---

## Phase 5: User Story 3 — Final Task Approval Closes the Workflow (Priority: P3)

**Goal**: Dashboard shows `workflow_complete` banner, correct approved-task count, and task links. Fix pre-existing `completed` → `approved` status bug.

**Independent Test**: After all tasks are approved, client dashboard Tasks tab shows the "All Tasks Complete" banner and the progress counter shows `N / N`.

### Implementation for User Story 3

- [X] T006 [US3] Rewrite `resources/views/client/dashboard/tabs/tasks.blade.php` to fix `completed` → `approved`, add task links, add pending opacity, add `workflow_complete` banner

**Exact replacement — overwrite the entire file with:**

```blade
@if ($application->status === 'workflow_complete')
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3">
        <p class="text-sm font-semibold text-green-800">{{ __('tasks.workflow_complete_title') }}</p>
        <p class="mt-1 text-sm text-green-700">{{ __('tasks.workflow_complete_message') }}</p>
    </div>
@endif

@if ($application->tasks->isEmpty())
    <div class="rounded-lg bg-white p-10 text-center shadow-sm">
        <p class="text-gray-500">{{ __('tasks.no_tasks') }}</p>
    </div>
@else
    <div class="mb-4 rounded-lg bg-white p-4 shadow-sm">
        <p class="text-sm font-medium text-gray-900">{{ __('tasks.progress_summary') }}</p>
        <p class="mt-1 text-sm text-gray-500">
            {{ __('tasks.task_summary', ['completed' => $application->tasks->where('status', 'approved')->count(), 'total' => $application->tasks->count()]) }}
        </p>
    </div>

    <div class="space-y-3">
        @foreach ($application->tasks->sortBy('position') as $task)
            @php
                $isActive   = $task->status === 'in_progress';
                $isApproved = $task->status === 'approved';
                $isPending  = $task->status === 'pending';
                $isRejected = $task->status === 'rejected';
            @endphp
            <div class="rounded-lg bg-white p-6 shadow-sm
                {{ $isActive   ? 'border-l-4 border-indigo-500 ring-1 ring-indigo-100' : '' }}
                {{ $isApproved ? 'border-l-4 border-green-500' : '' }}
                {{ $isRejected ? 'border-l-4 border-red-500' : '' }}
                {{ $isPending  ? 'opacity-50' : '' }}">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs text-gray-400 mb-1">{{ __('tasks.step_number', ['number' => $task->position]) }}</p>
                        @if ($isActive || $isApproved)
                            <a href="{{ route('client.tasks.show', [$application, $task]) }}"
                               class="font-semibold text-gray-900 hover:text-indigo-600">{{ $task->name }}</a>
                        @else
                            <h4 class="font-semibold text-gray-900">{{ $task->name }}</h4>
                        @endif
                        @if ($task->description)
                            <p class="mt-1 text-sm text-gray-500">{{ $task->description }}</p>
                        @endif
                        @if ($isApproved && $task->completed_at)
                            <p class="mt-2 text-xs text-green-600">{{ __('tasks.completed_on', ['date' => $task->completed_at->format('d M Y')]) }}</p>
                        @endif
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium
                        {{ $isApproved ? 'bg-green-100 text-green-700'   : '' }}
                        {{ $isActive   ? 'bg-indigo-100 text-indigo-700' : '' }}
                        {{ $isPending  ? 'bg-gray-100 text-gray-600'     : '' }}
                        {{ $isRejected ? 'bg-red-100 text-red-700'       : '' }}">
                        {{ __('tasks.status_' . $task->status) }}
                    </span>
                </div>
            </div>
        @endforeach
    </div>
@endif
```

**Checkpoint**: Dashboard Tasks tab renders approved tasks in green with links, pending tasks at 50% opacity without links, and shows the "All Tasks Complete" banner when `workflow_complete`.

---

## Phase 6: Feature Tests

- [X] T007 Create `tests/Feature/Tasks/TaskProgressionTest.php` with 10 progression tests

**Exact file content:**

```php
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

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function makeApplication(int $taskCount = 2, string $type = 'info'): array
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

        $section = WorkflowSection::create([
            'visa_type_id' => $visaType->id,
            'name'         => 'Test Section',
            'position'     => 99,
        ]);

        $workflowTasks = [];
        for ($i = 1; $i <= $taskCount; $i++) {
            $workflowTasks[] = WorkflowTask::create([
                'workflow_section_id' => $section->id,
                'name'                => "Task {$i}",
                'type'                => $type,
                'position'            => $i,
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

    // ── US1: Next task unlocks after approval ──────────────────────────────────

    public function test_approving_task_activates_next_task(): void
    {
        [, $reviewer, $application, $appTasks] = $this->makeApplication(2);

        app(WorkflowService::class)->approveTask($appTasks[0], null);

        $this->assertSame('approved',     $appTasks[0]->fresh()->status);
        $this->assertSame('in_progress',  $appTasks[1]->fresh()->status);
        $this->assertSame('in_progress',  $application->fresh()->status);
    }

    public function test_approving_last_task_sets_workflow_complete(): void
    {
        [, , $application, $appTasks] = $this->makeApplication(1);

        app(WorkflowService::class)->approveTask($appTasks[0], null);

        $this->assertSame('approved',           $appTasks[0]->fresh()->status);
        $this->assertSame('workflow_complete',  $application->fresh()->status);
    }

    public function test_approving_last_task_does_not_throw(): void
    {
        [, , , $appTasks] = $this->makeApplication(1);

        $this->expectNotToPerformAssertions();

        app(WorkflowService::class)->approveTask($appTasks[0], null);
    }

    public function test_advancing_task_activates_next_task(): void
    {
        [, , $application, $appTasks] = $this->makeApplication(2);

        app(WorkflowService::class)->advanceTask($appTasks[0], null);

        $this->assertSame('approved',    $appTasks[0]->fresh()->status);
        $this->assertSame('in_progress', $appTasks[1]->fresh()->status);
        $this->assertSame('in_progress', $application->fresh()->status);
    }

    public function test_advancing_last_task_sets_workflow_complete(): void
    {
        [, , $application, $appTasks] = $this->makeApplication(1);

        app(WorkflowService::class)->advanceTask($appTasks[0], null);

        $this->assertSame('approved',          $appTasks[0]->fresh()->status);
        $this->assertSame('workflow_complete', $application->fresh()->status);
    }

    // ── US2: Locked tasks are inaccessible ─────────────────────────────────────

    public function test_pending_task_submit_answers_is_rejected(): void
    {
        [$client, , $application, $appTasks] = $this->makeApplication(2, 'question');

        $pendingTask = $appTasks[1]; // second task starts pending

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

    // ── US3: Dashboard state ───────────────────────────────────────────────────

    public function test_dashboard_shows_active_task_link(): void
    {
        [$client, , $application, $appTasks] = $this->makeApplication(2);

        $activeTask = $appTasks[0]; // first task is in_progress

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

        app(WorkflowService::class)->approveTask($appTasks[0], null);

        $this->actingAs($client)
            ->get(route('client.dashboard', ['tab' => 'tasks']))
            ->assertOk()
            ->assertSee(__('tasks.workflow_complete_title'));
    }
}
```

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 2 (T001)**: No dependencies — do first
- **Phase 3 (T002, T003)**: Depends on nothing (service-only changes)
- **Phase 4 (T004, T005)**: Depends on T001 (uses `tasks.task_locked` key)
- **Phase 5 (T006)**: Depends on T001 (uses `workflow_complete_*` keys)
- **Phase 6 (T007)**: Depends on T002, T003, T004, T005, T006 all complete

### User Story Dependencies

- **US1 (T002, T003)**: Independent — no dependency on US2 or US3
- **US2 (T004, T005)**: Independent — no dependency on US1 or US3
- **US3 (T006)**: Independent — no dependency on US1 or US2 (view-only change)
- **Tests (T007)**: Depends on all phases complete

---

## Parallel Opportunities

```
# These can be done in any order after T001:
T002 — WorkflowService::approveTask  (service)
T003 — WorkflowService::advanceTask  (service)
T004 — TaskController::submitAnswers (controller)
T005 — TaskController::uploadReceipt (controller)
T006 — tasks.blade.php               (view)

# T007 must be last (tests everything)
```

---

## Implementation Strategy

### MVP (US1 only — T001 → T002 → T003)

1. Add lang keys (T001)
2. Add `workflow_complete` hook to `approveTask` (T002)
3. Add `workflow_complete` hook to `advanceTask` (T003)
4. **Validate**: Approve the last task → `application.status = workflow_complete`

### Full Delivery (T001–T007 in order)

1. T001 — lang keys
2. T002, T003 — service hooks
3. T004, T005 — controller guards
4. T006 — dashboard view
5. T007 — test suite (`php artisan test tests/Feature/Tasks/TaskProgressionTest.php`)

---

## Notes

- `WorkflowService::approveTask` and `advanceTask` already have `lockForUpdate()` — concurrent approval is safe without additional changes
- No migrations needed — `visa_applications.status` is VARCHAR(30); `'workflow_complete'` (17 chars) fits
- `DocumentService::upload` sets `awaiting_documents` only when `$application->status === 'in_progress'` — once `workflow_complete` is set, further uploads will not regress the status
- The pre-existing `'completed'` references in `tasks.blade.php` are a regression from migration `2026_03_22_200001_rename_completed_to_approved_in_application_tasks` — T006 fixes them
