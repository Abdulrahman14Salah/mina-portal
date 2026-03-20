# Research: Phase 3 — Workflow Engine

**Date**: 2026-03-19
**Status**: Complete

---

## Decision 1: ApplicationTask snapshot vs live FK reference

**Decision**: Snapshot — copy `name`, `description`, `position` from `WorkflowStepTemplate` into `ApplicationTask` at creation time.

**Rationale**: US3 acceptance scenario 4 requires that changing a template does **not** modify existing applications' tasks. A live FK reference means a template name change would silently rename tasks on in-flight applications — a data integrity violation and a confusing audit trail. Snapshotting freezes the task record at point-of-creation, matching the "event sourcing lite" pattern used in financial and workflow systems. The template FK (`workflow_step_template_id`) is still stored for traceability back to the origin template, but it is never used to derive display values.

**Alternatives considered**:
- Live FK reference (`$task->template->name`): rejected — template changes mutate live application data without audit trail.
- Full JSON snapshot of entire template: rejected — overkill; only 3 fields need to be frozen (name, description, position).

---

## Decision 2: Application status sync — service method vs model observer

**Decision**: `WorkflowService` service methods (`advanceTask`, `rejectTask`, `seedTasksForApplication`) explicitly update both `ApplicationTask` and `VisaApplication.status` inside `DB::transaction()` calls. No model observers.

**Rationale**: Model observers are opaque — they fire as a side effect of any `save()` call, making it easy to accidentally trigger or suppress them in tests. Explicit service methods are self-documenting, trivially testable (inject a mock), and consistent with the pattern established in Phase 2 (OnboardingService, AuditLogService). Constitution Principle II (Separation of Concerns) reinforces this: services own business logic.

**Alternatives considered**:
- `ApplicationTask::updated` observer recalculates status: rejected — causes N+1 status recalculations on bulk seed, produces opaque side effects.
- Derived status (computed from tasks on every read): rejected — forces a JOIN on every application load; can't index; breaks Constitution Principle III (status must be a persisted field).

---

## Decision 3: Task seeding integration with OnboardingService

**Decision**: Inject `WorkflowService` into `OnboardingService`. After the existing `DB::transaction()` completes successfully, call `$this->workflowService->seedTasksForApplication($application)` **outside** the main transaction. `seedTasksForApplication` runs its own inner `DB::transaction()`.

**Rationale**: The spec edge case states: "if no step template exists, the application is still created." This means task seeding must NOT cause the entire application creation to roll back. Keeping seeding outside the main transaction satisfies this. The inner transaction in `seedTasksForApplication` ensures tasks + status update are atomic with each other.

**Alternatives considered**:
- Seeding inside the main transaction: rejected — a missing template would orphan the entire onboarding flow, blocking application creation.
- `VisaApplication::created` Eloquent event: rejected — fires inside `create()`'s implicit transaction (before the outer `DB::transaction()` commits), creating a complex nested-transaction scenario. Also violates Constitution Principle II by hiding business logic in a model event.

---

## Decision 4: ApplicationTaskPolicy vs VisaApplicationPolicy extension

**Decision**: Create a dedicated `ApplicationTaskPolicy` with `advance` and `reject` methods. Reviewer controllers also check `$user->can('tasks.view')` for listing.

**Rationale**: The authorization logic differs per actor: clients may view tasks (own application only, via `VisaApplicationPolicy::view`), but only reviewers/admins may advance or reject tasks. Merging task-level permissions into `VisaApplicationPolicy` would make that policy responsible for two separate concerns. A dedicated `ApplicationTaskPolicy` keeps the reviewer's permission model explicit and independently testable.

**Alternatives considered**:
- Inline `$user->can('tasks.advance')` checks in controllers: rejected — bypasses Policy pattern; violates Constitution Principle V.
- Extending `VisaApplicationPolicy` with `advanceTask` / `rejectTask` methods: acceptable but creates a bloated policy mixing two entity types.

---

## Decision 5: Artisan backfill command design

**Decision**: `php artisan workflow:seed-tasks {--application=}` — accepts an optional `--application` flag (application ID). Without the flag, seeds all applications that have zero `application_tasks` records and whose `visa_type_id` has at least one template.

**Rationale**: The flag-based interface is safer than a positional argument — operators can run `--application=42` for a single targeted seed or omit it for a batch run. Checking for existing tasks before seeding prevents double-seeding if the command is run twice.

**Alternatives considered**:
- Migration-based seeding at deploy time: rejected — auto-seeding in migrations is dangerous in production (applications could be mid-review); operator control is safer.
- Seeder class (not artisan command): rejected — seeders are harder to scope to a subset of records; artisan commands support `--application` targeting.

---

## Decision 6: Reviewer route structure

**Decision**: Two new URL groups:
1. `/reviewer/dashboard/{tab?}` → `Reviewer\DashboardController@show` — tabbed reviewer dashboard (Applications tab = active queue)
2. `/reviewer/applications/{application}` → `Reviewer\ApplicationController@show` — application detail + task list
3. `POST /reviewer/applications/{application}/tasks/{task}/advance` → `Reviewer\ApplicationController@advance`
4. `POST /reviewer/applications/{application}/tasks/{task}/reject` → `Reviewer\ApplicationController@reject`

**Rationale**: Separating the list view (dashboard) from the detail view (application controller) keeps each controller focused on a single responsibility. The `{application}` route binding uses implicit model binding against `visa_applications.id`. The `{task}` binding uses implicit model binding against `application_tasks.id`.

**Alternatives considered**:
- Single `ReviewerDashboardController` with advance/reject methods: rejected — violates single-responsibility; too many actions in one controller.
- AJAX/fetch-based task advancement: rejected — introduces JS complexity; HTMX or full-page POST redirect is consistent with the Blade SSR approach used in Phase 2.

---

## Decision 7: Language file structure

**Decision**:
- `lang/en/tasks.php` + `lang/ar/tasks.php` — shared task status labels and common task-related strings used by **both** the client Tasks tab and the reviewer task list.
- `lang/en/reviewer.php` + `lang/ar/reviewer.php` — reviewer dashboard strings: tab labels, column headers, action buttons, headings.
- Proxy files in `lang/` delegate to `resources/lang/` following the Phase 1/2 pattern.

**Rationale**: Task status labels (`Pending`, `In Progress`, `Completed`, `Rejected`) appear in both the client dashboard and the reviewer views. A shared `tasks.php` file prevents duplication and guarantees consistency between the two UIs.
