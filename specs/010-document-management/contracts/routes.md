# Route Contracts: Document Management System

**Feature**: 010-document-management
**Date**: 2026-03-21
**Project type**: Laravel Blade SSR web application

All routes require `auth` + `verified` middleware unless noted. Policy checks are enforced inside each controller action.

---

## Existing Routes (No Change)

| Method | URI | Controller | Policy Gate | Name |
|--------|-----|-----------|-------------|------|
| GET | `/documents/{document}/download` | `DocumentController@download` | `download` on Document | `documents.download` |
| POST | `/client/documents` | `Client\DocumentController@store` | `upload` on Document | `client.documents.store` |
| POST | `/admin/applications/{application}/documents` | `Admin\DocumentController@store` | `adminUpload` on Document | `admin.applications.documents.store` |
| GET | `/admin/applications/{application}/documents` | `Admin\DocumentController@index` | `adminUpload` on Document | `admin.applications.documents.index` |
| POST | `/reviewer/applications/{application}/documents` | `Reviewer\DocumentController@store` | `reviewerUpload` on Document | `reviewer.applications.documents.store` |

---

## New Routes Required

### Client: Delete Own Document

| Method | URI | Controller | Policy Gate | Name |
|--------|-----|-----------|-------------|------|
| DELETE | `/client/documents/{document}` | `Client\DocumentController@destroy` | `delete` on Document | `client.documents.destroy` |

**Constraints**:
- `auth`, `verified`, `active` middleware
- Policy enforces: document belongs to authenticated client's application, task status is open (or null task = application-level, application is active)
- Redirects back with success/error flash message

**Request body**: none (document identified by route parameter)

**Success response**: Redirect to `client.dashboard` tab=documents with `success` flash
**Error responses**:
- 403 — document not owned by this client, or reviewer/admin document
- 422 — task is closed (cannot delete)

---

### Admin: Delete Any Document

| Method | URI | Controller | Policy Gate | Name |
|--------|-----|-----------|-------------|------|
| DELETE | `/admin/applications/{application}/documents/{document}` | `Admin\DocumentController@destroy` | `delete` on Document | `admin.applications.documents.destroy` |

**Constraints**:
- `auth`, `verified`, `can:dashboard.admin` middleware
- Policy enforces: user has `documents.delete` permission
- Document must belong to the given application (abort 404 if not)

**Request body**: none

**Success response**: Redirect to `admin.applications.documents.index` for the application with `success` flash
**Error responses**:
- 403 — insufficient permission
- 404 — document not found or does not belong to application

---

## Request Shape: Upload (All Roles)

The `application_task_id` field becomes optional across all Form Requests.

### Client Upload (`POST /client/documents`)

```
Content-Type: multipart/form-data

file                  required  file    mimes:pdf,jpg,jpeg,png,docx  max:10240
application_task_id   nullable  integer exists:application_tasks,id
```

### Reviewer Upload (`POST /reviewer/applications/{application}/documents`)

```
Content-Type: multipart/form-data

file                  required  file    mimes:pdf,jpg,jpeg,png,docx  max:10240
application_task_id   nullable  integer exists:application_tasks,id
```

### Admin Upload (`POST /admin/applications/{application}/documents`)

```
Content-Type: multipart/form-data

file                  required  file    mimes:pdf,jpg,jpeg,png,docx  max:10240
application_task_id   nullable  integer exists:application_tasks,id
```

---

## Business Rule Enforcement at Route Layer

| Rule | Where Enforced |
|------|----------------|
| Client can only upload to own application | `abort_if($task->application->user_id !== Auth::id(), 403)` or application ownership check for app-level |
| Client blocked from uploading to closed task | `abort_if(in_array($task->status, ['completed', 'rejected']), 422)` |
| Reviewer can only upload to assigned application | `abort_if($application->assigned_reviewer_id !== Auth::id(), 403)` (or pivot check) |
| 10-doc cap per task / 10-doc cap per app-level | `DocumentService::upload()` throws 422 when cap reached |
| Admin source type recorded correctly | Admin controller passes `'admin'` as `$sourceType` |

---

## Flash Message Keys (Localization)

| Key | Context |
|-----|---------|
| `documents.upload_success` | Client upload success |
| `documents.admin_upload_success` | Admin upload success |
| `documents.delete_success` | Delete success (any role) |
| `documents.delete_error_task_closed` | Client delete blocked (task closed) |
| `documents.delete_error_forbidden` | Delete blocked (permission denied) |
