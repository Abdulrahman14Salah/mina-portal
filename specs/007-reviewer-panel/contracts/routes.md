# Route Contracts: Reviewer Panel (007)

**Date**: 2026-03-20 | All routes under middleware: `auth`, `verified`

---

## Existing Routes (no change)

| Method | URI | Name | Middleware | Controller |
|--------|-----|------|------------|------------|
| GET | `/reviewer/dashboard/{tab?}` | `reviewer.dashboard` | `can:tasks.view` | `Reviewer\DashboardController@show` |
| GET | `/reviewer/applications/{application}` | `reviewer.applications.show` | `can:tasks.view` | `Reviewer\ApplicationController@show` |
| POST | `/reviewer/applications/{application}/tasks/{task}/advance` | `reviewer.applications.tasks.advance` | `can:tasks.advance` | `Reviewer\ApplicationController@advance` |
| POST | `/reviewer/applications/{application}/tasks/{task}/reject` | `reviewer.applications.tasks.reject` | `can:tasks.reject` | `Reviewer\ApplicationController@reject` |

---

## New Route

| Method | URI | Name | Middleware | Controller |
|--------|-----|------|------------|------------|
| POST | `/reviewer/applications/{application}/documents` | `reviewer.applications.documents.store` | `can:documents.reviewer-upload` | `Reviewer\DocumentController@store` |

### `reviewer.applications.documents.store` — Store Reviewer Document Upload

**Request Body** (multipart/form-data):
```
file                   required  file  PDF/JPG/JPEG/PNG, max 10MB
application_task_id    required  integer  must exist in application_tasks
```

**Success Response**: Redirect to `reviewer.applications.show` with success flash.

**Error Response**: Redirect back with validation errors.

**Authorization**: User must have `documents.reviewer-upload` permission. `application_task_id` must belong to the given `{application}`.

---

## Existing Shared Route (used by reviewer)

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| GET | `/documents/{document}/download` | `documents.download` | `DocumentController@download` |

Authorization enforced by `DocumentPolicy::download()`: allows any user with `documents.download` permission OR the document's owning client.
