# Data Model: Document Management System

**Feature**: 010-document-management
**Date**: 2026-03-21

---

## Existing Tables (No Structural Change Required)

These tables are already correct and need no migration changes.

### `visa_applications`

Referenced by documents. No changes needed for this feature.

### `application_tasks`

Referenced by documents. Key field used by this feature:

| Column | Type | Notes |
|--------|------|-------|
| `status` | string | Values: `pending`, `in_progress`, `completed`, `rejected`. Used to block uploads/deletions to closed tasks. |

---

## Existing Table: `documents` (Requires One Migration Change)

### Current Schema

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint unsigned | No | PK |
| `application_id` | bigint unsigned | No | FK → `visa_applications.id` CASCADE DELETE |
| `application_task_id` | bigint unsigned | **No** | FK → `application_tasks.id` RESTRICT DELETE — **must become nullable** |
| `uploaded_by` | bigint unsigned | No | FK → `users.id` RESTRICT DELETE |
| `source_type` | varchar(20) | No | `'client'`, `'reviewer'`, `'admin'`. Default: `'client'` |
| `original_filename` | varchar(255) | No | Original name as uploaded |
| `stored_filename` | varchar(255) | No | UUID-based name on disk |
| `disk` | varchar(50) | No | `'local'` or `'s3'` |
| `path` | varchar(500) | No | Full storage path |
| `mime_type` | varchar(100) | No | Validated MIME type |
| `size` | bigint unsigned | No | File size in bytes |
| `created_at` / `updated_at` | timestamp | Yes | Laravel timestamps |

### Required Migration: Make `application_task_id` Nullable

```
Migration: YYYY_MM_DD_000001_make_application_task_id_nullable_on_documents_table

Change: application_task_id → nullable FK (still constrained to application_tasks.id)
```

**Business rule enforced at application level**: A document with `application_task_id IS NULL` is an application-level document. A document with a `application_task_id` value is task-attached.

### Required Migration: Add Delete Permissions

```
Migration: YYYY_MM_DD_000002_add_document_delete_permissions

Adds permissions:
  - documents.delete-own  → assigned to: client
  - documents.delete      → assigned to: admin (already has all permissions)
```

---

## Document Constraints & Business Rules

| Rule | Enforcement Layer |
|------|------------------|
| Max 10 documents per task (task-attached) | `DocumentService::upload()` |
| Max 10 documents per application (application-level, task = null) | `DocumentService::upload()` |
| Only open tasks accept new uploads (client role) | `Client\DocumentController::store()` |
| Clients can only delete own documents on open tasks | `DocumentPolicy::delete()` + `Client\DocumentController::destroy()` |
| Clients cannot delete reviewer/admin documents | `DocumentPolicy::delete()` |
| Admins can delete any document | `DocumentPolicy::delete()` |
| source_type must accurately reflect uploader role | Controllers pass explicit source_type |
| Files stored outside public web root | `DocumentService` uses configured private disk |
| All mutations logged | `AuditLogService` called in `DocumentService` |

---

## Relationships

```
VisaApplication
  ├── hasMany Document (all documents for the application)
  └── hasMany ApplicationTask
        └── hasMany Document (task-attached documents only)

Document
  ├── belongsTo VisaApplication (application_id)
  ├── belongsTo ApplicationTask (application_task_id — nullable)
  └── belongsTo User (uploaded_by)
```

---

## Permission Model

| Permission | Role | Description |
|------------|------|-------------|
| `documents.upload` | client | Upload documents (task-attached or application-level) |
| `documents.download` | admin, reviewer | Download any document via policy |
| `documents.admin-upload` | admin | Upload documents as admin |
| `documents.reviewer-upload` | reviewer | Upload documents as reviewer |
| `documents.delete-own` | client | Delete own uploaded documents (open task only) |
| `documents.delete` | admin | Delete any document on any application |

**Note**: Client download access is handled via `DocumentPolicy::download()` ownership check (no explicit `documents.download` permission needed for clients — they access via `$document->application->user_id === $user->id`).

---

## Source Type Values

| Value | Set By | Meaning |
|-------|--------|---------|
| `client` | Default / explicit in client upload | Document uploaded by the applicant |
| `reviewer` | Reviewer controller | Document uploaded by an assigned reviewer |
| `admin` | Admin controller | Document uploaded by a system administrator |

---

## State Transition: Document Lifecycle

```
[Uploaded] → [Active] → [Deleted by client (own, task open)]
                      → [Deleted by admin (any time)]
```

No soft deletes. Deletions are permanent. The audit log provides the deletion record.

---

## File Storage Layout

```
{disk}/
└── documents/
    └── {application_id}/
        └── {uuid}.{ext}          ← stored_filename pattern
```

Disk is determined by the `FILESYSTEM_DISK` environment variable (local dev → `local` private disk; production → `s3` private bucket). No code changes needed to switch drivers.
