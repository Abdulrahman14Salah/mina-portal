# Data Model: Reviewer Validation (018)

## Schema Changes

### 1. workflow_tasks — Add `approval_mode`

**Migration**: `add_approval_mode_to_workflow_tasks`

```
workflow_tasks
├── ... (existing columns unchanged)
└── approval_mode  varchar(10) nullable default null
                   values: 'auto' | 'manual' | null (null = 'manual')
                   only meaningful when type = 'question'
                   payment tasks always require manual approval regardless
```

**Model update**: `WorkflowTask`
- Add `approval_mode` to `$fillable`
- Add validation in `booted()`: if type='question', approval_mode must be 'auto', 'manual', or null

### 2. application_tasks — Add `pending_review` status, `reviewed_by`, `reviewed_at`, `approval_mode`

**Migration**: `add_reviewer_fields_to_application_tasks`

```
application_tasks
├── ... (existing columns unchanged)
├── approval_mode  varchar(10) nullable  — copied from workflow_tasks at seed time
├── reviewed_by    bigint FK nullable    → users (nullOnDelete)
└── reviewed_at    timestamp nullable
```

**Status lifecycle** (updated):
```
pending → in_progress → pending_review → approved
                     ↑                ↓
                     └────────── rejected → (reopen) → in_progress

in_progress → approved  (auto path: question tasks with approval_mode='auto')
```

Valid status values: `'pending'`, `'in_progress'`, `'pending_review'`, `'approved'`, `'rejected'`

**Model update**: `ApplicationTask`
- Add `approval_mode`, `reviewed_by`, `reviewed_at` to `$fillable`
- Add cast: `reviewed_at` → datetime
- Add relationship: `reviewer()` → BelongsTo User (via `reviewed_by`)

---

## Service Changes

### WorkflowService — Method Updates & Additions

```
submitForReview(ApplicationTask $task): void
    Guard:   status must be 'in_progress'
    Action:  status → 'pending_review'
    Side fx: Mail::to(reviewer)->queue(new TaskSubmittedForReviewMail($task))  [if reviewer assigned]
             auditLog->log('task_submitted_for_review', actor, [task_id, application_id, task_name])

approveTask(ApplicationTask $task, ?string $note): void  [UPDATED]
    Guard:   status must be 'pending_review' (updated from 'in_progress')
    Action:  status → 'approved', completed_at → now(), reviewer_note → note,
             reviewed_by → actor()->id, reviewed_at → now()
             advance next task
    Audit:   'task_approved'

rejectTask / rejectTaskWithReason [UPDATED]
    Guard:   status must be 'pending_review' (updated from 'in_progress')
    Action:  reviewed_by → actor()->id, reviewed_at → now()  (added)
    Rest:    unchanged

autoCompleteTask(ApplicationTask $task): void  [NEW — internal]
    Called by: TaskAnswerService after submitAnswers() when approval_mode='auto'
    Guard:   status must be 'in_progress'
    Action:  status → 'approved', completed_at → now(), reviewed_at → now()
             reviewed_by → null (system action)
             advance next task
    Audit:   'task_auto_completed'
```

### TaskAnswerService — submitAnswers() Update

```
After upserting answers, check:
  if task->approval_mode === 'auto':
    call WorkflowService::autoCompleteTask($task)
  else:
    (no status change — client must explicitly submit for review)
```

---

## New Mail Class

```
app/Mail/TaskSubmittedForReviewMail.php
    implements: Mailable, ShouldQueue
    constructor: public ApplicationTask $task
    envelope:   subject → __('tasks.submitted_for_review_subject')
    content:    view → mail.task-submitted-for-review
    to:         $task->application->assignedReviewer->email
```

**View**: `resources/views/mail/task-submitted-for-review.blade.php`
- RTL support (same pattern as payment-confirmed)
- Shows: application reference, task name, task type, link to reviewer application view

---

## New Form Requests

```
app/Http/Requests/Client/SubmitTaskForReviewRequest.php
    authorize: $user->can('submitForReview', $task)
    rules:     [] (no body required — action is the intent)

app/Http/Requests/Reviewer/ApproveTaskRequest.php
    authorize: $user->can('approve', $task)
    rules:     ['note' => 'nullable|string|max:1000']

app/Http/Requests/Reviewer/RejectTaskRequest.php
    authorize: $user->can('reject', $task)
    rules:     ['rejection_reason' => 'required|string|min:5|max:2000']
```

*Note: SaveDraftRequest not needed — existing answer/receipt submission routes handle draft saves without status change.*

---

## Policy Updates

### ApplicationTaskPolicy

```php
submitForReview(User $user, ApplicationTask $task): bool
    → $user->can('tasks.submit-for-review')
      AND $task->application->user_id === $user->id
      AND $task->status === 'in_progress'

// Updated guards (approve/reject now require pending_review, not in_progress):
approve(User $user, ApplicationTask $task): bool
    → existing logic unchanged (policy checks actor, not status — status guard is in service)

reject(User $user, ApplicationTask $task): bool
    → existing logic unchanged
```

---

## New Permission

```
tasks.submit-for-review   → seeded for 'client' role
```

---

## Reviewer Dashboard Query

```php
// ReviewerDashboardController
$pendingReviewCount = ApplicationTask::whereHas('application', function ($q) use ($user) {
        $q->where('assigned_reviewer_id', $user->id);
    })
    ->where('status', 'pending_review')
    ->count();
```

Passed to view as `$pendingReviewCount` for badge display.

---

## Audit Events Added

| Event | Trigger |
|-------|---------|
| `task_submitted_for_review` | client submits task for review |
| `task_auto_completed` | question task auto-completes on submission |

---

## No New Tables Required

All state is stored in existing tables via new columns. The `audit_logs` table covers the full approval event history (FR-011).
