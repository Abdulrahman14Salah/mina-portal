# Research: Workflow System (Core System)

**Date**: 2026-03-21
**Feature**: 009-workflow-system

## Summary

Research confirmed that the existing codebase implements the majority of the workflow system. This document records decisions for the four remaining gaps and any patterns researched to support them.

---

## Finding 1: Re-open Task Step — Design Pattern

**Question**: How should `reopenTask()` reset a rejected step without affecting completed steps?

**Decision**: `reopenTask()` sets the rejected `ApplicationTask` status back to `in_progress`, clears `reviewer_note` and `completed_at`, and logs a `task_reopened` audit event. No other tasks are affected.

**Rationale**:
- The `application_tasks` table stores one record per task (not per step) — "re-opening a step" means resetting the task record itself back to `in_progress`.
- The spec model uses "step" terminology but the implementation uses `ApplicationTask` records, where each task has a position and progresses sequentially. A "rejected step" = a rejected `ApplicationTask`.
- Clearing `reviewer_note` on re-open is correct: the old rejection reason no longer applies once the reviewer has decided to give it another chance. The audit log preserves the full history.
- The task before the re-opened one remains `completed` — position ordering is preserved.

**Alternatives considered**:
- Creating a separate `task_history` table to preserve rejection state before re-opening — rejected; over-engineering for Phase 2. Audit log already captures the event trail.
- Adding a `reopened_at` timestamp to `application_tasks` — deferred; not required by spec.

---

## Finding 2: Removing Auto Application Status Transitions

**Question**: The existing `WorkflowService` auto-sets `application.status → 'approved'` on last task completion and `application.status → 'rejected'` on task rejection. The spec clarification requires no automatic status transitions. How should this be removed cleanly?

**Decision**: Remove both auto-transitions from `WorkflowService`. The `VisaApplication` status after workflow events will remain unchanged (e.g., `in_progress`). Admin manually updates application status via the admin panel.

**Rationale**:
- The spec clarification is explicit: "application status is always changed manually by an admin."
- Removing auto-transitions also simplifies `reopenTask()` — there is no need to reverse an auto-applied status.
- The audit log events `task_completed` and `task_rejected` are preserved; only the application status update side-effect is removed.
- The `application_approved` and `application_rejected` audit log events that were fired alongside the auto-transitions should also be removed from `WorkflowService` (they remain accurate when admin manually updates status — that event belongs in the admin action, not the workflow service).

**Alternatives considered**:
- Keeping auto-reject but removing auto-approve — rejected; inconsistent and contradicts the clarification.
- Feature-flagging the auto-transitions — rejected; unnecessary complexity for a single-tenant portal.

---

## Finding 3: Atomic Step Transitions

**Question**: How should concurrent advance/reject requests on the same task be prevented from creating inconsistent state?

**Decision**: Wrap `advanceTask()`, `rejectTask()`, and `reopenTask()` in `DB::transaction()` with a pessimistic lock (`lockForUpdate()`) on the `ApplicationTask` record before the status check.

**Rationale**:
- A pessimistic lock (`SELECT ... FOR UPDATE`) inside a transaction ensures the second concurrent request blocks until the first commits, then finds the task no longer `in_progress` and throws the guard exception.
- This is the idiomatic Laravel approach for workflow state machines and requires no additional infrastructure.
- The `WorkflowService` already uses `DB::transaction()` in `seedTasksForApplication()` — extending this pattern is consistent.

**Pattern**:
```
DB::transaction(function() use ($task) {
    $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);
    // status guard check
    // state mutation
});
```

**Alternatives considered**:
- Optimistic locking with a `version` column — rejected; requires schema change and retry logic. Pessimistic lock is simpler for a low-concurrency portal.
- Application-level mutex (cache lock) — rejected; adds Redis/cache dependency. DB-level lock is sufficient.

---

## Finding 4: Admin Task Status Summary

**Question**: How should the admin applications list display a task summary (e.g., "3/5 tasks complete") without N+1 queries?

**Decision**: Add `withCount` scopes to the `AdminApplicationController::index()` query: `tasks` (total) and a filtered count for `completed` tasks. Pass counts to the view via the existing application collection.

**Rationale**:
- Laravel's `withCount()` + `loadCount()` with a constraint generates efficient SQL (`COUNT(CASE WHEN status = 'completed' THEN 1 END)`) without additional queries per application row.
- No schema change is required — counts are derived at query time from existing `application_tasks` data.

**Pattern**:
```php
VisaApplication::withCount([
    'tasks',
    'tasks as completed_tasks_count' => fn($q) => $q->where('status', 'completed'),
])->paginate();
```

---

## Pre-existing Implementation Inventory

| Item | Location | Status |
|---|---|---|
| Task seeding (idempotent) | `WorkflowService::seedTasksForApplication()` | ✅ Done |
| Sequential step enforcement | `advanceTask()` / `rejectTask()` status guard | ✅ Done |
| Reviewer advance/reject | `ReviewerApplicationController` + `WorkflowService` | ✅ Done |
| Client task dashboard | `tasks.blade.php` | ✅ Done |
| Permission-based authorization | `ApplicationTaskPolicy` + `spatie/laravel-permission` | ✅ Done |
| Audit logging | `AuditLogService` — `task_completed`, `task_rejected` | ✅ Done |
| 122 passing workflow tests | `WorkflowTest`, `WorkflowTemplateTest` | ✅ Done |
| Artisan seed command | `SeedApplicationWorkflowTasks` | ✅ Done |
