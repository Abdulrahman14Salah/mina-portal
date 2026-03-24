# Data Model: Workflow System (Core System)

**Date**: 2026-03-21
**Feature**: 009-workflow-system

> Phase 2 requires no new database tables. All entities described here are already implemented. This document records the authoritative schema and state machine relevant to this feature, plus the one column-level change required (removal of auto-transition side effects — schema unchanged, only service logic).

---

## Entities

### WorkflowStepTemplate

**Table**: `workflow_step_templates`
**Model**: `App\Models\WorkflowStepTemplate`

| Field | Type | Rules |
|---|---|---|
| id | unsignedBigInteger | PK, auto-increment |
| visa_type_id | foreignId → visa_types | required; cascadeOnDelete |
| position | unsignedSmallInteger | required; ordering within visa type |
| name | string(150) | required |
| description | text | nullable |
| is_document_required | boolean | default: false |
| created_at / updated_at | timestamps | auto-managed |

**Unique constraint**: `[visa_type_id, position]` — no two templates at the same position for the same visa type.

**Relationships**: belongs to `VisaType`; has many `ApplicationTask`.

---

### ApplicationTask

**Table**: `application_tasks`
**Model**: `App\Models\ApplicationTask`

| Field | Type | Rules |
|---|---|---|
| id | unsignedBigInteger | PK, auto-increment |
| application_id | foreignId → visa_applications | required; cascadeOnDelete |
| workflow_step_template_id | foreignId → workflow_step_templates | required; restrictOnDelete |
| position | unsignedSmallInteger | required; task sequence within application |
| name | string(150) | required; copied from template at seed time |
| description | text | nullable; copied from template |
| status | string(20) | default: `pending`; see state machine below |
| reviewer_note | text | nullable; populated on advance or reject; cleared on re-open |
| completed_at | timestamp | nullable; set when status → `completed`; cleared on re-open |
| created_at / updated_at | timestamps | auto-managed |

**Fillable**: `application_id`, `workflow_step_template_id`, `position`, `name`, `description`, `status`, `reviewer_note`, `completed_at`

**Relationships**: belongs to `VisaApplication`; belongs to `WorkflowStepTemplate`; has many `Document`.

---

### ApplicationTask State Machine

```
                    ┌──────────────────────────────────────┐
                    │                                      │
         seed       ▼      WorkflowService::advanceTask()  │
  ───────────► pending ────────────────────────────────────┤
               (all but                                    │
               first task)  WorkflowService::advanceTask() │
                            (current active task,         │
         seed               non-final step)               │
  ───────────► in_progress ──────────────────────────────────► in_progress (next position)
               (first task  │
               on creation) │  advanceTask() (final step)
                            ├──────────────────────────────► completed  [terminal]
                            │
                            │  WorkflowService::rejectTask()
                            └──────────────────────────────► rejected
                                                              │
                                                              │  WorkflowService::reopenTask()
                                                              └──────────────────────────────► in_progress
```

**Valid transitions**:

| From | Action | To | Side Effects |
|---|---|---|---|
| `pending` | `advanceTask()` on PREVIOUS task completing | `in_progress` | None |
| `in_progress` | `advanceTask()` (non-final) | `completed` → next task `in_progress` | Sets `completed_at`; logs `task_completed` |
| `in_progress` | `advanceTask()` (final task) | `completed` | Sets `completed_at`; logs `task_completed` |
| `in_progress` | `rejectTask()` | `rejected` | Sets `reviewer_note`, `completed_at`; logs `task_rejected` |
| `rejected` | `reopenTask()` | `in_progress` | Clears `reviewer_note`, `completed_at`; logs `task_reopened` |

**Guard**: Only `in_progress` tasks accept `advanceTask()` or `rejectTask()`. Only `rejected` tasks accept `reopenTask()`. All other transitions are refused with an exception.

**Removed side effects** (Gap 2): `advanceTask()` no longer sets `application.status → 'approved'`. `rejectTask()` no longer sets `application.status → 'rejected'`. Application status is managed exclusively by admin action.

---

### VisaApplication (status field — Phase 2 scope)

**Table**: `visa_applications`
**Model**: `App\Models\VisaApplication`

Status values relevant to Phase 2:

| Status | Set By | Meaning |
|---|---|---|
| `pending_review` | Application creation | Submitted, no workflow activity yet |
| `in_progress` | `WorkflowService::seedTasksForApplication()` | Tasks seeded, workflow active |
| `approved` | Admin manual action | Admin has concluded the application positively |
| `rejected` | Admin manual action | Admin has concluded the application negatively |

> **Important**: In Phase 2, `WorkflowService` only transitions `VisaApplication.status` from `pending_review` → `in_progress` during task seeding. All other status changes are admin-only.

---

### AuditLog (events added in Phase 2)

**New event added**:

| Event | Triggered By | Metadata |
|---|---|---|
| `task_reopened` | `WorkflowService::reopenTask()` | `{ task_id, application_id, task_name }` |

**Existing events preserved**:

| Event | Triggered By |
|---|---|
| `task_completed` | `WorkflowService::advanceTask()` |
| `task_rejected` | `WorkflowService::rejectTask()` |

---

## No Schema Changes Required

Phase 2 requires zero new migrations. All tables and columns are already present. The only data-layer change is the removal of `VisaApplication` status mutations from `advanceTask()` and `rejectTask()`.
