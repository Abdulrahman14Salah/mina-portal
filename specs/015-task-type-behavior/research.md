# Research: Task Type Behavior (015)

## Decision 1 — Receipt storage reuses DocumentService, not a new system

**Decision**: Payment receipts are stored using the existing `DocumentService::upload()` and `DocumentService::delete()` infrastructure. A receipt is a `Document` record with `application_task_id` pointing to the payment task and `source_type = 'client'`.

**Rationale**: `DocumentService` already handles private disk storage, UUID filenames, MIME-type logging, audit entries, and signed URL serving. Building a parallel system for receipts would duplicate all of this and violate the Constitution (Principle VII). The existing `Document` model already has `application_task_id` — documents are already linked to tasks.

**Alternatives considered**: A dedicated `payment_receipts` table. Rejected — the Document system is already generic and task-linked. Adding a separate table for one file-per-task creates unnecessary complexity.

---

## Decision 2 — Receipt replacement: delete-then-upload

**Decision**: When a client uploads a replacement receipt for an in-progress payment task, the system finds and deletes the existing `Document` linked to that task (via `DocumentService::delete()`), then calls `DocumentService::upload()` for the new file.

**Rationale**: This keeps a single document per payment task at all times, preventing accumulation of stale receipts. Using `DocumentService::delete()` ensures the old file is removed from storage and an audit log entry is written.

**Alternatives considered**: Archiving (`archived_at`) instead of deleting. Rejected — replaced receipts are superseded and have no audit or legal value; hard deletion is simpler and avoids confusion in reviewer views.

---

## Decision 3 — Answer storage: new `task_questions` and `task_answers` tables

**Decision**: Two new tables are required:
- `task_questions`: linked to `workflow_tasks` (the blueprint); stores question prompt text, whether required, and position.
- `task_answers`: linked to `application_tasks` and `task_questions`; stores the client's answer text. Unique on `(application_task_id, task_question_id)`.

**Rationale**: Answers must be independent of the blueprint (same pattern as `application_tasks` vs `workflow_tasks`). Storing answers in a JSON column on `application_tasks` would make individual answer querying and validation harder. A proper relational model supports per-question required validation and future extension (e.g., multiple choice).

**Alternatives considered**: JSON column on `application_tasks`. Rejected — querying individual answers, enforcing per-question validation, and future extension to multiple-choice types all become harder with JSON storage. The relational approach aligns with Constitution Principle III.

---

## Decision 4 — Answer upsert: one row per (task, question)

**Decision**: `task_answers` has a unique constraint on `(application_task_id, task_question_id)`. Submitting answers uses an upsert (insert or update) on this key — allowing clients to update previously submitted answers as long as the task is still in progress.

**Rationale**: Confirmed in clarification: clients can resubmit answers while the task is in progress. An upsert approach (rather than delete-all-then-insert) is atomic and prevents partial answer sets appearing if the request fails mid-way.

**Alternatives considered**: Delete all existing answers then insert fresh. Rejected — non-atomic; a failed request mid-way leaves the task with no answers.

---

## Decision 5 — New service: TaskAnswerService

**Decision**: A new `App\Services\Tasks\TaskAnswerService` handles `submitAnswers()` and `uploadReceipt()` business logic. It is injected into the existing `Client\TaskController`.

**Rationale**: Constitution Principle II forbids business logic in controllers. Constitution Principle I requires tasks-module logic to be in its own service. `WorkflowService` already handles task status transitions and should not be expanded with answer/receipt logic (Single Responsibility).

**Alternatives considered**: Adding methods to `WorkflowService`. Rejected — `WorkflowService` already manages status transitions (advance, approve, reject, reopen). Adding answer and receipt logic makes it a bloated God class.

---

## Decision 6 — Reviewer completion reuses existing WorkflowService methods

**Decision**: Reviewer marking a question or payment task as "complete" reuses the existing `WorkflowService::approveTask()` method. No new reviewer service methods are needed for Phase 3.

**Rationale**: `approveTask()` already transitions the task to `approved`, sets `completed_at`, writes an audit entry, and activates the next task. This is exactly FR-004 and FR-009 behaviour.

---

## Decision 7 — Question input type: free text only in Phase 3

**Decision**: Phase 3 supports only free-text answers. The `task_questions` table has no `input_type` column — all questions accept text. Multiple-choice / boolean question types are deferred to a future phase.

**Rationale**: The spec does not require multiple input types. Adding `input_type` now without a UI to render it creates unused schema. The column can be added in a future phase without a destructive migration.

---

## Decision 8 — FR-005 payment instructions use task description

**Decision**: Payment instructions (amount, method, reference) are stored in `workflow_tasks.description`, which is already copied to `application_tasks.description` during seeding. No new column is needed.

**Rationale**: The description field already exists on both tables and is copied at generation time (Phase 2). Administrators set the payment instructions when authoring the workflow blueprint task. This is consistent with how info task content works too.

---

## Decision 9 — Info task completion: reviewer-controlled in Phase 3

**Decision**: Info tasks require a reviewer to call `approveTask()` to mark them complete, identical to question and payment tasks. Auto-completion on client view is deferred to Phase 6.

**Rationale**: Confirmed in spec Assumptions. Reviewer-controlled completion is simpler and consistent with other task types. Auto-completion introduces timing edge cases and requires additional state tracking.
