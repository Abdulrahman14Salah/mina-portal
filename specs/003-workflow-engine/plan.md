# Implementation Plan: Phase 3 — Workflow Engine

**Branch**: `003-workflow-engine` | **Date**: 2026-03-19 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/003-workflow-engine/spec.md`

## Summary

Implement the Workflow Engine for the Visa Application Portal: a database-driven system that seeds an ordered list of `ApplicationTask` records from `WorkflowStepTemplate` blueprints when an application is submitted, allows reviewers to advance tasks through `pending → in_progress → completed` (or `rejected`), automatically synchronises `visa_applications.status` with the active task state, and surfaces progress to clients on their Tasks dashboard tab. Built on Laravel 11 / Blade SSR, with `WorkflowService` owning all business logic, two new Reviewer controllers (dashboard + application detail), an artisan backfill command, and full audit logging on every task state transition.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 11
**Primary Dependencies**: Laravel Breeze (Blade), Alpine.js v3, `spatie/laravel-permission` v6+
**Storage**: MySQL (MAMP local dev); SQLite in-memory (tests)
**Testing**: PHPUnit via Laravel feature tests
**Target Platform**: Web server — MAMP (local), Linux (production)
**Performance Goals**: Reviewer advances all 6 steps in < 2 min (SC-001); client Tasks tab loads < 3 s (SC-002)
**Constraints**: No SPA; Blade SSR; CSRF enabled; all protected routes behind `auth` middleware; no inline `$request->validate()`; no `$guarded = []`; all strings via `__()`; no hardcoded workflow steps in PHP
**Scale/Scope**: Up to hundreds of active applications; 6 tasks each; single reviewer pool (Phase 6 adds assignment)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| # | Principle | Status | Notes |
|---|-----------|--------|-------|
| I | Modular Architecture | ✅ PASS | Tasks module: `app/Http/Controllers/Reviewer/`, `app/Services/Tasks/`, `app/Http/Requests/Reviewer/`. Client Tasks tab update is within existing `Client` module. |
| II | Separation of Concerns | ✅ PASS | `ReviewerDashboardController` and `ReviewerApplicationController` delegate entirely to `WorkflowService`. Zero business logic in controllers or Blade. |
| III | Database-Driven Workflows | ✅ PASS | Steps defined in `workflow_step_templates` table per visa type. No step names, counts, or sequences hardcoded in PHP. Artisan command seeds templates. |
| IV | API-Ready Design | ✅ PASS | All data access via `WorkflowService`. Controllers pass arrays/models to views. |
| V | Roles & Permissions | ✅ PASS | Three new permissions: `tasks.view`, `tasks.advance`, `tasks.reject` seeded in `RolePermissionSeeder`. `ApplicationTaskPolicy` enforces per-task authorization. `$user->can()` gates only. |
| VI | Payment Integrity | ✅ N/A | Phase 5. |
| VII | Secure Document Handling | ✅ N/A | Phase 4. |
| VIII | Dynamic Step-Based Workflow Engine | ✅ PASS | This IS the implementation of Principle VIII. Steps are configurable per visa type in DB; snapshotted into `application_tasks` at creation; progress tracked with timestamps. |
| IX | Security by Default | ✅ PASS | `AdvanceTaskRequest` and `RejectTaskRequest` Form Requests validate notes. `$fillable` on all new models. CSRF on all routes. `auth` middleware + `can:tasks.view` on reviewer routes. |
| X | Multi-Language Support | ✅ PASS | New lang files: `lang/en/tasks.php` and `lang/en/reviewer.php` (+ AR). All Blade strings via `__('tasks.*')` / `__('reviewer.*')`. RTL inherited from `app.blade.php`. |
| XI | Observability & Activity Logging | ✅ PASS | `AuditLogService::log()` called for: `workflow_started`, `task_completed`, `task_rejected`, `application_approved`, `application_rejected`. |
| XII | Testing Standards | ✅ PASS | Feature tests: workflow happy path (all 6 steps), mid-workflow rejection, policy enforcement, client read-only, template-driven task count, backfill command. |

**Constitution Gate**: PASS — no violations. No Complexity Tracking entries required.

## Project Structure

### Documentation (this feature)

```text
specs/003-workflow-engine/
├── plan.md              ← This file
├── spec.md              ← Feature specification
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output
├── quickstart.md        ← Phase 1 output
├── contracts/
│   └── routes.md        ← Phase 1 output
└── tasks.md             ← Phase 2 output (/speckit.tasks — not created here)
```

### Source Code Layout

```text
app/
├── Http/
│   ├── Controllers/
│   │   └── Reviewer/
│   │       ├── DashboardController.php        # GET /reviewer/dashboard/{tab?}
│   │       └── ApplicationController.php      # GET/POST /reviewer/applications/{application}[/tasks/{task}/advance|reject]
│   ├── Requests/
│   │   └── Reviewer/
│   │       ├── AdvanceTaskRequest.php         # note: nullable, string, max:2000
│   │       └── RejectTaskRequest.php          # note: nullable, string, max:2000
│   └── Middleware/                             # No new middleware
├── Models/
│   ├── WorkflowStepTemplate.php               # belongsTo VisaType; hasMany ApplicationTask
│   └── ApplicationTask.php                    # belongsTo VisaApplication; belongsTo WorkflowStepTemplate
├── Policies/
│   └── ApplicationTaskPolicy.php             # advance(): can('tasks.advance'); reject(): can('tasks.reject')
├── Services/
│   └── Tasks/
│       └── WorkflowService.php               # seedTasksForApplication, advanceTask, rejectTask
└── Console/
    └── Commands/
        └── SeedApplicationWorkflowTasks.php  # php artisan workflow:seed-tasks {--application=}

