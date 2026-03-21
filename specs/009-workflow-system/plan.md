# Implementation Plan: Workflow System (Core System)

**Branch**: `009-workflow-system` | **Date**: 2026-03-21 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/009-workflow-system/spec.md`

## Summary

Phase 2 is the core workflow engine of the portal. The vast majority of the system is already implemented — `WorkflowService`, `ApplicationTask`, `WorkflowStepTemplate`, reviewer advance/reject controllers, client task dashboard, and 122 passing tests. This plan addresses the four remaining gaps identified against the spec: (1) re-open of rejected task steps, (2) removal of automatic application status transitions that contradict the spec clarification, (3) task status summary on the admin applications list, and (4) atomic step transitions to prevent concurrent corruption.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 11
**Primary Dependencies**: spatie/laravel-permission v6+, Laravel Blade, AuditLogService
**Storage**: MySQL (MAMP local, port 8889); SQLite in-memory (tests)
**Testing**: PHPUnit via `php artisan test`
**Target Platform**: Web application (Blade SSR)
**Project Type**: Web application — no external integrations
**Performance Goals**: Standard web request; no special targets
**Constraints**: All validation via Form Request classes; no business logic in controllers or Blade; all auth via Policies; all strings via `__()` localization
**Scale/Scope**: Single-tenant portal; workflow volume not a constraint for this phase

## Constitution Check

| Principle | Status | Notes |
|---|---|---|
| I. Modular Architecture | ✅ Pass | Tasks module (`Tasks/`) already established; re-open fits inside WorkflowService |
| II. Separation of Concerns | ✅ Pass | New `reopenTask()` goes in WorkflowService; controller stays thin |
| III. Database-Driven Workflows | ✅ Pass | WorkflowStepTemplate drives all task/step structure — no hardcoding |
| IV. API-Ready Design | ✅ Pass | All logic in service layer; controllers return structured data |
| V. Roles & Permissions | ✅ Pass | New `reopen` policy check needed in ApplicationTaskPolicy |
| VI. Payment Integrity | N/A | Out of scope |
| VII. Secure Document Handling | N/A | Out of scope |
| VIII. Dynamic Workflow Engine | ✅ Pass | Step-based progression already implemented; re-open extends it |
| IX. Security by Default | ⚠️ Gap | Re-open action needs a `ReopenTaskRequest` Form Request (not inline validate) |
| X. Multi-Language Support | ✅ Pass | New UI strings must use `__()` with lang keys |
| XI. Observability | ✅ Pass | `reopen` event must be added to AuditLogService |
| XII. Testing Standards | ✅ Pass | New feature tests required for all 4 gaps |

**No constitution violations. One gap (IX) resolved by creating `ReopenTaskRequest`.**

## Spec vs Implementation: Gap Analysis

### ✅ Already Fully Implemented

| Requirement | Implemented In |
|---|---|
| FR-001/002: Auto-seed tasks in order on application creation | `WorkflowService::seedTasksForApplication()` |
| FR-003: Strict sequential step order enforcement | `advanceTask()` validates `in_progress` only |
| FR-004: Reviewer advances active step | `ReviewerApplicationController::advance()` |
| FR-005: Final step advance marks task complete | `WorkflowService::advanceTask()` last-task logic |
| FR-006: Reviewer rejects with mandatory reason | `ReviewerApplicationController::reject()` + `RejectTaskRequest` |
| FR-007: Rejection stored with reason | `WorkflowService::rejectTask()` |
| FR-008: Non-active steps refused | `advanceTask()` / `rejectTask()` status guard |
| FR-009: Progress persisted per step | `application_tasks` table with status + timestamps |
| FR-010: Client dashboard shows task state | `client/dashboard/tabs/tasks.blade.php` |
| FR-011: Rejection reason visible to client | Task view shows `reviewer_note` |
| FR-012: Reviewer sees all tasks with actions | `reviewer/applications/show.blade.php` |
| FR-015: Task definitions configurable per visa type | `workflow_step_templates` table |
| FR-016: Re-seeding is idempotent | `seedTasksForApplication()` early-return guard |
| FR-017: Step transitions logged | AuditLogService `task_completed`, `task_rejected` |

### ❌ Remaining Gaps (4 items)

**Gap 1 — Re-open Rejected Task Step (FR-007a)**
The spec clarification requires reviewers to re-open rejected steps (resetting only the rejected step to active, preserving completed steps, returning task to `in_progress`). No `reopenTask()` method exists. The reviewer UI has no re-open button.

**Gap 2 — Auto Application Status Transition Contradicts Spec (FR-009a-related)**
`WorkflowService::advanceTask()` automatically sets `application.status → 'approved'` when the last task completes. `rejectTask()` automatically sets `application.status → 'rejected'`. The spec clarification (Q4) explicitly states application status must be changed manually by admin only — no automatic trigger. These auto-transitions must be removed.

*Note: Removing the auto-reject also makes `reopenTask()` simpler — the application status stays `in_progress` throughout the task lifecycle.*

**Gap 3 — Admin Application List Missing Task Summary (FR-013)**
The admin applications index view lists applications but does not show a task status summary (e.g., "3/5 tasks complete"). The `AdminApplicationController::index()` does not eager-load task counts.

**Gap 4 — Step Transitions Not Atomic (FR-009a)**
`advanceTask()` and `rejectTask()` check status then update — these two operations are not wrapped in a database transaction with a pessimistic lock. A concurrent request on the same task could slip through the status guard before the first request commits.

## Project Structure

### Documentation (this feature)

```text
specs/009-workflow-system/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
└── tasks.md             # Phase 2 output (/speckit.tasks)
```

### Source Code (files touched by this feature)

```text
app/Services/Tasks/
└── WorkflowService.php                         # Remove auto-transitions; add reopenTask()

app/Http/Controllers/Reviewer/
└── ApplicationController.php                   # Add reopen() action

app/Http/Requests/Reviewer/
└── ReopenTaskRequest.php                       # New: validate reopen action (no extra fields)

app/Policies/
└── ApplicationTaskPolicy.php                   # Add reopen() policy method

app/Http/Controllers/Admin/
└── ApplicationController.php                   # Eager-load task counts for summary

resources/views/
├── reviewer/applications/show.blade.php        # Add re-open button (shown when task is rejected)
└── admin/applications/index.blade.php          # Add task summary column

lang/
├── en/tasks.php                                # Add re-open + admin summary lang keys
└── ar/tasks.php                                # Arabic translations

routes/web.php                                  # Add reopen route

tests/Feature/Reviewer/
├── WorkflowTest.php                            # Extend: re-open, auto-transition removal
└── ReopenTaskTest.php                          # New: re-open specific tests

tests/Feature/Admin/
└── ApplicationTaskSummaryTest.php              # New: admin task summary
```

**Structure Decision**: Single Laravel project. All changes are incremental modifications within the existing Tasks and Reviewer modules. No new directories required.
