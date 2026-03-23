# Implementation Plan: Task Page UI

**Branch**: `016-task-page-ui` | **Date**: 2026-03-23 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/016-task-page-ui/spec.md`

## Summary

Implement the client-facing task page for all three task types (`question`, `payment`, `info`). The route and controller already exist from Phase 3; this phase adds the `workflow_task_id` FK migration so question pages can load their questions, extends `TaskAnswerService::submitAnswers` to auto-reopen rejected tasks, updates `TaskController::show()` with correct eager-loading and a pending-task redirect, and rewrites `show.blade.php` with type-specific Blade partials for each task type and status combination.

---

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 11
**Primary Dependencies**: Laravel Blade (SSR), Alpine.js v3, `spatie/laravel-permission` v6+
**Storage**: Private local disk (dev) / S3 (prod) via `FILESYSTEM_DISK` — existing document system reused for receipts
**Testing**: PHPUnit / Laravel Feature Tests; SQLite in-memory
**Target Platform**: Web — MAMP local (MySQL 8, port 8889); production Linux
**Performance Goals**: Standard synchronous page load; no background processing
**Constraints**: Must not break existing 194 tests; reuse existing `DocumentService`, `TaskAnswerService`, and document download route
**Scale/Scope**: Per-application task page; single authenticated client at a time

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Modular Architecture | ✅ PASS | All changes confined to Client module (controller, views) and Tasks module (service). No cross-module internal access. |
| II. Separation of Concerns | ✅ PASS | Auto-reopen logic in `TaskAnswerService` (service layer). Controller only eager-loads and passes keyed collection. Views contain zero business logic. |
| III. Database-Driven Workflows | ✅ PASS | Questions loaded from `task_questions` table via `workflowTask.questions`. No question text hardcoded in views. |
| IV. API-Ready Design | ✅ PASS | Controller passes structured data (`$answers` keyed collection). View consumes it passively. |
| V. Roles & Permissions | ✅ PASS | Existing `VisaApplicationPolicy::view` enforces client-owns-application. Pending redirect added for locked tasks. |
| VII. Secure Document Handling | ✅ PASS | Receipt displayed via existing `documents.download` route (enforced by `DocumentPolicy`). No direct file URLs. |
| IX. Security by Default | ✅ PASS | Routes protected by `auth` + `active`. Form Requests already exist. `$fillable` used on all models. |
| X. Multi-Language Support | ⚠️ WATCH | All new view strings must use `__('tasks.key')`. 9 new keys added to `lang/en/tasks.php`. No hardcoded English in Blade. |
| XI. Observability | ✅ PASS | `task_answers_submitted` audit log already fires. Auto-reopen is a status update — covered by existing `task_answers_submitted` event context. |
| XII. Testing Standards | ✅ PASS | Feature test `TaskPageTest` required covering all type/status combinations and the pending redirect. |

**Post-design re-check**: All gates pass. No complexity violations.

---

## Project Structure

### Documentation (this feature)

```text
specs/016-task-page-ui/
├── plan.md              ← this file
├── research.md          ← Phase 0 decisions
├── data-model.md        ← Phase 1 schema + model changes
├── contracts/           ← N/A (internal Blade, no external interface)
└── tasks.md             ← Phase 2 output (/speckit.tasks command)
```

### Source Code

```text
database/migrations/
└── 2026_03_23_200001_add_workflow_task_id_to_application_tasks.php  [NEW]

app/Models/
└── ApplicationTask.php                   [MODIFIED — workflowTask() relationship]

app/Services/Tasks/
├── TaskAnswerService.php                 [MODIFIED — accept rejected, auto-reopen]
└── WorkflowService.php                   [MODIFIED — populate workflow_task_id at seed]

app/Http/Controllers/Client/
└── TaskController.php                    [MODIFIED — pending redirect, eager-load]

resources/views/client/tasks/
├── show.blade.php                        [REWRITTEN]
└── partials/
    ├── _question-form.blade.php          [NEW]
    ├── _answers-readonly.blade.php       [NEW]
    ├── _no-questions.blade.php           [NEW]
    ├── _payment-form.blade.php           [NEW]
    ├── _receipt-readonly.blade.php       [NEW]
    └── _info-content.blade.php           [NEW]

resources/lang/en/tasks.php               [MODIFIED — 9 new keys]

tests/Feature/Tasks/
└── TaskPageTest.php                      [NEW]
```

---

## Complexity Tracking

No constitution violations. Table not required.

---

## Phase 0: Research

All decisions resolved without external research agents. See [research.md](research.md).

| Unknown | Resolution |
|---------|------------|
| Auto-reopen pattern for rejected question tasks | Extend `submitAnswers` guard + update status inside transaction |
| `workflow_task_id` FK approach | Nullable FK, SET NULL on delete, populated at seed time |
| View structure | `@include` partials per type/status combination |
| Answer pre-population | `$task->answers->keyBy('task_question_id')` passed from controller |
| Receipt download link | Reuse existing `route('documents.download', $doc)` |
| Pending task redirect | Redirect to `client.dashboard` with no error message |

---

## Phase 1: Design

### Migration Design

**`2026_03_23_200001_add_workflow_task_id_to_application_tasks`**

```
up:   ALTER application_tasks ADD workflow_task_id BIGINT UNSIGNED NULL
      ADD CONSTRAINT FK workflow_task_id → workflow_tasks.id ON DELETE SET NULL
      ADD INDEX on workflow_task_id

