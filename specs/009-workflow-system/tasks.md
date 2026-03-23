# Tasks: Workflow System (Core System)

**Branch**: `009-workflow-system` | **Date**: 2026-03-21 | **Spec**: [spec.md](./spec.md)
**Plan**: [plan.md](./plan.md) | **Contracts**: [contracts/routes.md](./contracts/routes.md)

## Overview

The workflow engine is ~90% implemented. This tasks file closes 4 specific gaps:
- **Gap 1**: Reviewer can re-open a rejected task (new method + controller action + form request + policy + route + view button)
- **Gap 2**: Remove automatic application status transitions from `advanceTask()` and `rejectTask()`
- **Gap 3**: Admin applications list shows task count summary per application
- **Gap 4**: All WorkflowService state mutations wrapped in `DB::transaction()` with `lockForUpdate()`

**No migrations required. No new directories required.**

---

## Phase 1: Setup

> Verify the environment is ready before touching any code.

- [ ] T001 Run `php artisan test` from repo root and confirm all existing tests pass before making any changes. If tests are failing, stop and investigate — do not proceed.

---

## Phase 2: Foundational — Language Keys

> New lang keys must exist before views or controllers reference them. Add them first so later tasks can safely use `__()`.

- [ ] T002 Add re-open and admin task summary lang keys to `resources/lang/en/tasks.php`.

  Open `resources/lang/en/tasks.php`. The file currently returns this array:
  ```php
  return [
      'status_pending'   => 'Pending',
      'status_in_progress' => 'In Progress',
      'status_completed' => 'Completed',
      'status_rejected'  => 'Rejected',
      'no_tasks'         => 'No workflow tasks have been assigned to your application yet.',
      'current_step'     => 'Current Step',
      'completed_on'     => 'Completed on :date',
      'step_number'      => 'Step :number',
      'progress_summary' => 'Workflow Progress',
  ];
  ```

  Add three new keys at the end of the array (before the closing `]`):
  ```php
      'reopen'              => 'Re-open',
      'task_summary'        => ':completed / :total tasks complete',
      'reopen_success'      => 'Task has been re-opened.',
  ```

  Final file must look like:
  ```php
  <?php

  return [
      'status_pending'   => 'Pending',
      'status_in_progress' => 'In Progress',
      'status_completed' => 'Completed',
      'status_rejected'  => 'Rejected',
      'no_tasks'         => 'No workflow tasks have been assigned to your application yet.',
      'current_step'     => 'Current Step',
      'completed_on'     => 'Completed on :date',
      'step_number'      => 'Step :number',
      'progress_summary' => 'Workflow Progress',
      'reopen'           => 'Re-open',
      'task_summary'     => ':completed / :total tasks complete',
      'reopen_success'   => 'Task has been re-opened.',
  ];
  ```

- [ ] T003 [P] Add the same three keys (Arabic) to `resources/lang/ar/tasks.php`.

  Open `resources/lang/ar/tasks.php`. The file currently returns:
  ```php
  return [
      'status_pending'   => 'في الانتظار',
      'status_in_progress' => 'قيد التنفيذ',
      'status_completed' => 'مكتمل',
      'status_rejected'  => 'مرفوض',
      'no_tasks'         => 'لم يتم تعيين مهام سير العمل لطلبك بعد.',
      'current_step'     => 'الخطوة الحالية',
      'completed_on'     => 'اكتمل في :date',
      'step_number'      => 'الخطوة :number',
      'progress_summary' => 'تقدم سير العمل',
  ];
  ```

  Add three new keys at the end of the array (before the closing `]`):
  ```php
      'reopen'           => 'إعادة فتح',
      'task_summary'     => ':completed / :total مهام مكتملة',
      'reopen_success'   => 'تمت إعادة فتح المهمة.',
  ```

---

## Phase 3: Gap 2 — Remove Auto Application Status Transitions [US2]

> Remove two side effects from WorkflowService: `advanceTask()` must not auto-approve the application; `rejectTask()` must not auto-reject it. Do this before Gap 1 to keep WorkflowService coherent.

