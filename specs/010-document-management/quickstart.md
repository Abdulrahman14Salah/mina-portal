# Quickstart: Document Management System

**Feature**: 010-document-management
**Date**: 2026-03-21

---

## What This Feature Does

Extends the existing document upload system with:
- **Application-level uploads**: any role can attach documents to an application without linking to a specific task
- **Client document deletion**: clients can delete their own uploads while the task is still open
- **Admin document deletion**: admins can delete any document at any time
- **Upload caps**: max 10 documents per task, max 10 per application-level bucket
- **DOCX support**: added to the allowed file types
- **Bug fixes**: admin source type recorded correctly; reviewer assignment enforced

---

## Prerequisites

- Laravel app running locally via MAMP (port 8889)
- `.env` configured with `FILESYSTEM_DISK=local`
- Database migrated: `php artisan migrate`
- Roles and permissions seeded (runs as part of migration)

---

## Running Migrations

```bash
# After pulling the feature branch
php artisan migrate
```

Two new migrations run:
1. Makes `application_task_id` nullable on the `documents` table
2. Adds `documents.delete-own` (client) and `documents.delete` (admin) permissions

---

## Running Tests

```bash
# All document-related tests
php artisan test tests/Feature/Client/DocumentUploadTest.php
php artisan test tests/Feature/Admin/DocumentAdminTest.php
php artisan test tests/Feature/Reviewer/DocumentReviewerTest.php
```

---

## Key Files Changed

| File | Change |
|------|--------|
| `app/Services/Documents/DocumentService.php` | `upload()` makes task optional + cap enforcement; new `delete()` method |
| `app/Policies/DocumentPolicy.php` | New `delete()` gate |
| `app/Http/Controllers/Client/DocumentController.php` | `store()` supports null task; new `destroy()` |
| `app/Http/Controllers/Admin/DocumentController.php` | `store()` passes `'admin'` source type; new `destroy()` |
| `app/Http/Controllers/Reviewer/DocumentController.php` | Reviewer assignment check; supports null task |
| `app/Http/Requests/*/UploadDocumentRequest.php` (×3) | `application_task_id` nullable; DOCX added |
| `routes/web.php` | Two new DELETE routes |
| `resources/lang/en/documents.php` | Delete + app-level strings |
| `resources/lang/ar/documents.php` | Delete + app-level strings (Arabic) |

---

## Allowed File Types

`pdf`, `jpg`, `jpeg`, `png`, `docx` — max 10 MB per file.

Validation is content-based (MIME type checked against actual file bytes via PHP's `finfo`), not extension-only.

---

## Upload Modes

**Task-attached upload** — include `application_task_id` in the form:
```html
<input type="hidden" name="application_task_id" value="{{ $task->id }}">
```

**Application-level upload** — include `application_id`, omit `application_task_id`:
```html
<input type="hidden" name="application_id" value="{{ $application->id }}">
```

---

## Document Deletion

Client delete (own document, open task):
```html
<form method="POST" action="{{ route('client.documents.destroy', $document) }}">
    @csrf
    @method('DELETE')
    <button type="submit">Delete</button>
</form>
```

Admin delete (any document):
```html
<form method="POST" action="{{ route('admin.applications.documents.destroy', [$application, $document]) }}">
    @csrf
    @method('DELETE')
    <button type="submit">Delete</button>
</form>
```
