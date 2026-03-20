# Data Model: Reviewer Panel (007)

**Date**: 2026-03-20 | **Branch**: `007-reviewer-panel`

---

## Existing Entities (no schema changes required)

### VisaApplication
Already has status field supporting: `pending_review`, `in_progress`, `approved`, `rejected`.
Task rejection → application status becomes `rejected` via `WorkflowService::rejectTask()` — already implemented and tested.

### ApplicationTask
Already has status field: `pending`, `in_progress`, `completed`, `rejected`.
Already has `reviewer_note` column for storing advance/reject notes.

### AuditLog
Already captures `document_downloaded` events via `AuditLogService`. No changes required.

---

## Modified Entity: Document

### Current Schema
```
documents
├── id
├── application_id       FK → visa_applications
├── application_task_id  FK → application_tasks
├── uploaded_by          FK → users
├── original_filename
├── stored_filename
├── disk
├── path
├── mime_type
├── size
└── timestamps
```

### Change: Add `source_type` Column

**Migration**: `add_source_type_to_documents_table`

```
ALTER TABLE documents
ADD COLUMN source_type VARCHAR(20) NOT NULL DEFAULT 'client'
    CHECK (source_type IN ('client', 'reviewer', 'admin'))
    AFTER uploaded_by;
```

**Allowed values**:
- `client` — uploaded by the applicant via client dashboard
- `reviewer` — uploaded by a reviewer via reviewer panel
- `admin` — uploaded by an admin via admin panel

**Default**: `'client'` — existing rows keep correct attribution without data migration.

**Model update** (`App\Models\Document`):
- Add `'source_type'` to `$fillable`

**Service update** (`App\Services\Documents\DocumentService::upload()`):
- Add parameter `string $sourceType = 'client'` as the last argument
- Pass `'source_type' => $sourceType` to `Document::create()`

---

## New Permission Record

| name | guard_name | assigned to |
|------|------------|-------------|
| `documents.reviewer-upload` | `web` | `reviewer` role |

Seeded in:
- `database/migrations/2026_03_20_000001_seed_roles_permissions_and_visa_types.php`
- `database/seeders/RolePermissionSeeder.php`

---

## State Transitions (Reviewer Panel Scope)

### Task Status
```
pending → in_progress → completed  (via advance)
                      ↘ rejected   (via reject)
```
No new transitions. Already implemented.

### Application Status (on task rejection)
```
pending_review / in_progress → rejected
```
Already implemented in `WorkflowService::rejectTask()`.
Confirmed by test: `test_reviewer_can_reject_task`.

---

## Validation Rules

### RejectTaskRequest (fix)
| Field | Current | Required |
|-------|---------|----------|
| `note` | `nullable\|string\|max:2000` | `required\|string\|min:5\|max:2000` |

### ReviewerUploadDocumentRequest (new)
| Field | Rule |
|-------|------|
| `file` | `required\|file\|mimes:pdf,jpg,jpeg,png\|max:10240` |
| `application_task_id` | `required\|integer\|exists:application_tasks,id` |
