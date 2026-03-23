# Data Model: Application Task Generation (014)

## Existing Schema (no migrations required)

Phase 2 requires **no new migrations**. The `application_tasks` table already has all required columns, and both ENUM→string migrations for `type` were applied in Phase 1 (013).

### `application_tasks` (existing, unchanged)

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | Auto-increment |
| `application_id` | bigint FK → visa_applications | The owning application |
| `workflow_step_template_id` | bigint FK (nullable) | Legacy flat-template reference; `null` for section-based tasks |
| `position` | integer | Copied from `workflow_tasks.position`; determines order |
| `name` | string | Copied from `workflow_tasks.name` (UPDATE005.md calls this "title") |
| `description` | text (nullable) | Copied from `workflow_tasks.description` |
| `type` | string(50) | Copied from `workflow_tasks.type`; values: `upload`, `question`, `payment`, `info` |
| `status` | string | `pending` → `in_progress` → `approved` (terminal) or `rejected` |
| `reviewer_note` | text (nullable) | Set during approval/rejection |
| `rejection_reason` | text (nullable) | Set during rejection |
| `completed_at` | timestamp (nullable) | Set when task reaches `approved` or `rejected` |
| `created_at` / `updated_at` | timestamps | Standard Laravel |

### `visa_applications` (existing, unchanged)

| Column | Relevant to Phase 2 |
|--------|---------------------|
| `id` | PK — referenced by `application_tasks.application_id` |
| `visa_type_id` | FK → `visa_types`; used to look up the blueprint sections |
| `status` | Set to `in_progress` by seeding when tasks are created |

### `workflow_sections` (read-only in Phase 2)

| Column | Used in seeding |
|--------|----------------|
| `id` | Referenced to load ordered tasks |
| `visa_type_id` | FK; filter criterion for blueprint lookup |
| `position` | Ordering of sections |

### `workflow_tasks` (read-only in Phase 2)

| Column | Copied to application_tasks |
|--------|-----------------------------|
| `name` | → `application_tasks.name` |
| `description` | → `application_tasks.description` |
| `type` | → `application_tasks.type` |
| `position` | → `application_tasks.position` (re-sequenced globally across sections) |

## Status Lifecycle

```
[ Application created ]
         │
         ▼
  seedTasksForApplication()
         │
         ├─ task position 1 → status: in_progress
         └─ all other tasks → status: pending

         │   (Phase 5+ manages transitions)
         ▼
    in_progress → approved  (reviewer marks complete)
    in_progress → rejected  (reviewer rejects)
    rejected    → in_progress  (reviewer re-opens)
```

**Note**: UPDATE005.md uses the term `completed` for the terminal success state. In the codebase the equivalent column value is `approved`. No rename is planned (see research.md Decision 3).

## Entity Relationships

```
visa_types
  └── workflow_sections (1→many, ordered by position)
        └── workflow_tasks (1→many, ordered by position)  ← Blueprint (read-only source)

visa_applications
  └── application_tasks (1→many, ordered by position)    ← Generated copy; independent
        └── documents (1→many)
```

## Seeding Algorithm (existing implementation)

```
seedTasksForApplication(application):
  IF application.tasks.exists → return (idempotent guard)

  hasSections = WorkflowSection.where(visa_type_id = application.visa_type_id).exists

  DB.transaction:
    position = 1
    tasks = []

    IF hasSections:
      FOR each section (ordered by section.position):
        FOR each workflowTask (ordered by workflowTask.position):
          tasks[] = ApplicationTask.create(
            application_id = application.id,
            position       = position++,    ← globally re-sequenced
            name           = workflowTask.name,
            description    = workflowTask.description,
            type           = workflowTask.type,
            status         = 'pending'
          )
    ELSE:
      fallback to WorkflowStepTemplate records (legacy path)

    IF tasks not empty:
      tasks[0].update(status = 'in_progress')
      application.update(status = 'in_progress')
      seeded = true

  IF seeded:
    auditLog.log('workflow_started', user, {reference})
```

## Invariants

1. Each application has at most one task set (idempotency guard).
2. Exactly one task per application has `status = 'in_progress'` at any given time (enforced by WorkflowService).
3. `application_tasks` rows are independent of blueprint rows after creation — no FK to `workflow_tasks`.
4. Position values in `application_tasks` are globally sequential integers starting at 1, regardless of section boundaries.
