# Research: Task Progression (017)

**Date**: 2026-03-23
**Branch**: `017-task-progression`

---

## Decision 1: Progression Hook Location

**Decision**: Extend the existing `WorkflowService::approveTask()` and `WorkflowService::advanceTask()` methods — do NOT create a new service.

**Rationale**: Both methods already contain the full next-task activation logic (lines 104–111 / 132–139). They use `lockForUpdate()` for concurrent-approval safety and run inside `DB::transaction`. The only missing piece is the `workflow_complete` application status transition when `$nextTask` is null. Adding 2–3 lines to the existing null-check branch is the minimal, correct change.

**Alternatives considered**:
- New `ProgressionService`: Rejected — adds a new class for logic that is already colocated with approval. Would require injecting the new service into the reviewer controller or hooking it to an event, both of which add complexity with no benefit.
- Model observer on `ApplicationTask`: Rejected — observers fire outside the transaction boundary and make the progression logic invisible to readers of the service.

---

## Decision 2: `workflow_complete` Status — No Migration Required

**Decision**: Set `visa_applications.status = 'workflow_complete'` directly; no schema migration is needed.

**Rationale**: The `status` column is `string(30)` (plain VARCHAR), not an enum. `'workflow_complete'` is 17 characters, well within the limit. The existing codebase already sets arbitrary string statuses (`'in_progress'`, `'awaiting_documents'`, `'pending_review'`) without enum constraints. Adding a new value requires only code changes, not a migration.

**Alternatives considered**:
- Add enum constraint via migration: Rejected — there is no existing enum; adding one now would be an unnecessary schema change that constrains future status values.
- Reuse `'approved'` as terminal status: Rejected — per clarification Q2, `workflow_complete` is an intermediate state. Final admin approval is a separate step. Reusing `'approved'` would conflate two different states.

---

## Decision 3: Locked Task Submit/Upload Blocking — Controller Layer

**Decision**: Block pending-task submissions at the `TaskController` action level (before the service) with a redirect + flash error, not a 403 abort.

**Rationale**: The services (`TaskAnswerService::submitAnswers`, `uploadReceipt`) already throw `InvalidArgumentException` for non-`in_progress` tasks, which would bubble as a 500. Catching it in the controller is fragile. Adding an explicit `if ($task->status === 'pending') return redirect()->back()->with('error', ...)` at the top of each action is simple, direct, and gives the client a clean UX response rather than an error page.

**Alternatives considered**:
- Policy `update` gate on `ApplicationTask`: Considered — a Policy method `submit` that checks `status !== 'pending'` would be cleaner architecturally, but the existing `ApplicationTaskPolicy` has no client-facing gates and adding one for this edge case adds more indirection than benefit.
- Form Request `authorize()`: Rejected — `authorize()` in a FormRequest runs before route model binding resolution in some Laravel versions; injecting the task model there is non-standard and brittle.

---

## Decision 4: Dashboard Task List — Links and State Rendering

**Decision**: Add `route('client.tasks.show', [$application, $task])` links to `in_progress` and `approved` tasks only. Pending tasks render as non-interactive cards (no link, reduced opacity). Fix the stale `'completed'` references in `tasks.blade.php` to use `'approved'`.

**Rationale**:
1. The `tasks.blade.php` dashboard tab was written before the `completed → approved` rename migration (`2026_03_22_200001`). Line 8, 13, 21, 22, and 25 all reference `'completed'` — this is a pre-existing bug that means approved tasks currently render with the wrong badge color (no color at all) and the progress counter shows 0/N.
2. FR-009 requires locked tasks be visually distinct and not clickable. Making only `in_progress` and `approved` tasks linkable satisfies this with minimal template change.

**Alternatives considered**:
- Disable pointer-events via CSS only: Rejected — a disabled link still has an accessible href and can be navigated to by determined users. Not providing the href at all is correct.

---

## Decision 5: Audit Log for Workflow Completion

**Decision**: Log a `workflow_tasks_complete` event on the application when the last task is approved and the application transitions to `workflow_complete`.

**Rationale**: Constitution Principle XI requires logging "task step completed or rejected." The application-level completion is a higher-order event (all steps done) and is specifically relevant for dispute resolution and compliance tracking. The existing `task_approved` event still fires for the final task; the new `workflow_tasks_complete` event is additive.

**Alternatives considered**:
- No separate log entry: Rejected — the `workflow_complete` status transition on the application is operationally significant and should be independently queryable in the audit log.

---

## Decision 6: Idempotency for Re-Approval

**Decision**: The existing `lockForUpdate()` + status check (`if ($task->status !== 'in_progress') throw InvalidArgumentException`) already enforces idempotency. An already-approved task cannot be re-approved — the guard throws before any state change. FR-007 is satisfied without additional code.

**Rationale**: `lockForUpdate()` prevents concurrent reads of the same row before the transaction commits. The first approval sets status to `approved`; any concurrent second approval reads `status = approved` inside the lock and throws immediately. No duplicate activation is possible.

**Alternatives considered**:
- Optimistic locking (version column): Rejected — `lockForUpdate` (pessimistic) is already in place and sufficient for this low-contention scenario.
