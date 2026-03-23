# Quickstart & Acceptance Scenarios: Task Type Behavior (015)

## Prerequisites

- Seeders run: `RolePermissionSeeder`, `VisaTypeSeeder`, `WorkflowBlueprintSeeder`
- A client user and a reviewer user exist with correct roles
- An application exists with seeded tasks (Phase 2)
- A question-type workflow task has at least one `TaskQuestion` defined

---

## Acceptance Scenario 1 — Client submits answers to a question task (US1)

**Steps**:
1. Create a `WorkflowTask` with `type = 'question'` and two associated `TaskQuestion` records.
2. Create an application and seed its tasks (application task is in `in_progress`).
3. POST to `/client/tasks/{task}/answers` with answer values for both questions.

**Expected**:
- Two `TaskAnswer` rows exist, each linked to the correct `application_task_id` and `task_question_id`.
- `ApplicationTask.status` remains `in_progress` (not changed by submission).
- An audit log entry `task_answers_submitted` is recorded.

---

## Acceptance Scenario 2 — Reviewer sees submitted answers (US1)

**Steps**:
1. Complete Scenario 1.
2. Reviewer views the application task.

**Expected**:
- The reviewer's view includes the answer text for each question.
- Answers are associated with the correct task and application.

---

## Acceptance Scenario 3 — Reviewer marks question task complete (US1)

**Steps**:
1. Complete Scenario 1.
2. Reviewer calls `WorkflowService::approveTask($task, $note)`.

**Expected**:
- `ApplicationTask.status` transitions to `approved`.
- `ApplicationTask.completed_at` is set.
- Next task (if any) transitions to `in_progress`.
- Audit log entry `task_approved` recorded.

---

## Acceptance Scenario 4 — Client revisits question task and sees previous answers (US1)

**Steps**:
1. Complete Scenario 1.
2. Client requests the task page again (task still `in_progress`).

**Expected**:
- Previously submitted answers are returned/displayed.
- No duplicate `TaskAnswer` rows created.

---

## Acceptance Scenario 5 — Client uploads payment receipt (US2)

**Steps**:
1. Create an application with a `payment`-type task in `in_progress` status.
2. POST to `/client/tasks/{task}/receipt` with a valid image/PDF file.

**Expected**:
- One `Document` row exists, linked to the payment task (`application_task_id`) and the application.
- File is stored on private disk; not publicly accessible by URL.
- Audit log entry `document_uploaded` recorded.
- `ApplicationTask.status` remains `in_progress`.

---

## Acceptance Scenario 6 — Client replaces an existing receipt (US2)

**Steps**:
1. Complete Scenario 5.
2. POST to `/client/tasks/{task}/receipt` again with a different valid file.

**Expected**:
- Only one `Document` row now linked to the payment task (old one deleted).
- New file is stored; old file is removed from disk.
- Audit log entries for both `document_deleted` and `document_uploaded`.

---

## Acceptance Scenario 7 — Reviewer marks payment task complete (US2)

**Steps**:
1. Complete Scenario 5.
2. Reviewer calls `WorkflowService::approveTask($task, $note)`.

**Expected**:
- `ApplicationTask.status` → `approved`.
- Next task activates.

---

## Acceptance Scenario 8 — Invalid file rejected before storage (FR-011)

**Steps**:
1. POST to `/client/tasks/{task}/receipt` with an invalid file (e.g., `.exe` or oversized file).

**Expected**:
- 422 validation error returned.
- No `Document` row created.
- No file stored on disk.

---

## Acceptance Scenario 9 — Info task shows content, no input form (US3)

**Steps**:
1. Create an application with an `info`-type task in `in_progress`.
2. Client requests the task.

**Expected**:
- Task `name` and `description` are accessible.
- No answers endpoint or receipt upload endpoint applicable.
- Task remains `in_progress` until reviewer calls `approveTask()`.

---

## Acceptance Scenario 10 — Cannot submit to a completed task (FR-012)

**Steps**:
1. Complete Scenario 3 (reviewer marks question task `approved`).
2. Client attempts to POST to `/client/tasks/{task}/answers` again.

**Expected**:
- Request rejected with a 403 or validation error.
- No new or modified `TaskAnswer` rows.

---

## File Manifest

| File | Action |
|------|--------|
| `database/migrations/..._create_task_questions_table.php` | NEW |
| `database/migrations/..._create_task_answers_table.php` | NEW |
| `app/Models/TaskQuestion.php` | NEW |
| `app/Models/TaskAnswer.php` | NEW |
| `app/Models/WorkflowTask.php` | MODIFY — add `questions()` HasMany relationship |
| `app/Models/ApplicationTask.php` | MODIFY — add `answers()` HasMany relationship |
| `app/Services/Tasks/TaskAnswerService.php` | NEW — `submitAnswers()`, `uploadReceipt()` |
| `app/Http/Requests/Client/SubmitTaskAnswersRequest.php` | NEW |
| `app/Http/Requests/Client/UploadReceiptRequest.php` | NEW |
| `app/Http/Controllers/Client/TaskController.php` | MODIFY — add `submitAnswers()`, `uploadReceipt()` actions |
| `routes/web.php` | MODIFY — add 2 new client POST routes |
| `tests/Feature/Tasks/TaskTypeBehaviorTest.php` | NEW |
