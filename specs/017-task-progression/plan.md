# Implementation Plan: Task Progression

**Branch**: `017-task-progression` | **Date**: 2026-03-23 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/017-task-progression/spec.md`

## Summary

Completes the sequential task progression mechanic: when a reviewer approves a task, the next task automatically activates; when the final task is approved, the application transitions to `workflow_complete` status. Also fixes a pre-existing bug where the dashboard task list incorrectly references the old `completed` status (renamed to `approved` in migration `2026_03_22_200001`), and adds HTTP-level blocking of pending-task submissions.

The core next-task activation logic already exists in `WorkflowService::approveTask` and `advanceTask` — this feature adds only the end-of-workflow completion hook, the locked-task submission guard, and the dashboard rendering fixes.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 11
**Primary Dependencies**: `spatie/laravel-permission` v6+, `AuditLogService` (internal)
**Storage**: MySQL (MAMP local, port 8889) for dev; SQLite in-memory for tests — no schema changes required
**Testing**: PHPUnit (Laravel Feature Tests), SQLite in-memory
**Target Platform**: Web server (Blade SSR)
**Project Type**: Web application
**Performance Goals**: Progression fires within the same DB transaction as approval — no async required
**Constraints**: No new tables or migrations; reuse existing `application_tasks` and `visa_applications` tables
**Scale/Scope**: Single-tenant; low task count per application (< 50)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|---|---|---|
| I. Modular Architecture | ✅ PASS | Progression logic stays in `WorkflowService` (Tasks module); no cross-module internal access |
| II. Separation of Concerns | ✅ PASS | All business logic in `WorkflowService`; controller only adds an HTTP-level guard redirect |
| III. Database-Driven Workflows | ✅ PASS | Task ordering from `position` fields in DB; no hardcoded sequences |
| IV. API-Ready Design | ✅ PASS | Progression triggered through service layer, not directly from controller or view |
| V. Roles & Permissions | ✅ PASS | Existing `approve` policy unchanged; client pending-task blocking is a controller guard |
| VI. Payment Integrity | N/A | No payment changes |
| VII. Secure Document Handling | N/A | No new document handling |
| VIII. Dynamic Step-Based Workflow Engine | ✅ PASS | This feature implements the core step-progression; ordering is DB-driven |
| IX. Security by Default | ✅ PASS | Locked tasks blocked at controller level; no pending data can be written |
| X. Multi-Language Support | ✅ PASS | New strings (`task_locked`, `workflow_complete_*`) use `__()` |
| XI. Observability | ✅ PASS | New `workflow_tasks_complete` audit log event on final approval |
| XII. Testing Standards | ✅ PASS | 10 feature tests covering progression, blocking, and dashboard state |

**No violations. No Complexity Tracking entries required.**

## Project Structure

### Documentation (this feature)

```text
specs/017-task-progression/
├── plan.md              ← this file
├── spec.md
├── research.md
├── data-model.md
├── quickstart.md
└── tasks.md             ← /speckit.tasks output
```

### Source Code (files modified or created)

```text
app/
└── Services/
    └── Tasks/
        └── WorkflowService.php           ← add workflow_complete hook to approveTask + advanceTask

app/
└── Http/
    └── Controllers/
        └── Client/
            └── TaskController.php        ← add pending-task guard to submitAnswers + uploadReceipt

resources/
└── views/
    └── client/
        └── dashboard/
            └── tabs/
                └── tasks.blade.php       ← fix 'completed' → 'approved'; add task links; add workflow_complete banner

resources/
└── lang/
    └── en/
        └── tasks.php                     ← add task_locked, workflow_complete_title, workflow_complete_message

tests/
└── Feature/
    └── Tasks/
        └── TaskProgressionTest.php       ← NEW: 10 tests
```

**Structure Decision**: Laravel monolith — single project, standard app/ layout. No new directories.

## Phase Design

### Phase 1 (Foundational) — Service Layer Completion

**Goal**: Add the `workflow_complete` application transition to `WorkflowService`.

Files:
- `app/Services/Tasks/WorkflowService.php` — in `approveTask` and `advanceTask`, when `$nextTask === null`: call `$task->application->update(['status' => 'workflow_complete'])` and log `workflow_tasks_complete`

This is the single most important change. All other changes are client-facing consequences of this.

### Phase 2 (Client Safety) — Locked Task Submission Blocking

**Goal**: Prevent HTTP submissions to pending tasks from reaching the service layer.

Files:
- `app/Http/Controllers/Client/TaskController.php` — add to `submitAnswers` and `uploadReceipt`:
  ```php
  if ($task->status === 'pending') {
      return redirect()->back()->with('error', __('tasks.task_locked'));
  }
  ```

### Phase 3 (UI) — Dashboard Fixes and Task Links

**Goal**: Fix the `completed` → `approved` bug and add progression state to the dashboard.

Files:
- `resources/lang/en/tasks.php` — add `task_locked`, `workflow_complete_title`, `workflow_complete_message`
- `resources/views/client/dashboard/tabs/tasks.blade.php` — fix status references; add task page links for `in_progress` and `approved` tasks; add pending opacity; add `workflow_complete` banner

### Phase 4 (Tests) — Feature Test Suite

**Goal**: Cover all progression scenarios and regression cases.

File: `tests/Feature/Tasks/TaskProgressionTest.php`

10 tests (see data-model.md for full list).

## Implementation Notes

### Key Insight: Already Partially Done

`WorkflowService::approveTask` (lines 117–146) and `advanceTask` (lines 89–115) already implement next-task activation correctly, including `lockForUpdate()` for concurrency protection. The only missing code is the `if ($nextTask === null)` branch that sets `workflow_complete`. This means Phase 1 is a very small change.

### Pre-existing Bug (fix as part of this feature)

`resources/views/client/dashboard/tabs/tasks.blade.php` was written before the `completed → approved` rename migration. Five locations reference `'completed'` that should be `'approved'`. This causes:
- Progress counter always showing 0/N for approved tasks
- Approved tasks rendering with no badge color

This is fixed in Phase 3 as a required correctness fix, not a scope expansion.

### `advanceTask` vs `approveTask`

Both methods do the same thing (approve a task and activate the next). `advanceTask` is the legacy method name; `approveTask` was added later. Both are called from the reviewer controller. Both must receive the `workflow_complete` fix.

### Application Status After `workflow_complete`

The `DocumentService::upload` method sets `awaiting_documents` only when `$application->status === 'in_progress'` (line 51). Once an application is `workflow_complete`, uploading a document will NOT change the status back to `awaiting_documents` — this is the correct behavior.
