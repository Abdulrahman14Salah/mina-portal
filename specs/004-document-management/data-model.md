# Data Model: Phase 4 — Document Management System

**Date**: 2026-03-19
**Status**: Complete

---

## Entity: `documents`

Stores one record per uploaded file. Fields `original_filename`, `stored_filename`, `path`, `disk`, `mime_type`, and `size` are captured at upload time and never modified. The `application_task_id` FK anchors the document to the specific per-application task instance (not the blueprint template).

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `bigIncrements` | PK | |
| `application_id` | `foreignId` | NOT NULL, FK → `visa_applications.id`, CASCADE DELETE | Owning application — redundant with task but enables direct scoping |
| `application_task_id` | `foreignId` | NOT NULL, FK → `application_tasks.id`, RESTRICT ON DELETE | The specific per-application task this document belongs to |
| `uploaded_by` | `foreignId` | NOT NULL, FK → `users.id`, RESTRICT ON DELETE | The user who performed the upload (client or admin) |
| `original_filename` | `string(255)` | NOT NULL | Original name from the client's file system; used in download headers |
| `stored_filename` | `string(255)` | NOT NULL | UUID-based name on disk (e.g., `550e8400-e29b-41d4-a716-446655440000.pdf`) |
| `disk` | `string(50)` | NOT NULL | Storage driver name at upload time: `private` (local) or `s3` |
| `path` | `string(500)` | NOT NULL | Full relative path returned by `storeAs()` (e.g., `documents/42/{uuid}.pdf`) |
| `mime_type` | `string(100)` | NOT NULL | MIME type validated from file contents (e.g., `application/pdf`, `image/jpeg`) |
| `size` | `unsignedBigInteger` | NOT NULL | File size in bytes |
| `created_at` | `timestamp` | | Upload timestamp |
| `updated_at` | `timestamp` | | |

**Relationships**:
- Belongs to `VisaApplication` (as `$document->application`)
- Belongs to `ApplicationTask` (as `$document->task`) — FK `application_task_id`
- Belongs to `User` (as `$document->uploader`) — FK `uploaded_by`

**No unique constraint** on `(application_task_id, original_filename)` — multiple files with the same name are allowed for the same step (stored under different UUIDs).

---

## Entity: `visa_applications` (extended from Phase 3)

No schema changes. The `status` column gains one new active runtime value:

| Status value | When set | Phase |
|---|---|---|
| `pending_review` | Application created (Phase 2) | 2 |
| `in_progress` | First workflow task activated (Phase 3) | 3 |
| `awaiting_documents` | Client uploads first document on a document-required task | **4** |
| `under_review` | Deferred to Phase 7 (reviewer panel action) | 7 |
| `approved` | All tasks completed (Phase 3) | 3 |
| `rejected` | Any task rejected (Phase 3) | 3 |

**New relationship** (added in Phase 4):
```php
// On VisaApplication model — to be added
public function documents(): HasMany
{
    return $this->hasMany(Document::class, 'application_id');
}
```

---

## Entity: `application_tasks` (extended from Phase 3)

No schema changes. The existing `is_document_required` snapshot field (copied from `workflow_step_templates` at seeding time) is now actively consumed:
- Client Documents tab: upload form visible only when `$task->status === 'in_progress'` AND the task's template has `is_document_required = true`.
- The `ApplicationTask` model gains a relationship:

```php
public function documents(): HasMany
{
    return $this->hasMany(Document::class, 'application_task_id');
}
```

---

## New Permissions

Three new permissions added to `RolePermissionSeeder`:

| Permission | Assigned to | Description |
|---|---|---|
| `documents.upload` | `client`, `admin` | Can upload documents |
| `documents.download` | `reviewer`, `admin` | Can download any application's documents |
| `documents.admin-upload` | `admin` | Can upload documents on behalf of a client application |

**Note**: `client` does NOT receive `documents.download`. Clients access their documents via the ownership check in `DocumentPolicy::download()`. This prevents cross-application access.

---

## Validation Rules

### `UploadDocumentRequest` (client upload)

| Field | Rules |
|---|---|
| `file` | `required`, `file`, `mimes:pdf,jpg,jpeg,png`, `max:10240` (10 MB) |
| `application_task_id` | `required`, `integer`, `exists:application_tasks,id` |

### `AdminUploadDocumentRequest` (admin upload on behalf)

| Field | Rules |
|---|---|
| `file` | `required`, `file`, `mimes:pdf,jpg,jpeg,png`, `max:10240` (10 MB) |
| `application_task_id` | `required`, `integer`, `exists:application_tasks,id` |

Rules are identical — separate Form Request classes preserve the modular boundary and allow independent evolution.

---

## Migration Order

1. `xxxx_create_documents_table.php` (depends on `visa_applications`, `application_tasks`, `users`)

Runs after Phase 3 migrations.

---

## Status Transition: `awaiting_documents`

Triggered by `DocumentService::upload()` after a successful file store:

```php
// Atomic conditional update — no TOCTOU race condition
VisaApplication::whereKey($application->id)
    ->where('status', 'in_progress')
    ->update(['status' => 'awaiting_documents']);
```

Only transitions from `in_progress`. If the application is already `awaiting_documents` (prior upload), or `approved`/`rejected`, the query finds no matching row and silently does nothing.
