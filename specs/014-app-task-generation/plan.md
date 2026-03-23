# Implementation Plan: Application Task Generation

**Branch**: `014-app-task-generation` | **Date**: 2026-03-22 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/014-app-task-generation/spec.md`

## Summary

Phase 2 of the Task-Based Visa Workflow System. When a client submits a visa application, the system clones the predefined workflow blueprint into a personal set of `application_tasks`, sets the first task to `in_progress` and all others to `pending`, and ensures subsequent blueprint edits do not affect the generated task set.

**Key finding**: The core implementation is already complete in `WorkflowService::seedTasksForApplication()` (feature 003) and triggered via `OnboardingService::handle()`. Phase 2 work is primarily: verification, gap-filling of test coverage, and documenting the existing design against the spec acceptance scenarios.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 11
**Primary Dependencies**: `spatie/laravel-permission` v6+, `AuditLogService` (internal)
**Storage**: MySQL (MAMP local, port 8889) for dev; SQLite in-memory for tests
**Testing**: PHPUnit via `php artisan test`; `RefreshDatabase` trait
**Target Platform**: Laravel web application (server-rendered Blade)
**Project Type**: Web application — visa client portal
**Performance Goals**: Synchronous seeding completes within a single web request; no async required
**Constraints**: Must not break existing 184 passing tests; must not rewrite existing implementation
**Scale/Scope**: One task set per application; up to ~10 tasks per application

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Modular Architecture | ✅ Pass | `WorkflowService` in `App\Services\Tasks`; `OnboardingService` in `App\Services\Client` |
| II. Separation of Concerns | ✅ Pass | Business logic entirely in Service classes; Controllers delegate only |
| III. Database-Driven Workflows | ✅ Pass | Task definitions come from `workflow_tasks` table; nothing hardcoded |
| IV. API-Ready Design | ✅ Pass | All logic in Services; no direct Eloquent in controllers |
| V. Roles & Permissions | ✅ Pass | Phase 2 is seeding-only; no new authorization paths added |
| VIII. Dynamic Step-Based Workflow | ✅ Pass | Steps are DB records ordered by position |
| IX. Security by Default | ✅ Pass | No new routes; existing auth protects onboarding endpoint |
| X. Multi-Language Support | ✅ N/A | Phase 2 is a background seeding operation — no user-facing strings |
| XI. Observability & Activity Logging | ✅ Pass | `workflow_started` audit log entry emitted on successful seeding |
| XII. Testing Standards | ⚠️ Gap | Existing tests cover seeding indirectly; Phase 2 acceptance tests need to be added |

**Complexity violations**: None.

## Project Structure

### Documentation (this feature)

```text
specs/014-app-task-generation/
├── plan.md              ← This file
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output
├── quickstart.md        ← Phase 1 output
└── tasks.md             ← Phase 2 output (/speckit.tasks — NOT created here)
```

### Source Code (relevant files)

```text
app/
├── Services/
│   ├── Tasks/
│   │   └── WorkflowService.php          ← VERIFY: seedTasksForApplication() is correct
│   └── Client/
│       └── OnboardingService.php        ← VERIFY: trigger call at line 61
├── Models/
│   └── ApplicationTask.php              ← VERIFY: fillable includes name, type, status, position
└── Console/Commands/
    └── SeedApplicationWorkflowTasks.php ← Existing admin backfill command (no changes)

tests/
└── Feature/
    └── Tasks/
        ├── WorkflowSectionSeedingTest.php      ← Existing coverage (section-based + legacy)
        ├── WorkflowIntegrityTest.php           ← Existing coverage (idempotency, edge cases)
        └── ApplicationTaskGenerationTest.php   ← NEW: Phase 2 acceptance tests
```

**Structure Decision**: Single Laravel application. No new directories required. One new test file added under `tests/Feature/Tasks/`.

## Phase 0: Research Summary

See [research.md](research.md) for full decisions. Key findings:

1. **Implementation exists** — `WorkflowService::seedTasksForApplication()` satisfies all FRs.
2. **Trigger is correct** — `OnboardingService::handle()` calls seeding synchronously after main transaction.
3. **Status vocabulary** — `approved` (not `completed`) is the terminal status; no rename needed.
4. **Legacy fallback retained** — `WorkflowStepTemplate` path stays in place for older visa types.
5. **Test gaps identified** — New `ApplicationTaskGenerationTest` covers Phase 2 acceptance scenarios.

## Phase 1: Design

### What already exists (no changes needed)

| Requirement | Satisfied by |
|-------------|-------------|
| FR-001: Auto-generate on application creation | `OnboardingService::handle()` line 61 |
| FR-002: Copy name, type, position | `seedTasksForApplication()` lines 42–49 |
| FR-003: First task `in_progress`, rest `pending` | `seedTasksForApplication()` lines 76–78 |
| FR-004: Independence from blueprint | No FK from `application_tasks` to `workflow_tasks` |
| FR-005: Section-based blueprint path | `seedTasksForApplication()` `$hasSections` branch |
| FR-006: Graceful zero-task fallback | `seedTasksForApplication()` early-return if no templates |
| FR-007: Atomic generation | `DB::transaction()` wrapping all inserts |
| FR-008: No duplicate task sets | Early-return guard `if ($application->tasks()->exists())` |

### What Phase 2 adds

**One new test file**: `tests/Feature/Tasks/ApplicationTaskGenerationTest.php`

Tests to include (mapped to acceptance scenarios in quickstart.md):

| Test method | Scenario |
|-------------|----------|
| `test_tasks_generated_via_onboarding_service` | Scenario 1 — end-to-end through OnboardingService |
| `test_first_task_is_in_progress_rest_are_pending` | Scenario 2 — initial status assignment |
| `test_single_task_blueprint_gets_in_progress` | Scenario 3 — single-task edge case |
| `test_blueprint_changes_do_not_affect_existing_tasks` | Scenario 4 — independence |
| `test_no_tasks_for_visa_type_without_blueprint` | Scenario 5 — graceful no-op |
| `test_seeding_is_idempotent` | Scenario 6 — no duplicates |
| `test_two_applications_have_independent_task_sets` | Scenario 7 — isolation |
| `test_audit_log_created_on_seeding` | Scenario 8 — observability |

### No new migrations

Phase 2 requires no database schema changes. All required columns exist in `application_tasks`.

### No new routes or controllers

Phase 2 is purely a service/data layer concern. The task generation is an internal seeding operation triggered by the existing onboarding flow.

## Complexity Tracking

No constitution violations. No complexity tracking entries required.
