# Quickstart: Workflow Integrity Fixes

**Branch**: `012-workflow-integrity` | **Date**: 2026-03-22

---

## What This Feature Fixes

Two silent bugs in `WorkflowService`:

1. **False audit log**: `workflow_started` was logged even when no tasks existed for the visa type, producing phantom audit entries.
2. **Broken next-task lookup**: Advancing/approving a task used `position = current + 1` which silently fails when position numbers have gaps.

Both fixes are targeted changes to `app/Services/Tasks/WorkflowService.php` only.

---

## Acceptance Test Scenarios

### Scenario 1 — No audit log when visa type has no workflow tasks

1. Create a visa type with no workflow sections or templates.
2. Submit a new visa application for that visa type.
3. Trigger workflow seeding.
4. **Expect**: No `workflow_started` audit log entry for the application.
5. **Expect**: Application status is NOT `in_progress`.

### Scenario 2 — Audit log created when tasks are seeded

1. Create a visa type with at least one workflow section containing at least one task.
2. Submit a new visa application for that visa type.
3. Trigger workflow seeding.
4. **Expect**: Exactly one `workflow_started` audit log entry for the application.
5. **Expect**: Application status is `in_progress`.
6. **Expect**: The first task (lowest position) has status `in_progress`.

### Scenario 3 — Re-seeding does not create a duplicate audit log entry

1. Use an application that already has tasks seeded (Scenario 2 state).
2. Trigger seeding again.
3. **Expect**: No new `workflow_started` entry is created.
4. **Expect**: No duplicate tasks are created.

### Scenario 4 — Next task advances correctly with contiguous positions

1. Create a workflow with tasks at positions 1, 2, 3.
2. Start the workflow (task 1 is `in_progress`).
3. Advance task at position 1.
4. **Expect**: Task at position 1 is now `approved`.
5. **Expect**: Task at position 2 is now `in_progress`.

### Scenario 5 — Next task advances correctly with non-contiguous positions

1. Create a workflow with tasks at positions 1, 3, 5.
2. Start the workflow (task at position 1 is `in_progress`).
3. Advance task at position 1.
4. **Expect**: Task at position 1 is now `approved`.
5. **Expect**: Task at position 3 is now `in_progress` (not a non-existent position 2).
6. **Expect**: Task at position 5 is still `pending`.

### Scenario 6 — Advancing final task ends workflow cleanly

1. Create a workflow with a single task (or advance to the last task).
2. Advance the final task.
3. **Expect**: Final task is now `approved`.
4. **Expect**: No errors occur. No other tasks change status.

### Scenario 7 — Approve action has identical next-task behaviour

1. Create a workflow with tasks at positions 1, 3, 5.
2. Start the workflow (task at position 1 is `in_progress`).
3. **Approve** (not advance) task at position 1.
4. **Expect**: Task at position 3 becomes `in_progress` — same as Scenario 5.

---

## Running Tests

```bash
php artisan test --filter WorkflowIntegrity
```

Full regression check:

```bash
php artisan test
```

All pre-existing tests must continue to pass.

---

## Files Changed

| File | Status | Purpose |
|------|--------|---------|
| `app/Services/Tasks/WorkflowService.php` | Updated | Fix audit log condition + fix next-task query in advanceTask and approveTask |
| `tests/Feature/Tasks/WorkflowIntegrityTest.php` | New | Feature tests for both fixes |
