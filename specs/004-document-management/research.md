# Research: Phase 4 — Document Management System

**Date**: 2026-03-19
**Status**: Complete

---

## Decision 1: Private disk file serving — local vs S3

**Decision**: Use a single download controller method that branches on the document's stored `disk` name.

- **Local private disk** (`private`): Stream via `Storage::disk('private')->download($path, $originalFilename)` → returns a `StreamedResponse` that pipes the file to the browser without loading it entirely into memory.
- **S3** (`s3`): Generate a temporary signed URL via `Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(5))` → redirect the client to the pre-signed URL. The S3 URL is time-limited and contains an HMAC signature; guessing it without the secret key is computationally infeasible.

```php
// DocumentService::serve(Document $document): StreamedResponse|RedirectResponse
$disk = Storage::disk($document->disk);
if ($document->disk === 's3') {
    return redirect($disk->temporaryUrl($document->path, now()->addMinutes(5)));
}
return $disk->download($document->path, $document->original_filename);
```

The `disk` column on the `documents` table stores which storage driver was active at upload time. This ensures documents uploaded to local disk during development are served correctly even if S3 is enabled later.

**Rationale**: Branching on the stored disk name decouples download logic from the current `.env` configuration. A document uploaded on local disk will still serve correctly if the operator switches to S3 later — and vice versa.

**Alternatives considered**:
- Single `response()->streamDownload()` wrapper for both — rejected because S3 temporary URLs are more efficient (no PHP memory for large files).
- Public disk with randomised filenames — rejected outright (constitution Principle VII forbids public storage).

---

## Decision 2: File upload validation rules

**Decision**: Use `mimes:pdf,jpg,jpeg,png` (validates file contents via MIME sniffing, not just extension) combined with `max:10240` (10 MB in kilobytes) in a dedicated `UploadDocumentRequest` Form Request.

```php
public function rules(): array
{
    return [
        'file'                => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        'application_task_id' => ['required', 'integer', 'exists:application_tasks,id'],
    ];
}
```

The `mimes` rule uses PHP's `finfo` extension to read the file's binary signature — a renamed `.exe` with a `.pdf` extension will be rejected. The `max:10240` rule is in kilobytes (10 MB = 10 × 1024 KB).

**Rationale**: Extension-only validation (`extensions:`) can be bypassed by renaming files. `mimes:` validates actual content. Consistent with constitution Principle IX (security by default).

**Alternatives considered**:
- `mimetypes:application/pdf,image/jpeg,image/png` — more explicit but equivalent; `mimes:` is the idiomatic Laravel rule and expands to the correct MIME types automatically.

---

## Decision 3: Secure filename storage pattern

**Decision**: Store the original filename for display; generate a UUID-based unique name for disk storage.

```php
// In DocumentService::upload()
$originalFilename = $file->getClientOriginalName();
$extension        = $file->getClientOriginalExtension();
$storedFilename   = \Illuminate\Support\Str::uuid() . '.' . $extension;
$path             = $file->storeAs(
    'documents/' . $application->id,
    $storedFilename,
    ['disk' => config('filesystems.default')]
);

Document::create([
    'application_id'      => $application->id,
    'application_task_id' => $task->id,
    'uploaded_by'         => $uploader->id,
    'original_filename'   => $originalFilename,
    'stored_filename'     => $storedFilename,
    'disk'                => config('filesystems.default'),
    'path'                => $path,
    'mime_type'           => $file->getMimeType(),
    'size'                => $file->getSize(),
]);
```

The `path` column stores the full relative path returned by `storeAs()` (e.g., `documents/42/550e8400-e29b-41d4-a716-446655440000.pdf`). This is the value passed to `Storage::disk($disk)->download($path, $originalFilename)`.

**Note on `config('filesystems.default')`**: Phase 4 stores the disk name at upload time so future disk changes do not affect older documents. In development, `FILESYSTEM_DISK=private` (local). In production, set `FILESYSTEM_DISK=s3`.

**Rationale**: UUID filenames prevent path enumeration (no sequential IDs). Storing the original filename separately allows correct `Content-Disposition: attachment; filename="passport.pdf"` headers without exposing the internal path.