down: DROP FK, DROP INDEX, DROP COLUMN
```

### `WorkflowService::seedTasksForApplication` Change

In the section-based seeding path, pass `'workflow_task_id' => $workflowTask->id` alongside the existing fields. The legacy flat-template path continues to set it to `null`.

### `TaskAnswerService::submitAnswers` Change

```
Guard:   throw if status NOT IN ('in_progress', 'rejected')
After DB::transaction (answers stored):
  if task->status === 'rejected':
    task->update(['status' => 'in_progress', 'rejection_reason' => null])
Audit:   unchanged (task_answers_submitted)
```

### `TaskController::show()` Change

```
1. If task->status === 'pending': redirect to client.dashboard (before authorize)
2. $this->authorize('view', $application)
3. abort_if(task->application_id !== application->id, 404)
4. task->loadMissing(['documents', 'answers', 'workflowTask.questions'])
5. $answers = task->answers->keyBy('task_question_id')
6. return view('client.tasks.show', compact('application', 'task', 'answers'))
```

### View Logic Map

```
show.blade.php
├── Flash messages
├── Task header (title, step #, description, status badge)
├── Reviewer note (if present)
├── Rejection reason banner (if status === rejected)
├── @if type === 'question'
│   ├── @if status in [in_progress, rejected]
│   │   ├── @if workflowTask->questions->isEmpty() → @include _no-questions
│   │   └── @else → @include _question-form
│   └── @elseif status === 'approved' → @include _answers-readonly
├── @elseif type === 'payment'
│   ├── @if status in [in_progress, rejected] → @include _payment-form
│   └── @elseif status === 'approved' → @include _receipt-readonly
├── @elseif type === 'info' → @include _info-content
└── Back link to dashboard
```

### Partial Contracts

**`_question-form.blade.php`**
- Receives: `$task`, `$answers` (keyed collection)
- Renders: `<form POST route('client.tasks.answers.submit', [$application, $task])>`
- For each `$task->workflowTask->questions as $question`: label + `<textarea name="answers[{$question->id}]">{{ $answers[$question->id]->answer ?? '' }}</textarea>`
- Validation errors via `$errors->get("answers.{$question->id}")`
- Submit button: `__('tasks.submit_answers')`

**`_answers-readonly.blade.php`**
- Receives: `$task`, `$answers`
- Renders: list of question prompts + submitted answer text (no form elements)

**`_no-questions.blade.php`**
- Renders: `__('tasks.no_questions_defined')` message only

**`_payment-form.blade.php`**
- Receives: `$task`, `$application`
- Renders: current receipt (if `$task->documents->first()`) as download link labeled `__('tasks.current_receipt')`
- Upload form: `<form POST route('client.tasks.receipt.upload', [$application, $task])>` with `<input type="file" name="receipt">`
- Submit button: `__('tasks.replace_receipt')` if receipt exists, else `__('tasks.upload_receipt')`

**`_receipt-readonly.blade.php`**
- Receives: `$task`
- Renders: receipt filename as download link, no upload control

**`_info-content.blade.php`**
- Renders: `__('tasks.info_task_note')` message only (description already shown in header)

### New Language Keys

9 keys added to `resources/lang/en/tasks.php`:

```php
'your_answers'          => 'Your Answers',
'no_questions_defined'  => 'No questions are required for this task. Awaiting reviewer action.',
'submit_answers'        => 'Submit Answers',
'payment_receipt'       => 'Payment Receipt',
'current_receipt'       => 'Current Receipt',
'replace_receipt'       => 'Replace Receipt',
'upload_receipt'        => 'Upload Receipt',
'info_task_note'        => 'This task contains information only. No action is required from you.',
'answers_readonly_title'=> 'Submitted Answers',
```

### Test Plan (`TaskPageTest`)

| Test | Type | Covers |
|------|------|--------|
| `test_pending_task_redirects_to_dashboard` | HTTP | FR-013 |
| `test_question_task_shows_questions_form` | HTTP | FR-005 |
| `test_question_task_prepopulates_answers` | HTTP | FR-006 |
| `test_question_task_approved_shows_readonly` | HTTP | FR-007 |
| `test_question_task_no_questions_shows_message` | HTTP | FR-008 |
| `test_rejected_question_task_auto_reopens_on_submit` | Service | FR-005a |
| `test_payment_task_shows_upload_form` | HTTP | FR-009 |
| `test_payment_task_shows_existing_receipt_link` | HTTP | FR-010 |
| `test_payment_task_approved_shows_readonly` | HTTP | FR-011 |
| `test_info_task_shows_no_form` | HTTP | FR-012 |
| `test_client_cannot_view_other_clients_task` | HTTP | FR-013 (security) |
