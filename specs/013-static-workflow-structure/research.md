# Research: Static Workflow Structure

**Branch**: `013-static-workflow-structure` | **Date**: 2026-03-22

---

## Decision 1 — Type Column Strategy

**Decision**: Replace the `workflow_tasks.type` `ENUM('upload', 'text', 'both')` with `VARCHAR/string('type', 50)`, and enforce valid values at the application layer (Form Request + Model constant).

**Rationale**: The existing enum excludes all three new types (`question`, `payment`, `info`). Migrating an enum in MySQL requires a raw `ALTER TABLE … MODIFY COLUMN` statement that is not portable to SQLite (used in tests). Converting to a plain string column with application-level validation (`in:` rule) achieves identical enforcement, works across both drivers without driver-specific SQL, and is simpler to extend later. The `text` and `both` legacy enum values are dropped at this point — no records carry those values in any seeded dataset.

**Alternatives considered**:
- Alter enum via raw MySQL statement in migration — not portable to SQLite test DB; fragile
- Keep enum, add new values — MySQL requires lock on large tables; still not portable
- PHP 8.1 backed enum on the model — adds complexity with no benefit over a string + const list

---

## Decision 2 — Position Uniqueness Enforcement

**Decision**: Add a new migration that adds composite unique indexes: `['visa_type_id', 'position']` on `workflow_sections` and `['workflow_section_id', 'position']` on `workflow_tasks`.

**Rationale**: FR-005 requires uniqueness at the DB level. The existing migrations have no such constraint, meaning duplicate positions are silently accepted. A unique index enforces the invariant at the database level and causes an integrity exception on violation — no application code required for enforcement.

**Alternatives considered**:
- Application-level uniqueness check only — does not protect against concurrent inserts or direct DB writes
- Soft enforcement via ordering query only — does not prevent duplicate storage

---

## Decision 3 — Blueprint Seeder Design

**Decision**: Create a standalone `WorkflowBlueprintSeeder` class (not modifying `VisaTypeSeeder`). Seed the "Tourist Visa" type with exactly 4 sections and 8 tasks covering all three new types (`question`, `payment`, `info`). The seeder uses `firstOrCreate` on both sections and tasks (keyed by visa_type_id + position for sections, section_id + position for tasks) to remain idempotent.

**Rationale**: `VisaTypeSeeder` has a single responsibility (seeding visa type records). A separate `WorkflowBlueprintSeeder` follows single-responsibility and can be run independently. `firstOrCreate` prevents duplicate seeding on repeated runs.

**Alternatives considered**:
- Extend `VisaTypeSeeder` — violates single responsibility; makes the seeder harder to test in isolation
- Plain `insert()` without idempotency — unsafe to run multiple times

---

## Decision 4 — Type Validation Locations

**Decision**: Enforce valid types in two places:
1. `StoreWorkflowTaskRequest` — updated from `in:upload,text,both` to `in:upload,question,payment,info` (handles HTTP input)
2. `WorkflowTask::VALID_TYPES` constant + a `saving` model observer check (handles direct Eloquent saves that bypass the Form Request)

**Rationale**: Tests create `WorkflowTask` directly via `WorkflowTask::create()`, not via HTTP. If validation only exists in the Form Request, the model-level test in US2 (scenario 4) would not be caught. The `saving` observer throws an `InvalidArgumentException` on invalid type, matching the pattern already used in `WorkflowService`.

**Alternatives considered**:
- Form Request validation only — does not protect direct model saves in tests and seeder calls
- Database check constraint (MySQL `CHECK`) — not supported in all MySQL versions and not portable to SQLite

---

## Decision 5 — Test Strategy

**Decision**: New `tests/Feature/Tasks/WorkflowStructureTest.php` covering:
- US1: Blueprint sections/tasks retrievable in position order for seeded visa type
- US2: All four valid types accepted; invalid type rejected at model level
- US3: Blueprint stability — modifying a blueprint task does not retroactively alter application_tasks

**Rationale**: Existing tests in `WorkflowIntegrityTest`, `WorkflowSectionSeedingTest`, and `WorkflowSectionTaskBuilderTest` do not cover type enforcement or position uniqueness. A dedicated test file follows the existing naming convention in `tests/Feature/Tasks/`.

**Test isolation**: All tests use `RefreshDatabase` + `RolePermissionSeeder` + `VisaTypeSeeder`. The `WorkflowBlueprintSeeder` is called explicitly within tests that need it.

---

## Decision 6 — Constitution Conflict: Multi-Language Principle

**Observation**: Constitution Principle X requires Arabic and English as first-class languages. Arabic support was deliberately removed in the immediately preceding feature (by explicit user request — all `lang/ar/` files deleted, `SetLocale` hardcoded to `en`). This is an acknowledged business override, not an oversight.

**Impact on Phase 1**: Phase 1 has no user-facing strings (data-only). No AR/EN translation files are needed. If the constitution is later re-amended to re-add AR support, Phase 1 code requires no changes.