---

## Decision 4: `DocumentPolicy` for mixed access control

**Decision**: The `DocumentPolicy::download()` method allows reviewers/admins by permission, and clients by ownership — all others are denied.

```php
public function download(User $user, Document $document): bool
{
    // Reviewers and admins: broad permission
    if ($user->can('documents.download')) {
        return true;
    }
    // Clients: own application only
    return $document->application->user_id === $user->id;
}

public function upload(User $user): bool
{
    return $user->can('documents.upload');
}

public function adminUpload(User $user, VisaApplication $application): bool
{
    return $user->can('documents.admin-upload');
}
```

The `$document->application->user_id` comparison uses Eloquent's `application` relationship (already defined on `Document` model via `belongsTo(VisaApplication::class)`). If the relation is not eager-loaded at call time it lazy-loads — acceptable in this context because the policy runs once per request.

**Rationale**: Using `can()` for staff roles keeps authorization declarative and permission-table-driven. The ownership fallback for clients avoids giving them a broad `documents.download` permission that would expose other clients' files.

---

## Decision 5: Conditional status transition to `awaiting_documents`

**Decision**: Use a single `whereKey()->where('status', 'in_progress')->update()` to transition atomically only if the status is still `in_progress`.

```php
// In DocumentService::upload(), after successful file store and Document::create():
VisaApplication::whereKey($application->id)
    ->where('status', 'in_progress')
    ->update(['status' => 'awaiting_documents']);
```

This is a single UPDATE query with a WHERE clause — no race condition risk for concurrent uploads on the same application, because the second concurrent upload will find `status != 'in_progress'` and silently skip. No `fresh()` call needed; the update is fire-and-forget from the service perspective.

**Rationale**: Avoids the read-then-write pattern (`if ($application->status === 'in_progress') { $application->update(...); }`) which has a TOCTOU race condition under concurrent requests.

---

## Decision 6: Admin application routes (minimal, Phase 4 scope)

**Decision**: Add a minimal `GET /admin/applications` list and `GET|POST /admin/applications/{application}/documents` to the existing `admin.` route group. This gives admins a way to navigate to an application and upload documents without waiting for Phase 6's full admin panel.

The admin application list shows: reference number, client name, visa type, status, link to documents page. No edit/delete — that is Phase 6.

**Rationale**: US3 (admin upload) is Priority P3. Without any navigation, it is inaccessible. A minimal 2-route addition is preferable to leaving P3 unimplementable.

**Alternatives considered**:
- Deferred entirely to Phase 6 — rejected because US3 is in scope for Phase 4 per the spec.

---

## Decision 7: New permissions

Three new permissions are added to `RolePermissionSeeder`:

| Permission | Assigned to | Purpose |
|---|---|---|
| `documents.upload` | `client`, `admin` | Upload documents (client: own application; admin: any) |
| `documents.download` | `reviewer`, `admin` | Download any application's documents |
| `documents.admin-upload` | `admin` | Upload documents on behalf of a client |

The `documents.download` permission is deliberately NOT given to `client` — clients access their documents via the ownership check in `DocumentPolicy::download()`, not by permission. This prevents a client from downloading another client's documents by constructing a valid document URL.

---

## Decision 8: Lang file structure

Following the established Phase 2/3 pattern:
- Real content in `resources/lang/en/documents.php` and `resources/lang/ar/documents.php`
- Proxies in `lang/en/documents.php` and `lang/ar/documents.php` (each containing `return require resource_path('lang/{locale}/documents.php')`)

A single shared `documents.php` file covers both the client Documents tab and reviewer/admin document lists.

---

## Decision 9: Upload form scoping — active task only

The upload form appears on the Documents tab **only** for the current `in_progress` task if `is_document_required = true` on that task's template. Previously uploaded documents are always shown as a read-only list grouped by task step. This matches FR-001 ("only when the active step requires documents") and US1 scenario 5.

The `Client\DashboardController` already eager-loads `tasks` ordered by position. Phase 4 requires additionally eager-loading `tasks.documents` to render per-task document lists on the Documents tab.