- [ ] T004 [US2] Remove auto-approve side effect from `WorkflowService::advanceTask()` in `app/Services/Tasks/WorkflowService.php`.

  Open the file. The `advanceTask()` method currently reads:
  ```php
  public function advanceTask(ApplicationTask $task, ?string $note): void
  {
      if ($task->status !== 'in_progress') {
          throw new InvalidArgumentException('Only an in_progress task can be advanced.');
      }

      DB::transaction(function () use ($task, $note): void {
          $task->update([
              'status' => 'completed',
              'completed_at' => now(),
              'reviewer_note' => $note,
          ]);

          $nextTask = ApplicationTask::where('application_id', $task->application_id)
              ->where('position', $task->position + 1)
              ->first();

          if ($nextTask) {
              $nextTask->update(['status' => 'in_progress']);
          } else {
              $task->application->update(['status' => 'approved']);
              $this->auditLog->log('application_approved', $this->actor(), ['reference' => $task->application->reference_number]);
          }
      });

      $this->auditLog->log('task_completed', $this->actor(), ['task' => $task->name, 'reference' => $task->application->reference_number]);
  }
  ```

  Replace the `else` branch — remove the two lines that set `status => 'approved'` and log `application_approved`. The updated `DB::transaction` closure must look exactly like this:
  ```php
  DB::transaction(function () use ($task, $note): void {
      $task->update([
          'status' => 'completed',
          'completed_at' => now(),
          'reviewer_note' => $note,
      ]);

      $nextTask = ApplicationTask::where('application_id', $task->application_id)
          ->where('position', $task->position + 1)
          ->first();

      if ($nextTask) {
          $nextTask->update(['status' => 'in_progress']);
      }
  });
  ```

  The `$this->auditLog->log('task_completed', ...)` line after the transaction remains unchanged.

- [ ] T005 [US2] Remove auto-reject side effect from `WorkflowService::rejectTask()` in `app/Services/Tasks/WorkflowService.php`.

  The `rejectTask()` method currently reads:
  ```php
  public function rejectTask(ApplicationTask $task, ?string $note): void
  {
      if ($task->status !== 'in_progress') {
          throw new InvalidArgumentException('Only an in_progress task can be rejected.');
      }

      DB::transaction(function () use ($task, $note): void {
          $task->update([
              'status' => 'rejected',
              'completed_at' => now(),
              'reviewer_note' => $note,
          ]);

          $task->application->update(['status' => 'rejected']);
      });

      $this->auditLog->log('task_rejected', $this->actor(), ['task' => $task->name, 'reference' => $task->application->reference_number]);
      $this->auditLog->log('application_rejected', $this->actor(), ['reference' => $task->application->reference_number]);
  }
  ```

  Make two changes:
  1. Inside the `DB::transaction` closure, remove the line `$task->application->update(['status' => 'rejected']);`
  2. After the transaction, remove the line `$this->auditLog->log('application_rejected', ...)`

  The updated method must look exactly like this:
  ```php
  public function rejectTask(ApplicationTask $task, ?string $note): void
  {
      if ($task->status !== 'in_progress') {
          throw new InvalidArgumentException('Only an in_progress task can be rejected.');
      }

      DB::transaction(function () use ($task, $note): void {
          $task->update([
              'status' => 'rejected',
              'completed_at' => now(),
              'reviewer_note' => $note,
          ]);
      });

      $this->auditLog->log('task_rejected', $this->actor(), ['task' => $task->name, 'reference' => $task->application->reference_number]);
  }
  ```

- [ ] T006 [US2] Fix the stale status-check message in `app/Http/Controllers/Reviewer/ApplicationController.php`.

  The `advance()` method currently contains this stale logic that references the now-removed auto-approve:
  ```php
  $successMsg = $application->fresh()->status === 'approved'
      ? __('reviewer.application_approved')
      : __('reviewer.task_advanced');
  ```

  Replace those two lines with a single line:
  ```php
  $successMsg = __('reviewer.task_advanced');
  ```

  The complete updated `advance()` method must look like this:
  ```php
  public function advance(AdvanceTaskRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
  {
      abort_if($task->application_id !== $application->id, 404);
      $this->authorize('advance', $task);
      $this->workflowService->advanceTask($task, $request->input('note'));

      return redirect()->route('reviewer.applications.show', $application)->with('success', __('reviewer.task_advanced'));
  }
  ```

---

## Phase 4: Gap 4 — Atomic Step Transitions [US2]

> Add pessimistic locking inside DB::transaction for all three WorkflowService methods. Do this after Gap 2 cleanup so the code is stable before adding locking.

