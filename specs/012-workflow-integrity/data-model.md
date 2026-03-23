# Data Model: Workflow Integrity Fixes

**Branch**: `012-workflow-integrity` | **Date**: 2026-03-22

> This feature introduces **zero schema changes**. Both fixes are query logic corrections inside `WorkflowService`. The data model below describes the existing entities involved, included here for completeness and to define the constraints the fixes must respect.

---

## No New Migrations

No new tables, columns, or indexes are added. No existing columns are modified.

---

## Existing Entities Affected

### ApplicationTask

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint unsigned | PK |
| `application_id` | bigint unsigned | FK → `visa_applications.id` |
| `workflow_step_template_id` | bigint unsigned, nullable | FK → `workflow_step_templates.id`; null for section-based tasks |
| `position` | int | Display and execution order. **NOT guaranteed contiguous.** |
| `name` | string | |
| `description` | text, nullable | |
| `type` | enum('upload','text','both') | |
| `status` | string | Valid values: `pending`, `in_progress`, `approved`, `rejected` |
| `reviewer_note` | text, nullable | |
| `rejection_reason` | text, nullable | |
| `completed_at` | datetime, nullable | |

**Position invariant**: For a given `application_id`, `position` values are unique but may contain gaps of any size. The query `WHERE position > :current ORDER BY position ASC LIMIT 1` is the correct way to find the next task.

### VisaApplication

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint unsigned | PK |
| `status` | string | Transitions to `in_progress` only when at least one task is seeded |

**Status invariant**: `status` MUST NOT be set to `in_progress` (during seeding) unless at least one `ApplicationTask` was created. This invariant is already correctly enforced in the `if (! empty($tasks))` block; the audit log fix ensures the log mirrors this same condition.

### AuditLog (logical — stored via `AuditLogService`)

| Field | Notes |
|-------|-------|
| `event` | e.g., `workflow_started`, `task_completed`, `task_approved` |
| `user` | Actor — the application owner (fetched via `$application->user()->first()`) |
| `context` | Array with `reference` key for `workflow_started` events |

**Audit invariant**: A `workflow_started` entry MUST be present if and only if at least one `ApplicationTask` row exists for the application with `status IN ('pending', 'in_progress', 'approved', 'rejected')`.

---

## State Transition Diagram — Workflow Seeding

```
Application submitted
        │
        ▼
seedTasksForApplication()
        │
        ├─── Tasks exist already? ──YES──► RETURN (no-op, no log)
        │
        ├─── No templates/sections? ──YES──► RETURN ($seeded = false, no log)
        │
        └─── Tasks created ──────────────► $seeded = true
                                           first task → in_progress
                                           application → in_progress
                                           AuditLog: workflow_started  ◄── only here
```

## State Transition Diagram — Task Progression

```
Current task (in_progress)
        │
        ▼ [advance / approve]
Current task → approved
        │
        ▼ Query: SELECT * FROM application_tasks
          WHERE application_id = :id
          AND position > :current_position
          ORDER BY position ASC
          LIMIT 1
        │
        ├─── Found? ──YES──► Next task → in_progress
        │
        └─── Not found? ────► Workflow complete (no-op)
```