database/
├── migrations/
│   ├── xxxx_create_workflow_step_templates_table.php
│   └── xxxx_create_application_tasks_table.php
└── seeders/
    ├── WorkflowStepTemplateSeeder.php         # Seeds 6 steps × 3 visa types = 18 template rows
    └── RolePermissionSeeder.php               # MODIFY: add tasks.view, tasks.advance, tasks.reject

resources/
├── views/
│   ├── reviewer/
│   │   ├── dashboard/
│   │   │   ├── index.blade.php               # Tab nav + @include active tab partial
│   │   │   └── tabs/
│   │   │       └── applications.blade.php    # Active application list (pending_review + in_progress)
│   │   └── applications/
│   │       └── show.blade.php                # Application detail + ordered task list + advance/reject forms
│   └── client/
│       └── dashboard/
│           └── tabs/
│               └── tasks.blade.php           # MODIFY: replace empty state with real task list
└── lang/
    ├── en/
    │   ├── tasks.php                         # Shared: status labels, task-related strings (EN)
    │   └── reviewer.php                      # Reviewer-specific: tab labels, buttons, headings (EN)
    └── ar/
        ├── tasks.php                         # Shared task strings (AR)
        └── reviewer.php                      # Reviewer strings (AR)

lang/
├── en/
│   ├── tasks.php                             # Proxy → resource_path('lang/en/tasks.php')
│   └── reviewer.php                          # Proxy → resource_path('lang/en/reviewer.php')
└── ar/
    ├── tasks.php                             # Proxy → resource_path('lang/ar/tasks.php')
    └── reviewer.php                          # Proxy → resource_path('lang/ar/reviewer.php')

routes/
└── web.php                                   # MODIFY: replace reviewer stub; add reviewer application routes

app/Services/Client/OnboardingService.php     # MODIFY: inject WorkflowService; call seedTasksForApplication() after transaction
app/Http/Controllers/Client/DashboardController.php  # MODIFY: eager-load tasks on application query
app/Providers/AppServiceProvider.php          # MODIFY: register ApplicationTaskPolicy
database/seeders/DatabaseSeeder.php           # MODIFY: add WorkflowStepTemplateSeeder call
```

**Structure Decision**: Laravel monolith (consistent with Phases 1 and 2). Reviewer module lives under `app/Http/Controllers/Reviewer/` and `app/Services/Tasks/`. Client module modifications are minimal (DashboardController eager-load, Tasks tab partial update). `WorkflowService` is in `app/Services/Tasks/` to keep it separate from the `Client` and `Auth` service namespaces already in use.

## Complexity Tracking

> No constitution violations — table not required.
