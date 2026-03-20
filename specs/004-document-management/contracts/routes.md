# Route Contracts: Phase 4 — Document Management System

**Date**: 2026-03-19

All routes are web routes (Blade, CSRF enabled, session-based auth).

---

## Client Document Routes

### POST `/client/documents`
- **Controller**: `Client\DocumentController@store`
- **Purpose**: Upload a file for a specific application task
- **Middleware**: `['auth', 'verified', 'active']`
- **Authorization**: `$this->authorize('upload', Document::class)` → `DocumentPolicy::upload` checks `$user->can('documents.upload')`
- **Request**: `Client\UploadDocumentRequest` (`file`: required, mimes:pdf,jpg,jpeg,png, max:10240; `application_task_id`: required, integer, exists:application_tasks,id)
- **Business logic**:
  1. Resolve the `ApplicationTask` — abort 403 if it does not belong to the authenticated client's application
  2. Store file, create `Document` record (via `DocumentService::upload()`)
  3. Conditionally transition application status to `awaiting_documents`
  4. Log `document_uploaded` audit event
- **Success response**: `redirect()->route('client.dashboard', ['tab' => 'documents'])->with('success', __('documents.upload_success'))`
- **Named route**: `client.documents.store`

---

## Shared Download Route

### GET `/documents/{document}/download`
- **Controller**: `DocumentController@download`
- **Purpose**: Serve a document file — streams for local disk, temporary redirect for S3
- **Middleware**: `['auth', 'verified']`
- **Authorization**: `$this->authorize('download', $document)` → `DocumentPolicy::download` — allows reviewers/admins (via `documents.download` permission) OR the client who owns the application
- **Route model binding**: `{document}` → `Document` by `id`
- **Response**: `Storage::disk($document->disk)->download($document->path, $document->original_filename)` for local disk; `redirect(Storage::disk('s3')->temporaryUrl($document->path, now()->addMinutes(5)))` for S3
- **Named route**: `documents.download`

---

## Admin Document Routes

### GET `/admin/applications`
- **Controller**: `Admin\ApplicationController@index`
- **Purpose**: Minimal application list for admin navigation to document management (pre-Phase 6 admin panel)
- **Middleware**: `['auth', 'verified']`
- **Authorization**: `$user->can('dashboard.admin')` via route middleware `can:dashboard.admin`
- **Data passed to view**: `$applications` — collection with `user`, `visaType` eager-loaded
- **Named route**: `admin.applications.index`

### GET `/admin/applications/{application}/documents`
- **Controller**: `Admin\DocumentController@index`
- **Purpose**: Show all documents for an application with an admin upload form
- **Middleware**: `['auth', 'verified']`
- **Authorization**: `$this->authorize('adminUpload', [Document::class, $application])` → `DocumentPolicy::adminUpload` checks `$user->can('documents.admin-upload')`
- **Route model binding**: `{application}` → `VisaApplication` by `id`
- **Data passed to view**: `$application` (with `user`, `visaType`, `tasks.documents.uploader` eager-loaded), `$tasks` (ordered by position, each with documents)
- **Named route**: `admin.applications.documents.index`

### POST `/admin/applications/{application}/documents`
- **Controller**: `Admin\DocumentController@store`
- **Purpose**: Admin uploads a document on behalf of a client application
- **Middleware**: `['auth', 'verified']`
- **Authorization**: `$this->authorize('adminUpload', [Document::class, $application])` → `DocumentPolicy::adminUpload` checks `$user->can('documents.admin-upload')`
- **Request**: `Admin\AdminUploadDocumentRequest` (`file`: required, mimes:pdf,jpg,jpeg,png, max:10240; `application_task_id`: required, integer, exists:application_tasks,id)
- **Business logic**: `DocumentService::upload($application, $task, $file, $adminUser)` — uploader recorded as admin; `awaiting_documents` transition applies equally
- **Success response**: `redirect()->route('admin.applications.documents.index', $application)->with('success', __('documents.admin_upload_success'))`
- **Named route**: `admin.applications.documents.store`

---

## Reviewer Routes (existing `reviewer.` group — modified)

### GET `/reviewer/applications/{application}` (existing — modified)
- **Controller**: `Reviewer\ApplicationController@show` (MODIFY: eager-load `tasks.documents.uploader`)
- **No route change** — the document list for reviewers is added to the existing show view
- **Data change**: `$application` now eager-loads `tasks` → each task has `documents` → each document has `uploader`

---

## Route Registration Summary

```php
// Shared download (all authenticated users — policy enforces access)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/documents/{document}/download', [\App\Http\Controllers\DocumentController::class, 'download'])
        ->name('documents.download');
});

// Client document upload (inside existing ['auth', 'verified', 'active'] group or as standalone)
Route::middleware(['auth', 'verified', 'active'])->prefix('client')->name('client.')->group(function () {
    // existing client routes...
    Route::post('/documents', [\App\Http\Controllers\Client\DocumentController::class, 'store'])
        ->name('documents.store');
});

// Admin application + document routes (inside existing admin group)
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    // existing admin routes...
    Route::get('/applications', [\App\Http\Controllers\Admin\ApplicationController::class, 'index'])
        ->middleware('can:dashboard.admin')
        ->name('applications.index');
    Route::get('/applications/{application}/documents', [\App\Http\Controllers\Admin\DocumentController::class, 'index'])
        ->name('applications.documents.index');
    Route::post('/applications/{application}/documents', [\App\Http\Controllers\Admin\DocumentController::class, 'store'])
        ->name('applications.documents.store');
});
```

---

## Middleware Applied

| Middleware | Where applied | Purpose |
|---|---|---|
| `auth` | All routes | Requires authentication |
| `verified` | All routes | Email verification (currently no-op) |
| `active` | Client upload | Requires active account status |
| `can:dashboard.admin` | Admin application list | Admin-only navigation |
| `DocumentPolicy` (via `authorize`) | All document actions | Per-action permission and ownership check |
