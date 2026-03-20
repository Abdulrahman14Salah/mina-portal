# Data Model: Phase 3 — Workflow Engine

**Date**: 2026-03-19
**Status**: Complete

---

## Entity: `workflow_step_templates`

Blueprint steps that define the ordered workflow for each visa type. Managed directly in the database; the admin UI to CRUD templates is Phase 6. Phase 3 seeds defaults.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `bigIncrements` | PK | |
| `visa_type_id` | `foreignId` | NOT NULL, FK → `visa_types.id`, CASCADE DELETE | The visa type this template step belongs to |
| `position` | `unsignedSmallInteger` | NOT NULL | Order of this step within the visa type (1-based) |
| `name` | `string(150)` | NOT NULL | Display name shown to clients and reviewers (e.g., "Initial Review") |
| `description` | `text` | NULLABLE | Shown to clients on Tasks tab to explain what this step means |
| `is_document_required` | `boolean` | NOT NULL, DEFAULT false | Phase 4 integration point — flags that this step requires document upload |
| `created_at` | `timestamp` | | |
| `updated_at` | `timestamp` | | |

**Unique constraint**: `(visa_type_id, position)` — no two steps at the same position for the same visa type.

**Relationships**:
- Belongs to `VisaType`
- Has many `ApplicationTask` (via `workflow_step_template_id`)

**Seeder**: `WorkflowStepTemplateSeeder` — seeds 6 ordered steps for each of the 3 visa types from Phase 2:

| Position | Name | Description |
|----------|------|-------------|
| 1 | Application Received | Your application has been received and is awaiting initial review. |
| 2 | Initial Review | Our team is reviewing your submitted application details. |
| 3 | Document Request | We are preparing a list of required documents for your application. |
| 4 | Document Review | Your submitted documents are under review by our team. |
| 5 | Assessment | Your application is being assessed for a final recommendation. |
| 6 | Final Decision | A final decision is being made on your visa application. |

---

## Entity: `application_tasks`

Concrete instances of workflow steps, one per step per application. Created from the template at application submission time. Fields `name`, `description`, and `position` are **snapshotted** (copied from the template) so future template changes do not affect in-flight applications.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `bigIncrements` | PK | |
| `application_id` | `foreignId` | NOT NULL, FK → `visa_applications.id`, CASCADE DELETE | The owning application |
| `workflow_step_template_id` | `foreignId` | NOT NULL, FK → `workflow_step_templates.id`, RESTRICT | Back-reference to source template (for reporting only) |
| `position` | `unsignedSmallInteger` | NOT NULL | Snapshotted from template at creation; determines sort order |
| `name` | `string(150)` | NOT NULL | Snapshotted from template at creation |
| `description` | `text` | NULLABLE | Snapshotted from template at creation |
| `status` | `string(20)` | NOT NULL, DEFAULT `'pending'` | Valid values: `pending`, `in_progress`, `completed`, `rejected` |
| `reviewer_note` | `text` | NULLABLE | Optional note added by reviewer when completing or rejecting |
| `completed_at` | `timestamp` | NULLABLE | Set when `status` transitions to `completed` or `rejected` |
| `created_at` | `timestamp` | | |
| `updated_at` | `timestamp` | | |

**Relationships**:
- Belongs to `VisaApplication` (as `$task->application`)
- Belongs to `WorkflowStepTemplate`

**Status lifecycle**:

```
[seeded] → pending
              ↓ (first task auto-set to in_progress on seed)
           in_progress → completed → (next task auto-set to in_progress)
                       ↘ rejected  (application status set to rejected; no further transitions)
```

At any point, exactly ONE task per application has status `in_progress`; all prior tasks are `completed`; all subsequent tasks are `pending`.

---

## Entity: `visa_applications` (extended from Phase 2)

No schema changes. The `status` column (`string(30)`) gains additional valid runtime values:

| Status value | When set |
|---|---|
| `pending_review` | Application created (Phase 2); also when no workflow template exists |
| `in_progress` | First task activated by `WorkflowService::seedTasksForApplication()` |
| `awaiting_documents` | Phase 4 integration — set when active task has `is_document_required = true` |
| `under_review` | Phase 4 integration — set when documents submitted |
| `approved` | All tasks completed (final task marked `completed`) |
| `rejected` | Any task marked `rejected` |

Status transitions are exclusively set by `WorkflowService` methods; no other code may set `visa_applications.status` directly.

---

## New Permissions

Three new permissions are added to `RolePermissionSeeder` in Phase 3:

| Permission | Assigned to | Description |
|---|---|---|
| `tasks.view` | `reviewer`, `admin` | Can view active applications and their task lists |
| `tasks.advance` | `reviewer`, `admin` | Can mark the in_progress task as completed |
| `tasks.reject` | `reviewer`, `admin` | Can mark the in_progress task as rejected |

---

## Migration Order

1. `xxxx_create_workflow_step_templates_table.php` (depends on `visa_types`)
2. `xxxx_create_application_tasks_table.php` (depends on `visa_applications`, `workflow_step_templates`)

Both run after the Phase 2 migrations.

---

## Validation Rules

### `AdvanceTaskRequest` (used by `ReviewerApplicationController@advance`)

| Field | Rules |
|---|---|
| `note` | `nullable`, `string`, `max:2000` |

### `RejectTaskRequest` (used by `ReviewerApplicationController@reject`)

| Field | Rules |
|---|---|
| `note` | `nullable`, `string`, `max:2000` |
