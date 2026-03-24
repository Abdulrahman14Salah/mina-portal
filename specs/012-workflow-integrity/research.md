# Research: Workflow Integrity Fixes

**Branch**: `012-workflow-integrity` | **Date**: 2026-03-22 | **Phase**: 0

No external unknowns required research. All decisions are internal PHP/Laravel patterns applied to the existing codebase.

---

## Decision 1 — Audit Log Guard: Boolean Flag vs. Return Value

**Decision**: Use a `bool $seeded` variable captured by reference (`&$seeded`) in the `DB::transaction` closure.

**Rationale**: The `return` inside a closure only exits the closure, not the outer method — the root cause of the bug. Capturing `$seeded` by reference is the minimal, idiomatic PHP fix. The outer method then wraps the audit log call in `if ($seeded)`. No refactoring of the transaction structure is needed.

**Alternatives Considered**:
- **Return a bool from the closure**: PHP `DB::transaction()` returns whatever the closure returns, but using a return value requires changing the transaction call site and handling `null`/`false` explicitly. More fragile.
- **Extract a separate private method**: Over-engineering for a one-line condition change.
- **Restructure to avoid the nested closure**: Would require significant refactoring and risks breaking the existing lock-for-update pattern. Rejected.

---

## Decision 2 — Next-Task Query: Ordered Position vs. Equality

**Decision**: Replace `->where('position', $task->position + 1)->first()` with `->where('position', '>', $task->position)->orderBy('position')->first()`.

**Rationale**: The current equality lookup assumes task positions are always contiguous integers. The `WorkflowService::seedTasksForApplication()` legacy path uses `$template->position` values from `WorkflowStepTemplate`, which may have gaps. The ordered-greater-than query is equivalent for contiguous positions (both select the same next task) but also handles gaps — making it strictly safer with zero regression risk.

**Alternatives Considered**:
- **Enforce contiguous positions in seeding**: Would require a migration + data fix for existing records and adds a constraint that doesn't match the domain. Rejected.
- **Use `LIMIT 1 OFFSET 1` subquery**: More complex SQL, no benefit over `>` with `orderBy`. Rejected.
- **Fix the fix in the seeder instead**: The seeder already uses `$position++` for section-based tasks (contiguous). But the legacy template path is the risk vector and fixing the query is simpler and covers both paths. Chosen.

---

## Decision 3 — Scope: Both `advanceTask` and `approveTask`

**Decision**: Apply the next-task fix to both `advanceTask()` and `approveTask()` independently.

**Rationale**: Both methods contain identical position-lookup code (lines 98–100 and 125–127 respectively). They are separate code paths and must each be fixed. The audit log fix applies only to `seedTasksForApplication()`.

---

## Decision 4 — No New Migrations or Schema Changes

**Decision**: Zero schema changes for this feature.

**Rationale**: Both fixes are query logic changes. The `application_tasks.position` column already exists and is already an integer. No new columns, tables, or indexes are required.

---

## Decision 5 — Test Strategy

**Decision**: Add targeted feature tests in a new `tests/Feature/Tasks/WorkflowIntegrityTest.php` file. Do not modify existing test files or assertions.

**Rationale**: The spec explicitly forbids modifying existing test assertions (SC-003). New tests must cover: (a) no audit log when no tasks seeded, (b) correct audit log when tasks are seeded, (c) next task found with contiguous positions, (d) next task found with non-contiguous positions, (e) no next-task activation on final task. Existing passing tests validate the regression constraint.
