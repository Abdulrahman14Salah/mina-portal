# Research: Task Page UI (016)

## Decision 1 — Auto-reopen Pattern for Rejected Question Tasks

**Decision**: Extend `TaskAnswerService::submitAnswers` to accept `rejected` status and auto-transition to `in_progress` after storing answers, clearing `rejection_reason`.

**Rationale**: Mirrors the existing auto-reopen in `DocumentService::upload` (which reopens a rejected task when a client re-uploads a document). Making question tasks symmetric with payment tasks keeps the service layer consistent and avoids a separate "reopen" HTTP round-trip before the client can submit.

**Alternatives considered**:
- Reviewer-must-reopen-first: Creates unnecessary friction for clients and an asymmetry between task types. Rejected.
- Auto-reopen in the controller: Violates Principle II (business logic in controller). Rejected.

---

## Decision 2 — Adding `workflow_task_id` FK to `application_tasks`

**Decision**: Add a nullable `workflow_task_id` FK to `application_tasks` with `SET NULL` on delete. Populate it in `WorkflowService::seedTasksForApplication` for the section-based path. Add a `workflowTask()` BelongsTo relationship on `ApplicationTask`.

**Rationale**: The task page needs the `WorkflowTask`'s `questions()` to render the question form. Without this FK there is no safe join path from an `ApplicationTask` to its defining `WorkflowTask`. Nullable + SET NULL protects existing data if a blueprint task is later deleted. Legacy applications seeded via the flat template path will have `workflow_task_id = null` and will not be affected (they use `type = 'upload'` which is out of Phase 4 scope).

**Alternatives considered**:
- Resolve via `WorkflowSection` + `name` match: Fragile if task names are renamed. Rejected.
- Store questions snapshot on `ApplicationTask` at seed time: Duplicates data, violates Principle III (database-driven). Rejected.

---

## Decision 3 — View Partials vs. Single Monolithic Blade File

**Decision**: Split the type-specific UI into `@include` partials under `resources/views/client/tasks/partials/`:
- `_question-form.blade.php` — question form + read-only answers
- `_payment-form.blade.php` — receipt upload + current receipt link
- `_info-content.blade.php` — text-only display

The main `show.blade.php` handles the shared wrapper (header, status badge, reviewer note, rejection reason, back link) and `@include`s the appropriate partial.

**Rationale**: Blade templates are not testable in isolation, so splitting for testability adds no value. Partials are purely a readability and maintainability choice. The three types have completely different markup; a single file with three `@if ($task->type === ...)` blocks becomes difficult to read past ~100 lines.

**Alternatives considered**:
- Single file with `@if` blocks: Acceptable for small views; rejected here because each type block will be 30–60 lines including form, validation errors, and read-only states.
- Blade components (`<x-task-question-form>`): Correct long-term pattern but overkill for three one-use templates. Deferred to a future UI polish phase.

---

## Decision 4 — Answer Pre-population Strategy

**Decision**: In `TaskController::show()`, pass answers as a collection keyed by `task_question_id`:
```php
$answers = $task->answers->keyBy('task_question_id');
```
The partial accesses `$answers[$question->id]->answer ?? ''` for each question.

**Rationale**: Avoids an N+1 query per question (answers already eager-loaded in one query) and gives O(1) lookup per question in the template without additional PHP in the view.

**Alternatives considered**:
- `$task->answers->where('task_question_id', $q->id)->first()` in Blade: Works but O(n) per question and puts collection logic in a view. Rejected (Principle II).
- Passing `$answers` as an associative array from the controller: Equivalent outcome; `keyBy()` is idiomatic Laravel and more readable.

---

## Decision 5 — Receipt Display Link

**Decision**: Use the existing `route('documents.download', $doc)` route to render a clickable download link for the current receipt on the payment task page.

**Rationale**: The `DocumentPolicy` already enforces that only the document owner and authorized reviewers can download. No new authorization logic is needed. The route is already trusted and production-proven.

**Alternatives considered**:
- Signed temporary URL generated in the controller: More secure for S3, but adds controller complexity and the download route already handles both local and S3 transparently. Deferred to a future security hardening pass if needed.

---

## Decision 6 — Pending Task Redirect Location

**Decision**: Redirect to `route('client.dashboard')` from `TaskController::show()` when `$task->status === 'pending'`.

**Rationale**: The dashboard shows the full workflow with locked/unlocked task states, giving the client context on why the task is not yet accessible. A generic 403 would be confusing.

**Alternatives considered**:
- `abort(403)`: Technically correct but poor UX — the client is not unauthorized, they just need to complete earlier tasks first. Rejected.
