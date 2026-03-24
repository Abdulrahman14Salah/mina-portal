# Quickstart & Acceptance Scenarios: Application Task Generation (014)

## Prerequisites

- Seeders run: `RolePermissionSeeder`, `VisaTypeSeeder`, `WorkflowBlueprintSeeder`
- Tourist Visa blueprint has 4 sections × 2 tasks = 8 total `workflow_tasks`

---

## Acceptance Scenario 1 — Full task set generated on application creation (US1)

**Goal**: Verify that submitting a visa application via `OnboardingService` creates the correct task set.

**Steps**:
1. Ensure Tourist Visa has 8 workflow tasks seeded via `WorkflowBlueprintSeeder`.
2. Submit a new application for Tourist Visa through `OnboardingService::handle()`.
3. Query `application_tasks` for the new application.

**Expected**:
- Exactly 8 `ApplicationTask` rows exist for the application.
- Each task's `name` and `type` match the corresponding `WorkflowTask` blueprint.
- Tasks are ordered by `position` 1–8.

---

## Acceptance Scenario 2 — First task is `in_progress`, rest are `pending` (US2)

**Goal**: Verify initial status assignment.

**Steps**:
1. Create an application with 8 blueprint tasks.
2. Call `WorkflowService::seedTasksForApplication($application)`.
3. Inspect all application tasks ordered by `position`.

**Expected**:
- Task at position 1 has `status = 'in_progress'`.
- Tasks at positions 2–8 each have `status = 'pending'`.
- The `visa_applications` row has `status = 'in_progress'`.

---

## Acceptance Scenario 3 — Single-task blueprint assigns `in_progress` correctly (US2)

**Goal**: Edge case where blueprint has exactly one task.

**Steps**:
1. Create a visa type with one workflow section containing one workflow task.
2. Create an application for that visa type and seed tasks.

**Expected**:
- Exactly 1 `ApplicationTask` row exists.
- That task has `status = 'in_progress'`.

---

## Acceptance Scenario 4 — Blueprint changes do not affect existing application tasks (US3)

**Goal**: Verify task independence after creation.

**Steps**:
1. Create an application and seed tasks (8 tasks from Tourist Visa blueprint).
2. Rename a blueprint `WorkflowTask` (e.g., "Application Fee Payment" → "Processing Fee Payment").
3. Re-query the `application_tasks` for the application.

**Expected**:
- The `ApplicationTask` still has `name = 'Application Fee Payment'`.
- No application task values have changed.

---

## Acceptance Scenario 5 — No tasks generated for visa type without blueprint (FR-006)

**Goal**: Graceful no-op for blueprint-less visa types.

**Steps**:
1. Create a visa type with no `workflow_sections`.
2. Create an application for that visa type and call `seedTasksForApplication()`.

**Expected**:
- Zero `ApplicationTask` rows for the application.
- Application is created successfully with `status = 'pending_review'` (unchanged, not `in_progress`).
- No exception thrown.

---

## Acceptance Scenario 6 — Idempotency: double seeding does not duplicate tasks (FR-008)

**Goal**: Calling `seedTasksForApplication()` twice is a no-op.

**Steps**:
1. Create an application and call `seedTasksForApplication()`.
2. Call `seedTasksForApplication()` again for the same application.

**Expected**:
- Still exactly 8 `ApplicationTask` rows (not 16).
- No error or exception.

---

## Acceptance Scenario 7 — Two clients get independent task sets (US1 scenario 3)

**Goal**: Task sets are isolated per application.

**Steps**:
1. Create two applications for the same visa type.
2. Seed tasks for both.
3. Update a task name on application A's task set.

**Expected**:
- Application B's task set is unaffected.
- Each application has exactly 8 tasks.

---

## Acceptance Scenario 8 — Audit log entry created on seeding (Constitution XI)

**Goal**: Observability requirement from constitution.

**Steps**:
1. Create an application and seed tasks.

**Expected**:
- An audit log record exists with event `workflow_started` referencing the application's `reference_number`.

---

## File Manifest

| File | Action |
|------|--------|
| `app/Services/Tasks/WorkflowService.php` | Existing — verify; no changes expected |
| `app/Services/Client/OnboardingService.php` | Existing — verify trigger is correct |
| `tests/Feature/Tasks/ApplicationTaskGenerationTest.php` | NEW — Phase 2 acceptance tests |

---

## Key Invariants to Verify

1. `application_tasks` count matches `workflow_tasks` count for the visa type.
2. Position 1 task → `in_progress`; all others → `pending`.
3. `visa_applications.status` → `in_progress` when tasks are seeded.
4. No FK exists from `application_tasks` to `workflow_tasks` — they are independent rows.
5. Re-calling `seedTasksForApplication` does not create duplicates.
