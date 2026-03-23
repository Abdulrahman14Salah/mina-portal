# Quickstart: Static Workflow Structure

**Branch**: `013-static-workflow-structure` | **Date**: 2026-03-22

---

## What This Feature Delivers

Phase 1 of the task-based visa workflow system. It establishes the data foundation:

1. **Extended task types** — `workflow_tasks.type` now accepts `question`, `payment`, and `info` alongside the legacy `upload` type. Invalid types are rejected.
2. **Position uniqueness** — Sections are uniquely ordered within a visa type; tasks are uniquely ordered within a section.
3. **Seeded blueprint** — The "Tourist Visa" type has a complete, real workflow blueprint: 4 sections, 8 tasks, all three new types represented.

No client-facing pages. No task progression logic. Data foundation only.

---

## Acceptance Test Scenarios

### Scenario 1 — Blueprint retrieval returns sections in position order

1. Run `WorkflowBlueprintSeeder` for "Tourist Visa".
2. Query `WorkflowSection::where('visa_type_id', $touristVisa->id)->orderBy('position')->get()`.
3. **Expect**: Exactly 4 sections returned.
4. **Expect**: First section is "Personal Information" at position 1.
5. **Expect**: Fourth section is "Final Submission" at position 4.

### Scenario 2 — Tasks within a section are returned in position order

1. Load section "Documentation" from the seeded Tourist Visa blueprint.
2. Query `$section->tasks`.
3. **Expect**: Exactly 2 tasks returned.
4. **Expect**: First task is type `payment` at position 1.
5. **Expect**: Second task is type `info` at position 2.

### Scenario 3 — All three new task types are accepted

1. Create a `WorkflowTask` with `type = 'question'` → **no error**.
2. Create a `WorkflowTask` with `type = 'payment'` → **no error**.
3. Create a `WorkflowTask` with `type = 'info'` → **no error**.
4. Create a `WorkflowTask` with `type = 'foobar'` → **`InvalidArgumentException` thrown**.

### Scenario 4 — Legacy `upload` type is still accepted

1. Create a `WorkflowTask` with `type = 'upload'`.
2. **Expect**: No error. Task is saved and retrieved successfully.

### Scenario 5 — Duplicate section position is rejected

1. Seed a section at position 1 for a visa type.
2. Attempt to seed a second section at position 1 for the same visa type.
3. **Expect**: Database integrity exception (unique constraint violation).

### Scenario 6 — Duplicate task position within section is rejected

1. Seed a task at position 1 for a section.
2. Attempt to seed a second task at position 1 for the same section.
3. **Expect**: Database integrity exception (unique constraint violation).

### Scenario 7 — Multiple visa types have independent blueprints

1. Seed blueprints for "Tourist Visa" and "Work Permit" with different section counts.
2. Query sections for "Tourist Visa" → returns only Tourist Visa sections.
3. Query sections for "Work Permit" → returns only Work Permit sections.

### Scenario 8 — Blueprint modifications do not affect existing application tasks

1. Seed blueprint and generate application tasks (use `WorkflowService::seedTasksForApplication`).
2. Update the blueprint task name.
3. **Expect**: Existing `application_tasks` record retains the original name.

---

## Running Tests

```bash
php artisan test --filter WorkflowStructure
```

Full regression check:

```bash
php artisan test
```

All 175 pre-existing tests must continue to pass.

---

## Files Changed

| File | Status | Purpose |
|------|--------|---------|
| `database/migrations/XXXX_update_workflow_tasks_type_column.php` | New | Change type column from ENUM to string |
| `database/migrations/XXXX_add_unique_constraints_workflow_sections_tasks.php` | New | Add unique composite indexes |
| `app/Models/WorkflowTask.php` | Updated | Add `VALID_TYPES` constant + `saving` model hook |
| `app/Http/Requests/Admin/StoreWorkflowTaskRequest.php` | Updated | Update type validation to include new types |
| `database/seeders/WorkflowBlueprintSeeder.php` | New | Seed Tourist Visa with 4 sections, 8 tasks |
| `database/seeders/DatabaseSeeder.php` | Updated | Call `WorkflowBlueprintSeeder` after `VisaTypeSeeder` |
| `tests/Feature/Tasks/WorkflowStructureTest.php` | New | Feature tests for all acceptance scenarios |
