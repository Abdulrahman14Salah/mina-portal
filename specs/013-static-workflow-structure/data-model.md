# Data Model: Static Workflow Structure

**Branch**: `013-static-workflow-structure` | **Date**: 2026-03-22

---

## Existing Tables (no schema changes)

### `visa_types`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar | e.g. "Tourist Visa" |
| description | text nullable | |
| is_active | boolean | |
| timestamps | | |

---

## Modified Tables

### `workflow_sections` — add unique constraint

**Existing columns** (unchanged):

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| visa_type_id | bigint FK → visa_types | CASCADE DELETE |
| name | varchar | Section display name |
| position | smallint unsigned | Ordering within the visa type |
| timestamps | | |

**New constraint added by migration**:
- `UNIQUE (visa_type_id, position)` — enforces FR-005: no two sections may share the same position within a visa type

---

### `workflow_tasks` — type column changed + unique constraint

**Column changes**:

| Column | Before | After | Notes |
|--------|--------|-------|-------|
| type | ENUM('upload', 'text', 'both') | VARCHAR(50) | String column; valid values enforced at application layer |

**Valid type values** (enforced by `WorkflowTask::VALID_TYPES` constant and `StoreWorkflowTaskRequest`):

| Value | Meaning | Phase |
|-------|---------|-------|
| `question` | Task collects client answers | New (Phase 1) |
| `payment` | Task requires receipt upload | New (Phase 1) |
| `info` | Task shows instructions only | New (Phase 1) |
| `upload` | Legacy document upload task | Existing (kept for backward compatibility) |

**New constraint added by migration**:
- `UNIQUE (workflow_section_id, position)` — enforces FR-005: no two tasks may share the same position within a section

**All other columns** (unchanged):

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| workflow_section_id | bigint FK → workflow_sections | CASCADE DELETE |
| name | varchar | Task display title |
| description | text nullable | Optional instructions |
| position | smallint unsigned | Ordering within the section |
| timestamps | | |

---

## Entity Relationships

```
visa_types
  └── workflow_sections (1:N, CASCADE DELETE)
        └── workflow_tasks (1:N, CASCADE DELETE)
```

- One visa type → many sections (each with a unique position)
- One section → many tasks (each with a unique position)
- Sections and tasks are blueprint records — they do NOT belong to any client application

---

## Seeded Blueprint: Tourist Visa

The `WorkflowBlueprintSeeder` will produce the following structure for the "Tourist Visa" type:

| Section | Position | Task | Task Position | Task Type |
|---------|----------|------|---------------|-----------|
| Personal Information | 1 | Complete Personal Details | 1 | question |
| Personal Information | 1 | Identity Verification Info | 2 | info |
| Documentation | 2 | Application Fee Payment | 1 | payment |
| Documentation | 2 | Review Documentation Requirements | 2 | info |
| Interview Preparation | 3 | Pre-Interview Questionnaire | 1 | question |
| Interview Preparation | 3 | Interview Instructions | 2 | info |
| Final Submission | 4 | Final Payment | 1 | payment |
| Final Submission | 4 | Submission Confirmation | 2 | info |

All three new types (`question`, `payment`, `info`) are represented. Exactly 4 sections, 2 tasks per section.

---

## State / Lifecycle

`WorkflowSection` and `WorkflowTask` are static blueprint records with no status lifecycle. They are:
- Created once (via seeder or future admin UI)
- Read when generating application tasks (Phase 2)
- Never modified per-application

---

## Migrations Required (new — do not modify existing)

### Migration A: `update_workflow_tasks_type_column`
- Change `type` column from `ENUM('upload', 'text', 'both')` to `string('type', 50)->default('upload')`
- Safe: existing records with value `'upload'` remain valid

### Migration B: `add_unique_constraints_to_workflow_sections_and_tasks`
- Add `unique(['visa_type_id', 'position'])` on `workflow_sections`
- Add `unique(['workflow_section_id', 'position'])` on `workflow_tasks`
- Safe: no existing duplicate position data in any seeded dataset
