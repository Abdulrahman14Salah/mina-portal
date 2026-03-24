# Data Model: Task Progression (017)

**Date**: 2026-03-23
**Branch**: `017-task-progression`

---

## Schema Changes

### No new tables or columns required.

All progression state lives in the existing `application_tasks.status` and `visa_applications.status` columns. The `workflow_complete` application status is a new string value on an existing VARCHAR(30) column — no migration needed.

---

## Entity: `visa_applications`

**Relevant column**: `status VARCHAR(30)`

### Status Lifecycle (complete set)

| Status | Meaning | Set by |
|---|---|---|
| `pending_review` | Submitted, awaiting admin action | Application creation (default) |
| `in_progress` | Workflow seeded, client is working | `WorkflowService::seedTasksForApplication` |
| `awaiting_documents` | Client has uploaded at least one document | `DocumentService::upload` |
| `workflow_complete` | All tasks approved; pending final admin sign-off | `WorkflowService::approveTask` / `advanceTask` (NEW) |

**State transition added by this feature**:
```
in_progress / awaiting_documents
    → (last task approved)
    → workflow_complete
```

---

## Entity: `application_tasks`

**Relevant columns**: `status VARCHAR(30)`, `position INT`, `application_id BIGINT`

### Status Lifecycle (unchanged)

| Status | Meaning |
|---|---|
| `pending` | Locked — not yet accessible to client |
| `in_progress` | Active — client can view and submit |
| `approved` | Complete — reviewer approved |
| `rejected` | Returned — client must resubmit |

### Progression Query Pattern

Finding the next task to activate (used in `WorkflowService`):

```
SELECT * FROM application_tasks
WHERE application_id = :appId
  AND position > :currentPosition
ORDER BY position ASC
LIMIT 1
```

Finding whether all tasks are approved (used for `workflow_complete` check):

```
SELECT COUNT(*) FROM application_tasks
WHERE application_id = :appId
  AND status != 'approved'
```

If count = 0 → application has no remaining tasks → set `workflow_complete`.

**Alternative approach used**: Rather than a separate count query, check `$nextTask === null` after the existing next-task query. If there is no task with a higher position, the current task is the last one — set `workflow_complete`. This avoids an extra DB query.

---

## Services Affected

### `WorkflowService` (modified)

**`approveTask(ApplicationTask $task, ?string $note)`**

Existing logic:
1. Lock task row
2. Guard: status must be `in_progress`
3. Set task `status = approved`, `completed_at = now()`, `reviewer_note`
4. Find next task by position
5. If next task exists → set `status = in_progress`
6. *(currently nothing if next task is null)*

**Change**: Add to step 6 — when `$nextTask === null`:
- `$task->application->update(['status' => 'workflow_complete'])`
- Log `workflow_tasks_complete` audit event

**`advanceTask(ApplicationTask $task, ?string $note)`**

Same change applied (mirrors `approveTask` — both do approval, `advanceTask` is the legacy method called from the reviewer panel's "advance" button).

---

## Controllers Affected

### `TaskController` (modified)

**`submitAnswers`**: Add pending-task guard before service call:
```php
if ($task->status === 'pending') {
    return redirect()->back()->with('error', __('tasks.task_locked'));
}
```

**`uploadReceipt`**: Same guard.

---

## Views Affected

### `resources/views/client/dashboard/tabs/tasks.blade.php` (modified)

**Bug fixes** (pre-existing, caused by `completed → approved` rename):
- Line 8: `->where('status', 'completed')` → `->where('status', 'approved')`
- Line 13: `$task->status === 'completed'` badge class → `$task->status === 'approved'`
- Line 21: `$task->status === 'completed'` completed_at check → `approved`
- Line 22: `'tasks.completed_on'` → still valid, but condition uses `approved`
- Line 25: badge class `'completed'` → `'approved'`

**New behaviour**:
- `in_progress` task: wrapped in `<a href="{{ route('client.tasks.show', [$application, $task]) }}">` link
- `approved` task: optionally also linked (read-only view of completed task)
- `pending` task: no link, add `opacity-50 cursor-not-allowed` classes

**`workflow_complete` application banner** (new): When `$application->status === 'workflow_complete'`, show a success banner above the task list.

---

## Language Keys Required

**File**: `resources/lang/en/tasks.php`

| Key | Value |
|---|---|
| `task_locked` | `'This task is not yet available.'` |
| `workflow_complete_title` | `'All Tasks Complete'` |
| `workflow_complete_message` | `'All workflow tasks have been completed. Your application is now under final review.'` |
| `status_approved` | Already present ✓ |
| `status_pending` | Already present ✓ |

**Note**: The dashboard `tasks.blade.php` references `tasks.status_completed` via `__('tasks.status_' . $task->status)`. Since tasks now use `approved` not `completed`, the `status_completed` key is unreachable for tasks (though it may still be referenced by legacy document steps). Keep it; add nothing for it.

---

## Tests Required

**File**: `tests/Feature/Tasks/TaskProgressionTest.php`

| Test | Description |
|---|---|
| `test_approving_task_activates_next_task` | Approve task N → task N+1 becomes `in_progress` |
| `test_approving_last_task_sets_workflow_complete` | Approve final task → application status = `workflow_complete` |
| `test_approving_last_task_does_not_activate_nonexistent_task` | No next task → no exception, no orphan activation |
| `test_advancing_task_activates_next_task` | Via `advanceTask` path (same logic, different method) |
| `test_advancing_last_task_sets_workflow_complete` | Via `advanceTask` path |
| `test_pending_task_submit_is_rejected` | POST answers to pending task → redirect with error |
| `test_pending_task_receipt_upload_is_rejected` | POST receipt to pending task → redirect with error |
| `test_dashboard_shows_active_task_link` | `in_progress` task card contains link to task show page |
| `test_dashboard_pending_task_has_no_link` | `pending` task card contains no link to task show page |
| `test_dashboard_approved_task_count_is_correct` | Progress counter reflects `approved` (not `completed`) status |