- [ ] T007 [US2] Add `lockForUpdate()` guard to `WorkflowService::advanceTask()` in `app/Services/Tasks/WorkflowService.php`.

  The `advanceTask()` method currently starts with a status guard check BEFORE the transaction:
  ```php
  public function advanceTask(ApplicationTask $task, ?string $note): void
  {
      if ($task->status !== 'in_progress') {
          throw new InvalidArgumentException('Only an in_progress task can be advanced.');
      }

      DB::transaction(function () use ($task, $note): void {
          // ...
      });
  ```

  Move the status guard INSIDE the transaction with a fresh locked read. Replace the method opening with:
  ```php
  public function advanceTask(ApplicationTask $task, ?string $note): void
  {
      DB::transaction(function () use ($task, $note): void {
          $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

          if ($task->status !== 'in_progress') {
              throw new InvalidArgumentException('Only an in_progress task can be advanced.');
          }

          $task->update([
              'status' => 'completed',
              'completed_at' => now(),
              'reviewer_note' => $note,
          ]);

          $nextTask = ApplicationTask::where('application_id', $task->application_id)
              ->where('position', $task->position + 1)
              ->first();

          if ($nextTask) {
              $nextTask->update(['status' => 'in_progress']);
          }
      });

      $this->auditLog->log('task_completed', $this->actor(), ['task' => $task->name, 'reference' => $task->application->reference_number]);
  }
  ```

  Note: The `$task` variable used by `$this->auditLog->log(...)` after the closure refers to the original `$task` parameter (passed from controller), which still has `->name` and `->application->reference_number` accessible even after the closure redefines `$task` internally. This is correct because the closure's `$task` is scoped to the closure.

- [ ] T008 [US2] Add `lockForUpdate()` guard to `WorkflowService::rejectTask()` in `app/Services/Tasks/WorkflowService.php`.

  Apply the same pattern as T007. Move the status guard inside the transaction with a fresh locked read. The updated method must look exactly like:
  ```php
  public function rejectTask(ApplicationTask $task, ?string $note): void
  {
      DB::transaction(function () use ($task, $note): void {
          $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

          if ($task->status !== 'in_progress') {
              throw new InvalidArgumentException('Only an in_progress task can be rejected.');
          }

          $task->update([
              'status' => 'rejected',
              'completed_at' => now(),
              'reviewer_note' => $note,
          ]);
      });

      $this->auditLog->log('task_rejected', $this->actor(), ['task' => $task->name, 'reference' => $task->application->reference_number]);
  }
  ```

---

## Phase 5: Gap 1 — Re-open Rejected Task [US2]

> Add `reopenTask()` to WorkflowService, create ReopenTaskRequest, add `reopen()` policy method, add `reopen()` controller action, add the route, update the view.

- [ ] T009 [US2] Add `reopenTask()` method to `app/Services/Tasks/WorkflowService.php`.

  Open the file. After the `rejectTask()` method (line 99 in original) and before the `private function actor()` method, insert the new `reopenTask()` method:
  ```php
  public function reopenTask(ApplicationTask $task): void
  {
      DB::transaction(function () use ($task): void {
          $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

          if ($task->status !== 'rejected') {
              throw new InvalidArgumentException('Only a rejected task can be re-opened.');
          }

          $task->update([
              'status'        => 'in_progress',
              'reviewer_note' => null,
              'completed_at'  => null,
          ]);
      });

      $this->auditLog->log('task_reopened', $this->actor(), [
          'task_id'        => $task->id,
          'application_id' => $task->application_id,
          'task_name'      => $task->name,
      ]);
  }
  ```

  After this change the method order in the class is: `seedTasksForApplication`, `advanceTask`, `rejectTask`, `reopenTask`, `actor`.

- [ ] T010 [US2] Create `app/Http/Requests/Reviewer/ReopenTaskRequest.php`.

  Create the file with this exact content:
  ```php
  <?php

  namespace App\Http\Requests\Reviewer;

  use Illuminate\Foundation\Http\FormRequest;

  class ReopenTaskRequest extends FormRequest
  {
      public function authorize(): bool
      {
          return true;
      }

      public function rules(): array
      {
          return [];
      }
  }
  ```

  Note: Authorization is handled by the policy in the controller. No user-submitted fields are needed — the action is the form submission itself.

