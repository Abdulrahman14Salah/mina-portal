# Data Model: Task Page UI (016)

## Schema Changes

### `application_tasks` — Add `workflow_task_id` column

**Migration**: `2026_03_23_200001_add_workflow_task_id_to_application_tasks.php`

| Column | Type | Nullable | Default | FK |
|--------|------|----------|---------|-----|
| `workflow_task_id` | `unsignedBigInteger` | YES | `null` | `workflow_tasks.id` ON DELETE SET NULL |

**Backfill**: No backfill needed — existing rows remain `null`. The column is nullable so legacy applications seeded via flat templates are unaffected.

**Populated by**: `WorkflowService::seedTasksForApplication()` (section-based path) — set to `$workflowTask->id` at seed time.

---

## Model Changes

### `ApplicationTask` — New relationship

```
workflowTask(): BelongsTo → WorkflowTask (via workflow_task_id)
```

Used by `TaskController::show()` to eager-load `workflowTask.questions` for question-type task pages.

---

## Service Changes

### `TaskAnswerService::submitAnswers`

**Status guard change**: Accept both `in_progress` and `rejected` (throw for all other statuses).

**Auto-reopen logic** (runs inside DB transaction, after answers stored):
- If `$task->status === 'rejected'`: update status → `in_progress`, clear `rejection_reason`

**Audit log**: existing `task_answers_submitted` event unchanged.

---

## Controller Changes

### `TaskController::show()`

**Pending redirect**: If `$task->status === 'pending'`, redirect to `client.dashboard` before any authorization or rendering.

**Eager loading** (replaces current `loadMissing('documents')`):
```
$task->loadMissing([
    'documents',
    'answers',
    'workflowTask.questions' => fn($q) => $q->orderBy('position'),
])
```

**Data passed to view**:
```
$application   — VisaApplication
$task          — ApplicationTask (with relations loaded)
$answers       — $task->answers->keyBy('task_question_id')  [for template O(1) lookup]
```

---

## View Changes

### `resources/views/client/tasks/show.blade.php` — Full rewrite

Shared sections (all types):
- Flash messages (success / error)
- Task header: step number, title, description, status badge
- Reviewer note (when present)
- Rejection reason banner (when `status === 'rejected'`)
- Type-specific partial (via `@include`)
- Back link to dashboard

### New partials under `resources/views/client/tasks/partials/`

| File | Renders when |
|------|-------------|
| `_question-form.blade.php` | `type === 'question'` AND `status` in `[in_progress, rejected]` |
| `_answers-readonly.blade.php` | `type === 'question'` AND `status === 'approved'` |
| `_no-questions.blade.php` | `type === 'question'` AND `workflowTask->questions` is empty |
| `_payment-form.blade.php` | `type === 'payment'` AND `status` in `[in_progress, rejected]` |
| `_receipt-readonly.blade.php` | `type === 'payment'` AND `status === 'approved'` |
| `_info-content.blade.php` | `type === 'info'` (all statuses) |

---

## Language Keys (additions to `resources/lang/en/tasks.php`)

| Key | Value |
|-----|-------|
| `your_answers` | `Your Answers` |
| `no_questions_defined` | `No questions are required for this task. Awaiting reviewer action.` |
| `submit_answers` | `Submit Answers` |
| `payment_receipt` | `Payment Receipt` |
| `current_receipt` | `Current Receipt` |
| `replace_receipt` | `Replace Receipt` |
| `upload_receipt` | `Upload Receipt` |
| `info_task_note` | `This task contains information only. No action is required from you.` |
| `answers_readonly_title` | `Submitted Answers` |

---

## No New Tables

All data for this feature already exists in:
- `task_questions` (Phase 3) — question definitions
- `task_answers` (Phase 3) — stored answers
- `documents` (Phase 3/4) — payment receipts

The only schema change is the `workflow_task_id` FK column added to `application_tasks`.
