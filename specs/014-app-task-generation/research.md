# Research: Application Task Generation (014)

## Decision 1 — Core implementation already exists

**Decision**: `WorkflowService::seedTasksForApplication()` fully covers Phase 2 requirements. Phase 2 work is verification, gap-filling, and ensuring correct test coverage — not a greenfield implementation.

**Rationale**: The method was introduced in feature 003-workflow-engine and extended in 012-workflow-integrity. It already handles: section-based blueprint copying (name, description, type, position), initial status assignment (first task → `in_progress`, rest → `pending`), idempotency guard, DB transaction atomicity, and audit logging.

**Alternatives considered**: Re-implementing via a model `created` observer on `VisaApplication`. Rejected — the existing `OnboardingService::handle()` explicit call is already in place, and observers would introduce hidden side-effects that are harder to test and reason about (constitution Principle III and research note from 003).

---

## Decision 2 — Trigger mechanism: OnboardingService, not model observer

**Decision**: Task seeding is triggered explicitly by `OnboardingService::handle()` immediately after the main transaction that creates the `VisaApplication`. The call is `$this->workflowService->seedTasksForApplication($application)` at line 61 of `OnboardingService`.

**Rationale**: Keeping seeding outside the main `DB::transaction()` ensures that if seeding fails, the application itself is still created (spec edge case FR-006). `seedTasksForApplication` runs its own inner transaction to keep tasks + status update atomic with each other. This was an explicit decision from 003-workflow-engine research.

**Alternatives considered**: Eloquent model observer (rejected — hidden side-effects); queue job (rejected — spec requires synchronous availability immediately after submission).

---

## Decision 3 — Status vocabulary: `approved` vs `completed`

**Decision**: The existing column uses statuses `pending`, `in_progress`, `approved`, `rejected`. UPDATE005.md Phase 2 mentions `completed` as a terminal status, but the codebase uses `approved` in its place (and `rejected` for the failure path). No rename migration will be done — `approved` is the canonical terminal status. The spec references `completed` as a conceptual state; the data layer uses `approved`.

**Rationale**: Renaming would break 184 passing tests and 10+ existing features. `approved` is semantically equivalent to `completed` in the context of the reviewer-controlled workflow. The spec assumption "Phase 2 is limited to task generation" means this vocabulary decision is out of scope to change here.

**Alternatives considered**: Migrating `approved` → `completed` across all tables and code. Deferred to a future refactor if the business requires surfacing `completed` to clients.

---

## Decision 4 — Legacy flat-template fallback retained

**Decision**: The `else` branch in `seedTasksForApplication()` that falls back to `WorkflowStepTemplate` records remains unchanged. Phase 2 scope is section-based blueprint seeding only; the fallback is a safety net for older visa types.

**Rationale**: Removing it would break existing applications that predate the section structure. The spec Assumptions section explicitly scopes Phase 2 to the section-based path.

---

## Decision 5 — Duplicate guard: early-return pattern

**Decision**: The idempotency guard `if ($application->tasks()->exists()) { return; }` at the top of `seedTasksForApplication()` satisfies FR-008 (no duplicate task sets). No additional unique index is needed on `application_tasks` for this invariant.

**Rationale**: The early-return pattern is simple, readable, and already present. A unique DB constraint on `(application_id, position)` would be redundant given the guard, and would cause cryptic errors rather than graceful no-ops if ever triggered.

---

## Decision 6 — Test coverage gaps to address

**Decision**: The following test cases are not yet covered by existing tests and should be added in Phase 2:

1. `test_tasks_generated_when_application_created_via_onboarding_service` — end-to-end test through `OnboardingService` verifying task count and types match the blueprint.
2. `test_first_task_is_in_progress_after_seeding` — explicit assertion that position-1 task has `in_progress` status and all others have `pending`.
3. `test_seeding_is_no_op_if_tasks_already_exist` — verify idempotency at the service level (already in WorkflowIntegrityTest; confirm coverage).
4. `test_application_created_for_visa_type_without_blueprint_has_zero_tasks` — confirm no error and zero tasks for blueprint-less visa types.

**Rationale**: `WorkflowSectionSeedingTest` already covers items 3 and 4 indirectly. Items 1 and 2 need explicit Phase 2 tests to match the spec acceptance scenarios.