- [ ] T011 [US2] Add `reopen()` method to `app/Policies/ApplicationTaskPolicy.php`.

  Open the file. It currently contains two methods: `advance()` and `reject()`. Add a third method after `reject()`:
  ```php
  public function reopen(User $user, ApplicationTask $task): bool
  {
      return $user->can('tasks.advance');
  }
  ```

  The complete updated file must look exactly like:
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

      public function reject(User $user, ApplicationTask $task): bool
      {
          return $user->can('tasks.reject');
      }

      public function reopen(User $user, ApplicationTask $task): bool
      {
          return $user->can('tasks.advance');
      }
  }
  ```

- [ ] T012 [US2] Add `reopen()` action to `app/Http/Controllers/Reviewer/ApplicationController.php`.

  Add the import for `ReopenTaskRequest` at the top of the file. The current imports are:
  ```php
  use App\Http\Requests\Reviewer\AdvanceTaskRequest;
  use App\Http\Requests\Reviewer\RejectTaskRequest;
  ```

  Add one more import line:
  ```php
  use App\Http\Requests\Reviewer\ReopenTaskRequest;
  ```

  Then add the `reopen()` method at the end of the class (after the `reject()` method, before the closing `}`):
  ```php
  public function reopen(ReopenTaskRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
  {
      abort_if($task->application_id !== $application->id, 404);
      $this->authorize('reopen', $task);
      $this->workflowService->reopenTask($task);

      return redirect()->route('reviewer.applications.show', $application)->with('success', __('tasks.reopen_success'));
  }
  ```

  The complete updated file must look exactly like:
  ```php
  <?php

  namespace App\Http\Controllers\Reviewer;

  use App\Http\Controllers\Controller;
  use App\Http\Requests\Reviewer\AdvanceTaskRequest;
  use App\Http\Requests\Reviewer\RejectTaskRequest;
  use App\Http\Requests\Reviewer\ReopenTaskRequest;
  use App\Models\ApplicationTask;
  use App\Models\VisaApplication;
  use App\Services\Tasks\WorkflowService;
  use Illuminate\Http\RedirectResponse;
  use Illuminate\View\View;

  class ApplicationController extends Controller
  {
      public function __construct(private WorkflowService $workflowService)
      {
      }

      public function show(VisaApplication $application): View
      {
          $application->loadMissing(['visaType', 'user', 'tasks' => fn ($q) => $q->orderBy('position')->with(['template', 'documents' => fn ($d) => $d->with('uploader')])]);
          $activeTask = $application->tasks->firstWhere('status', 'in_progress');

          return view('reviewer.applications.show', compact('application', 'activeTask'));
      }

      public function advance(AdvanceTaskRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
      {
          abort_if($task->application_id !== $application->id, 404);
          $this->authorize('advance', $task);
          $this->workflowService->advanceTask($task, $request->input('note'));

          return redirect()->route('reviewer.applications.show', $application)->with('success', __('reviewer.task_advanced'));
      }

      public function reject(RejectTaskRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
      {
          abort_if($task->application_id !== $application->id, 404);
          $this->authorize('reject', $task);
          $this->workflowService->rejectTask($task, $request->input('note'));

          return redirect()->route('reviewer.applications.show', $application)->with('success', __('reviewer.task_rejected'));
      }

      public function reopen(ReopenTaskRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
      {
          abort_if($task->application_id !== $application->id, 404);
          $this->authorize('reopen', $task);
          $this->workflowService->reopenTask($task);

          return redirect()->route('reviewer.applications.show', $application)->with('success', __('tasks.reopen_success'));
      }
  }
  ```

- [ ] T013 [US2] Add the reopen route to `routes/web.php`.

  Open the file. Find the reviewer routes group (lines 69–75). It currently ends with:
  ```php
      Route::post('/applications/{application}/tasks/{task}/reject', [ReviewerApplicationController::class, 'reject'])->name('applications.tasks.reject');
      Route::post('/applications/{application}/documents', [ReviewerDocumentController::class, 'store'])->middleware('can:documents.reviewer-upload')->name('applications.documents.store');
  });
  ```

  Insert a new route BETWEEN the reject route and the documents route:
  ```php
      Route::post('/applications/{application}/tasks/{task}/reopen', [ReviewerApplicationController::class, 'reopen'])->middleware('can:tasks.advance')->name('applications.tasks.reopen');
  ```

  After the edit the reviewer group (lines 69–75) must look like:
  ```php
  Route::middleware(['auth', 'verified'])->prefix('reviewer')->name('reviewer.')->group(function () {
      Route::get('/dashboard/{tab?}', [ReviewerDashboardController::class, 'show'])->middleware('can:tasks.view')->name('dashboard');
      Route::get('/applications/{application}', [ReviewerApplicationController::class, 'show'])->middleware('can:tasks.view')->name('applications.show');
      Route::post('/applications/{application}/tasks/{task}/advance', [ReviewerApplicationController::class, 'advance'])->name('applications.tasks.advance');
      Route::post('/applications/{application}/tasks/{task}/reject', [ReviewerApplicationController::class, 'reject'])->name('applications.tasks.reject');
      Route::post('/applications/{application}/tasks/{task}/reopen', [ReviewerApplicationController::class, 'reopen'])->middleware('can:tasks.advance')->name('applications.tasks.reopen');
      Route::post('/applications/{application}/documents', [ReviewerDocumentController::class, 'store'])->middleware('can:documents.reviewer-upload')->name('applications.documents.store');
  });
  ```

- [ ] T014 [US2] Add the Re-open button to `resources/views/reviewer/applications/show.blade.php`.

  Open the file. Find the task action panel (the `@if ($activeTask && $task->id === $activeTask->id)` block at line 64). This block currently shows Advance and Reject forms only when the task is active (`in_progress`).

  The re-open button must appear for rejected tasks — NOT inside the active-task block. It must replace the advance/reject forms when a task is `rejected`.

  Find the closing of the task action panel at line 92:
  ```blade
                      @endif
                  </div>
              @endforeach
  ```

  The complete task card loop body (lines 34–94) must be updated. The current structure of the `@foreach` inner `<div>` is:
  ```blade
  {{-- ... task header ... --}}
  @if ($activeTask && $task->id === $activeTask->id)
      {{-- Advance + Reject forms --}}
  @endif
  ```

  Change it to:
  ```blade
  {{-- ... task header (unchanged) ... --}}
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
      <div class="mt-4 rounded-lg bg-indigo-50/60 p-4">
          <p class="mb-4 text-sm font-medium text-indigo-900">{{ __('tasks.current_step') }}</p>
          <div class="grid gap-4 md:grid-cols-2">
              {{-- Advance Form --}}
              <form method="POST" action="{{ route('reviewer.applications.tasks.advance', [$application, $task]) }}" class="space-y-3">
                  @csrf
                  <label class="block text-sm font-medium text-gray-700" for="advance-note-{{ $task->id }}">{{ __('reviewer.note_label') }}</label>
                  <textarea id="advance-note-{{ $task->id }}" name="note" placeholder="{{ __('reviewer.note_placeholder') }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                  <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 ms-3">{{ __('reviewer.mark_complete') }}</button>
              </form>

              {{-- Reject Form --}}
              <form method="POST" action="{{ route('reviewer.applications.tasks.reject', [$application, $task]) }}" class="space-y-3">
                  @csrf
                  <label class="block text-sm font-medium text-gray-700" for="reject-note-{{ $task->id }}">
                      {{ __('reviewer.reject_reason_label') }} <span class="text-red-500">*</span>
                  </label>
                  <textarea id="reject-note-{{ $task->id }}" name="note" required
                      placeholder="{{ __('reviewer.reject_reason_placeholder') }}"
                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"></textarea>
                  @error('note')
                      <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                  @enderror
                  <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white">{{ __('reviewer.reject') }}</button>
              </form>
          </div>
      </div>
  @endif
  ```

  The key rules:
  - When `$task->status === 'rejected'`: show ONLY the Re-open form (amber button). No advance/reject forms.
  - When task is the `$activeTask` (i.e., `in_progress`): show ONLY the advance/reject forms. No re-open button.
  - When task is `pending` or `completed`: show nothing.

  The full updated `show.blade.php` replacing just the `@foreach` task loop section (lines 33–94) must be:
  ```blade
  @foreach ($application->tasks->sortBy('position') as $task)
      <div class="rounded-lg bg-white p-6 shadow-sm
          {{ $task->status === 'completed' ? 'border-l-4 border-green-500' : '' }}
          {{ $task->status === 'in_progress' ? 'border-l-4 border-indigo-500 ring-1 ring-indigo-100' : '' }}
          {{ $task->status === 'rejected' ? 'border-l-4 border-red-500' : '' }}">
          <div class="flex items-start justify-between gap-4">
              <div>
                  <p class="text-xs text-gray-400 mb-1">{{ __('tasks.step_number', ['number' => $task->position]) }}</p>
                  <h4 class="font-semibold text-gray-900">{{ $task->name }}</h4>
                  @if ($task->description)
                      <p class="mt-1 text-sm text-gray-500">{{ $task->description }}</p>
                  @endif
                  @if ($task->status === 'completed' && $task->completed_at)
                      <p class="mt-2 text-xs text-green-600">{{ __('tasks.completed_on', ['date' => $task->completed_at->format('d M Y')]) }}</p>
                  @endif
                  @if ($task->reviewer_note)
                      <div class="mt-3 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600">
                          <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('reviewer.reviewer_note') }}</p>
                          <p>{{ $task->reviewer_note }}</p>
                      </div>
                  @endif
              </div>
              <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium
                  {{ $task->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                  {{ $task->status === 'in_progress' ? 'bg-indigo-100 text-indigo-700' : '' }}
                  {{ $task->status === 'pending' ? 'bg-gray-100 text-gray-600' : '' }}
                  {{ $task->status === 'rejected' ? 'bg-red-100 text-red-700' : '' }}">
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
              <div class="mt-4 rounded-lg bg-indigo-50/60 p-4">
                  <p class="mb-4 text-sm font-medium text-indigo-900">{{ __('tasks.current_step') }}</p>
                  <div class="grid gap-4 md:grid-cols-2">
                      {{-- Advance Form --}}
                      <form method="POST" action="{{ route('reviewer.applications.tasks.advance', [$application, $task]) }}" class="space-y-3">
                          @csrf
                          <label class="block text-sm font-medium text-gray-700" for="advance-note-{{ $task->id }}">{{ __('reviewer.note_label') }}</label>
                          <textarea id="advance-note-{{ $task->id }}" name="note" placeholder="{{ __('reviewer.note_placeholder') }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                          <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 ms-3">{{ __('reviewer.mark_complete') }}</button>
                      </form>

                      {{-- Reject Form --}}
                      <form method="POST" action="{{ route('reviewer.applications.tasks.reject', [$application, $task]) }}" class="space-y-3">
                          @csrf
                          <label class="block text-sm font-medium text-gray-700" for="reject-note-{{ $task->id }}">
                              {{ __('reviewer.reject_reason_label') }} <span class="text-red-500">*</span>
                          </label>
                          <textarea id="reject-note-{{ $task->id }}" name="note" required
                              placeholder="{{ __('reviewer.reject_reason_placeholder') }}"
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"></textarea>
                          @error('note')
                              <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                          @enderror
                          <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white">{{ __('reviewer.reject') }}</button>
                      </form>
                  </div>
              </div>
          @endif
      </div>
  @endforeach
  ```

---

## Phase 6: Gap 3 — Admin Task Summary [US4]

> Add `withCount()` to the admin query and display the task summary column in the admin applications index.

- [ ] T015 [US4] Add task count eager-loading to `app/Http/Controllers/Admin/ApplicationController.php`.

  Open the file. Find line 25:
  ```php
  $query = VisaApplication::with(['user', 'visaType']);
  ```

  Replace it with:
  ```php
  $query = VisaApplication::with(['user', 'visaType'])->withCount([
      'tasks',
      'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'completed'),
  ]);
  ```

  This adds two virtual properties to each `$app` in the view:
  - `$app->tasks_count` — total number of tasks
  - `$app->completed_tasks_count` — number of tasks with `status = 'completed'`

  No other changes to the controller are needed.

- [ ] T016 [US4] Add task summary column to `resources/views/admin/applications/index.blade.php`.

  Open the file. The `<x-admin.table>` component is called with a `:columns` array:
  ```blade
  <x-admin.table
      :columns="[
          'reference_number' => __('admin.col_reference'),
          'created_at'       => __('admin.col_submitted'),
          'status'           => __('admin.col_status'),
      ]"
  ```

  Add a fourth column entry for tasks:
  ```blade
  <x-admin.table
      :columns="[
          'reference_number' => __('admin.col_reference'),
          'created_at'       => __('admin.col_submitted'),
          'status'           => __('admin.col_status'),
          'tasks'            => __('tasks.progress_summary'),
      ]"
  ```

  Then find the `@foreach($applications as $app)` rows block. Currently it has 4 `<td>` elements (reference, date, status, action link). Insert a new `<td>` BEFORE the action link `<td>`:
  ```blade
  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
      {{ __('tasks.task_summary', ['completed' => $app->completed_tasks_count ?? 0, 'total' => $app->tasks_count ?? 0]) }}
  </td>
  ```

  The complete updated `@foreach` block must look like:
  ```blade
  @foreach($applications as $app)
  <tr>
      <td class="px-6 py-4">
          <span class="font-mono text-sm">{{ $app->reference_number }}</span>
      </td>
      <td class="px-6 py-4">{{ $app->created_at->format('d M Y') }}</td>
      <td class="px-6 py-4">
          <span class="rounded-full px-2 py-1 text-xs font-medium
              {{ $app->status === 'approved' ? 'bg-green-100 text-green-700' :
                 ($app->status === 'pending_review' ? 'bg-yellow-100 text-yellow-700' :
                 'bg-gray-100 text-gray-600') }}">
              {{ $app->status }}
          </span>
      </td>
      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
          {{ __('tasks.task_summary', ['completed' => $app->completed_tasks_count ?? 0, 'total' => $app->tasks_count ?? 0]) }}
      </td>
      <td class="px-6 py-4 whitespace-nowrap">
          <a href="{{ route('admin.applications.documents.index', $app) }}" class="text-sm text-blue-600 hover:underline">
              {{ __('admin.action_view') }}
          </a>
      </td>
  </tr>
  @endforeach
  ```

---

## Phase 7: Tests

> Write feature tests for all 4 gaps. Run the full suite last.

- [X] T017 [P] [US2] Create `tests/Feature/Reviewer/ReopenTaskTest.php` with the following content:

  ```php
  <?php

  namespace Tests\Feature\Reviewer;

  use App\Models\ApplicationTask;
  use App\Models\User;
  use App\Models\VisaApplication;
  use Illuminate\Foundation\Testing\RefreshDatabase;
  use Tests\TestCase;

  class ReopenTaskTest extends TestCase
  {
      use RefreshDatabase;

      private function makeReviewer(): User
      {
          return User::factory()->create()->assignRole('reviewer');
      }

      private function makeApplication(User $reviewer): VisaApplication
      {
          return VisaApplication::factory()
              ->has(ApplicationTask::factory()->state(['status' => 'rejected', 'reviewer_note' => 'Docs missing', 'completed_at' => now()]))
              ->create(['reviewer_id' => $reviewer->id]);
      }

      public function test_reviewer_can_reopen_rejected_task(): void
      {
          $reviewer = $this->makeReviewer();
          $app = $this->makeApplication($reviewer);
          $task = $app->tasks->first();

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
          $app = VisaApplication::factory()
              ->has(ApplicationTask::factory()->state(['status' => 'in_progress']))
              ->create(['reviewer_id' => $reviewer->id]);
          $task = $app->tasks->first();

          $this->actingAs($reviewer)
              ->post(route('reviewer.applications.tasks.reopen', [$app, $task]))
              ->assertStatus(500); // InvalidArgumentException
      }

      public function test_reviewer_without_permission_cannot_reopen(): void
      {
          $client = User::factory()->create()->assignRole('client');
          $reviewer = $this->makeReviewer();
          $app = $this->makeApplication($reviewer);
          $task = $app->tasks->first();

          $this->actingAs($client)
              ->post(route('reviewer.applications.tasks.reopen', [$app, $task]))
              ->assertForbidden();
      }

      public function test_reviewer_cannot_reopen_task_belonging_to_another_application(): void
      {
          $reviewerA = $this->makeReviewer();
          $reviewerB = $this->makeReviewer();
          $appA = $this->makeApplication($reviewerA);
          $appB = $this->makeApplication($reviewerB);
          $taskFromB = $appB->tasks->first();

          // Reviewer A tries to reopen a task belonging to app B
          $this->actingAs($reviewerA)
              ->post(route('reviewer.applications.tasks.reopen', [$appA, $taskFromB]))
              ->assertNotFound();
      }

      public function test_advance_task_does_not_auto_approve_application(): void
      {
          $reviewer = $this->makeReviewer();
          $app = VisaApplication::factory()
              ->has(ApplicationTask::factory()->state(['status' => 'in_progress', 'position' => 1]))
              ->create(['reviewer_id' => $reviewer->id, 'status' => 'in_progress']);
          $task = $app->tasks->first();

          $this->actingAs($reviewer)
              ->post(route('reviewer.applications.tasks.advance', [$app, $task]), ['note' => null])
              ->assertRedirect();

          $app->refresh();
          $this->assertNotEquals('approved', $app->status);
      }

      public function test_reject_task_does_not_auto_reject_application(): void
      {
          $reviewer = $this->makeReviewer();
          $app = VisaApplication::factory()
              ->has(ApplicationTask::factory()->state(['status' => 'in_progress']))
              ->create(['reviewer_id' => $reviewer->id, 'status' => 'in_progress']);
          $task = $app->tasks->first();

          $this->actingAs($reviewer)
              ->post(route('reviewer.applications.tasks.reject', [$app, $task]), ['note' => 'Missing documents'])
              ->assertRedirect();

          $app->refresh();
          $this->assertNotEquals('rejected', $app->status);
      }
  }
  ```

- [X] T018 [P] [US4] Create `tests/Feature/Admin/ApplicationTaskSummaryTest.php` with the following content:

  ```php
  <?php

  namespace Tests\Feature\Admin;

  use App\Models\ApplicationTask;
  use App\Models\User;
  use App\Models\VisaApplication;
  use Illuminate\Foundation\Testing\RefreshDatabase;
  use Tests\TestCase;

  class ApplicationTaskSummaryTest extends TestCase
  {
      use RefreshDatabase;

      private function makeAdmin(): User
      {
          return User::factory()->create()->assignRole('admin');
      }

      public function test_admin_applications_index_shows_task_summary(): void
      {
          $admin = $this->makeAdmin();
          $app = VisaApplication::factory()
              ->has(ApplicationTask::factory()->state(['status' => 'completed']), 'tasks')
              ->has(ApplicationTask::factory()->state(['status' => 'completed']), 'tasks')
              ->has(ApplicationTask::factory()->state(['status' => 'in_progress']), 'tasks')
              ->create();

          $response = $this->actingAs($admin)
              ->get(route('admin.applications.index'));

          $response->assertOk();
          // 2 completed, 3 total
          $response->assertSee('2 / 3');
      }

      public function test_admin_applications_index_shows_zero_summary_when_no_tasks(): void
      {
          $admin = $this->makeAdmin();
          VisaApplication::factory()->create();

          $response = $this->actingAs($admin)
              ->get(route('admin.applications.index'));

          $response->assertOk();
          $response->assertSee('0 / 0');
      }

      public function test_non_admin_cannot_access_applications_index(): void
      {
          $client = User::factory()->create()->assignRole('client');

          $this->actingAs($client)
              ->get(route('admin.applications.index'))
              ->assertForbidden();
      }
  }
  ```

- [ ] T019 Run the targeted test suites and verify they pass:

  ```bash
  php artisan test --filter=ReopenTaskTest
  php artisan test --filter=ApplicationTaskSummaryTest
  ```

  Both must pass with 0 failures. If a test fails, fix the implementation (do NOT change the test assertions to make them pass).

- [ ] T020 Run the full test suite and confirm it stays green:

  ```bash
  php artisan test
  ```

  All tests must pass. If any previously-passing test now fails, it is a regression introduced by this feature — fix it.

---

## Dependency Graph

```
T001 (verify green) → T002, T003
T002 → T004, T005, T016 (lang keys used by view)
T003 (parallel with T002)
T004 → T007 (clean method before adding lock)
T005 → T008 (clean method before adding lock)
T006 (independent — only touches controller message)
T007, T008 → T009 (all three methods in same service)
T009 → T012 (controller needs the service method)
T010 → T012 (controller needs the form request)
T011 → T012 (controller needs the policy method)
T012 → T013 (route needs the controller action)
T013 → T014 (view needs the route name)
T015 → T016 (view needs the controller data)
T017, T018 can be written any time after T004-T016
T019 → T020
```

## Parallel Execution

Within each phase, tasks marked `[P]` can be executed concurrently.

- T002 + T003: Write EN and AR lang keys simultaneously
- T017 + T018: Write both test files simultaneously (they touch different files)

## Implementation Notes

1. **No migrations required** — zero schema changes for this feature.
2. **No new directories required** — `app/Http/Requests/Reviewer/` already exists.
3. **WorkflowService `$task` variable shadowing** (T007, T008, T009): The closure redefines `$task` via `$task = ApplicationTask::lockForUpdate()->findOrFail($task->id)`. This is intentional — the fresh DB read is authoritative inside the transaction. The outer `$task` parameter is still accessible in `$this->auditLog->log(...)` after the closure because PHP closures capture by value (`use ($task)`), so the outer variable is unchanged.
4. **`tasks.task_summary` lang key uses named parameters** — the Blade call must use `['completed' => ..., 'total' => ...]` format matching the `:completed` and `:total` placeholders defined in T002.
5. **Test factories** — if `ApplicationTask::factory()` or `VisaApplication::factory()` do not support a `reviewer_id` column on `visa_applications`, adjust the factory call to match the actual factory signatures. Use `->state([...])` for per-test overrides.
