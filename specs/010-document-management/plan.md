# Implementation Plan: Document Management System

**Branch**: `010-document-management` | **Date**: 2026-03-21 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/010-document-management/spec.md`

---

## Summary

Extend the existing document management foundation to cover four gaps identified during the audit: application-level uploads (task-free), client document deletion (own, open task), document upload caps (10/task, 10/app-level), and admin document deletion. Fix two bugs: admin uploads recorded with wrong source type (`'client'`), and DOCX file type missing from validation. Add reviewer assignment enforcement and two new permissions (`documents.delete-own`, `documents.delete`).

The existing `DocumentService`, `DocumentPolicy`, all three role-specific `DocumentController`s, `Document` model, Form Requests, language files, and migrations are already in place and largely correct — this plan extends them minimally.

---

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 11
**Primary Dependencies**: spatie/laravel-permission v6+, Laravel Blade (SSR), AuditLogService (existing), FILESYSTEM_DISK env var (local/S3)
**Storage**: MySQL (MAMP, port 8889) dev; SQLite in-memory (tests); private local disk (dev) / S3 private bucket (prod) for files
**Testing**: PHPUnit via Laravel Feature Tests; SQLite in-memory
**Target Platform**: Web (MAMP local dev → production server)
**Project Type**: Web application (Laravel Blade SSR)
**Performance Goals**: Uploads complete in under 30 seconds (SC-001); validation rejects 100% of invalid files (SC-002)
**Constraints**: Files stored outside public web root; all file access via authenticated route + Policy; no direct URL access; source type must accurately reflect uploader role
**Scale/Scope**: 10 documents/task, 10 documents/application-level per application; unlimited applications

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Modular Architecture | PASS | Documents module already self-contained: `Models/Document`, `Services/Documents/DocumentService`, `Policies/DocumentPolicy`, `Http/Controllers/{Admin,Client,Reviewer}/DocumentController`, `Http/Requests/{Admin,Client,Reviewer}/UploadDocumentRequest`. New additions follow the same module boundary. |
| II. Separation of Concerns | PASS | Controllers handle HTTP only; all business logic (cap check, delete, upload) lives in `DocumentService`; Model contains only DB relationships. Plan maintains this separation. |
| III. Database-Driven Workflows | PASS | Document–task associations are FK relationships; no workflow logic hardcoded in PHP. |
| IV. API-Ready Design | PASS | All mutations go through `DocumentService`; controllers return structured redirects. If an API layer is added later, the Service is reusable. |
| V. Roles & Permissions — Granular | PASS | New permissions `documents.delete-own` (client) and `documents.delete` (admin) added via migration. All authorization via `$user->can()` and `DocumentPolicy`. No hardcoded role strings. |
| VII. Secure Document Handling | PASS | Files stored on private disk; served via authenticated download route + Policy check; S3 uses `temporaryUrl()`. MIME + size + extension validated via Form Requests. |
| IX. Security by Default | PASS | All routes auth-protected; all input via dedicated Form Request classes; `Document::$fillable` used; CSRF enabled. |
| X. Multi-Language Support | PASS | All new strings added to `resources/lang/en/documents.php` and `resources/lang/ar/documents.php`. No hardcoded strings in Blade or PHP. |
| XI. Observability | PASS | `AuditLogService::log()` called for upload, download, and (new) delete in `DocumentService`. |
| XII. Testing Standards | PASS | Feature tests required and will be written for all new upload paths, deletion rules, and cap enforcement. |

**Constitution violations**: None. No Complexity Tracking entry required.

---

## Project Structure

### Documentation (this feature)

```text
specs/010-document-management/
├── plan.md              ← this file
├── spec.md
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output
├── quickstart.md        ← Phase 1 output
├── contracts/
│   └── routes.md        ← Phase 1 output
└── tasks.md             ← Phase 2 output (/speckit.tasks command)
```

### Source Code Changes (repository root)

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   └── DocumentController.php        ← add destroy(), fix source_type
│   │   ├── Client/
│   │   │   └── DocumentController.php        ← add destroy(), support null task
│   │   └── Reviewer/
│   │       └── DocumentController.php        ← add reviewer assignment check, support null task
│   └── Requests/
│       ├── Admin/
│       │   └── AdminUploadDocumentRequest.php ← make task_id nullable, add docx
│       ├── Client/
│       │   └── UploadDocumentRequest.php      ← make task_id nullable, add docx
│       └── Reviewer/
│           └── UploadDocumentRequest.php      ← make task_id nullable, add docx
├── Models/
│   └── Document.php                           ← no changes needed
├── Policies/
│   └── DocumentPolicy.php                     ← add delete() gate
└── Services/
    └── Documents/
        └── DocumentService.php                ← update upload() signature, add delete(), add cap check

database/
└── migrations/
    ├── YYYY_MM_DD_000001_make_application_task_id_nullable_on_documents_table.php  ← NEW
    └── YYYY_MM_DD_000002_add_document_delete_permissions.php                       ← NEW

resources/
└── lang/
    ├── en/documents.php   ← add delete + app-level strings
    └── ar/documents.php   ← add delete + app-level strings

routes/
└── web.php   ← add DELETE /client/documents/{document}, DELETE /admin/applications/{application}/documents/{document}

tests/
└── Feature/
    ├── Client/
    │   └── DocumentUploadTest.php        ← update: add app-level upload, cap, delete tests
    ├── Admin/
    │   └── DocumentAdminTest.php         ← update: add delete, source_type fix tests
    └── Reviewer/
        └── DocumentReviewerTest.php      ← update: add assignment check, app-level upload tests
```

**Structure Decision**: Single-project Laravel structure (Option 1). All changes are extensions to the existing `Documents` module. No new modules or directories needed.

---

## Implementation Phases

### Phase A: Database & Permissions (no app logic changes)

**Prerequisite for all other work.**

1. **Migration — nullable task ID**
   - Create `YYYY_MM_DD_000001_make_application_task_id_nullable_on_documents_table.php`
   - Change: `$table->foreignId('application_task_id')->nullable()->constrained('application_tasks')->nullOnDelete()->change()`
   - Note: use `nullOnDelete()` so deleting a task nullifies the document's task link rather than blocking deletion

2. **Migration — delete permissions**
   - Create `YYYY_MM_DD_000002_add_document_delete_permissions.php`
   - Add `documents.delete-own` permission → sync to `client` role
   - Add `documents.delete` permission → sync to `admin` role

---

### Phase B: Service Layer

**All business logic changes. Depended on by all controllers.**

3. **`DocumentService::upload()` — make task optional + add cap enforcement**
   - Change signature: `upload(VisaApplication $application, ?ApplicationTask $task, UploadedFile $file, User $uploader, string $sourceType = 'client'): Document`
   - Cap check (before storing):
     - If `$task` is not null: `abort_if(Document::where('application_task_id', $task->id)->count() >= 10, 422)`
     - If `$task` is null: `abort_if(Document::where('application_id', $application->id)->whereNull('application_task_id')->count() >= 10, 422)`
   - Set `application_task_id` conditionally: `'application_task_id' => $task?->id`
   - Audit log message updated to handle null task gracefully

4. **`DocumentService::delete()` — new method**
   ```
   delete(Document $document, User $actor): void
     → Storage::disk($document->disk)->delete($document->path)
     → $document->delete()
     → $this->auditLog->log('document_deleted', $actor, ['document_id' => $document->id, 'reference' => ...])
   ```

---

### Phase C: Policy

5. **`DocumentPolicy::delete()` — new gate**
   ```
   delete(User $user, Document $document): bool
     → if admin: $user->can('documents.delete')
     → if client (own doc):
         - $user->can('documents.delete-own')
         - AND $document->uploaded_by === $user->id
         - AND ($document->application_task_id === null  // app-level
               OR $document->task->status not in ['completed', 'rejected'])
     → else: false
   ```

---

### Phase D: Form Requests

**All three requests get the same two changes.**

6. **Make `application_task_id` optional**: Change `'required'` to `'nullable'` in all three Form Requests.
7. **Add DOCX**: Change `mimes:pdf,jpg,jpeg,png` to `mimes:pdf,jpg,jpeg,png,docx` in all three.

---

### Phase E: Controllers

8. **`Client\DocumentController::store()` — support null task + closed task check**
   - When `application_task_id` is present: load task, verify ownership, check task is not closed, call service with task
   - When absent: load application directly via a new approach (need to pass application_id), call service with null task
   - Note: the current route `POST /client/documents` doesn't take an application ID — for app-level uploads the `application_id` must be included in the request body or derived from the client's active application. Add `application_id` as an optional request field (validated against the authenticated user's applications).

9. **`Client\DocumentController::destroy()` — new method**
   - Load document, call `$this->authorize('delete', $document)`
   - Call `$this->documentService->delete($document, Auth::user())`
   - Redirect back with success flash

10. **`Admin\DocumentController::store()` — fix source_type**
    - Change call: `$this->documentService->upload($application, $task, $request->file('file'), $user, 'admin')`

11. **`Admin\DocumentController::destroy()` — new method**
    - Load document, verify `$document->application_id === $application->id` (abort 404 if not)
    - Call `$this->authorize('delete', $document)`
    - Call `$this->documentService->delete($document, Auth::user())`
    - Redirect with success flash

12. **`Reviewer\DocumentController::store()` — add assignment check + null task support**
    - Add reviewer assignment guard (check assigned_reviewer_id or pivot depending on model)
    - Support null task (same pattern as client controller)
    - Pass `'reviewer'` as source_type (already does this correctly)

---

### Phase F: Routes

13. **Add DELETE routes to `routes/web.php`**:
    ```php
    // Client group
    Route::delete('/client/documents/{document}', [ClientDocumentController::class, 'destroy'])
        ->middleware('active')
        ->name('client.documents.destroy');

    // Admin group
    Route::delete('/admin/applications/{application}/documents/{document}',
        [AdminDocumentController::class, 'destroy'])
        ->middleware('can:dashboard.admin')
        ->name('admin.applications.documents.destroy');
    ```

---

### Phase G: Language Files

14. **Add to `resources/lang/en/documents.php`**:
    ```php
    'delete_success'             => 'Document deleted successfully.',
    'delete_confirm'             => 'Are you sure you want to delete this document? This cannot be undone.',
    'delete_error_task_closed'   => 'This document cannot be deleted because the task is closed.',
    'delete_error_forbidden'     => 'You do not have permission to delete this document.',
    'application_documents'      => 'Application Documents',
    'upload_application_level'   => 'Upload General Document',
    'reviewer_upload_success'    => 'Document uploaded successfully.',
    ```

15. **Add to `resources/lang/ar/documents.php`** (Arabic equivalents):
    ```php
    'delete_success'             => 'تم حذف المستند بنجاح.',
    'delete_confirm'             => 'هل أنت متأكد من حذف هذا المستند؟ لا يمكن التراجع عن هذا الإجراء.',
    'delete_error_task_closed'   => 'لا يمكن حذف هذا المستند لأن المهمة مغلقة.',
    'delete_error_forbidden'     => 'ليس لديك صلاحية لحذف هذا المستند.',
    'application_documents'      => 'مستندات الطلب',
    'upload_application_level'   => 'رفع مستند عام',
    'reviewer_upload_success'    => 'تم رفع المستند بنجاح.',
    ```

---

### Phase H: Tests

16. **`tests/Feature/Client/DocumentUploadTest.php`** — add:
    - Test: client uploads application-level document (no task_id) → success
    - Test: client uploads to closed task → 422
    - Test: client reaches 10-doc cap on a task → 422
    - Test: client reaches 10-doc cap on application-level → 422
    - Test: client deletes own document on open task → success
    - Test: client deletes own document on closed task → 403/422
    - Test: client attempts to delete reviewer document → 403
    - Test: client uploads DOCX → success

17. **`tests/Feature/Admin/DocumentAdminTest.php`** — add:
    - Test: admin uploads document → source_type is 'admin'
    - Test: admin uploads application-level document (no task) → success
    - Test: admin deletes any document → success + audit log entry
    - Test: admin deletes document from wrong application → 404

18. **`tests/Feature/Reviewer/DocumentReviewerTest.php`** — add:
    - Test: reviewer uploads to assigned application → success
    - Test: reviewer uploads to unassigned application → 403
    - Test: reviewer uploads application-level document → success

---

## Dependency Order

```
Phase A (migrations) → Phase B (service) → Phase C (policy)
Phase B + Phase C + Phase D (form requests) → Phase E (controllers)
Phase E + Phase F (routes) → Phase G (language) → Phase H (tests)
```

Phases D and G can run in parallel with other phases. Tests (Phase H) must be last.

---

## Open Questions for Implementation

1. **`Client\DocumentController::store()` for app-level uploads**: The current route `POST /client/documents` has no application context. For task-attached uploads, the application is derived from the task. For app-level uploads, an `application_id` must come from somewhere. **Decision**: Add `application_id` as an optional field in `Client\UploadDocumentRequest`, validated as `exists:visa_applications,id`. In the controller, when `application_task_id` is null, load the application from `application_id` and verify `application->user_id === Auth::id()`.

2. **Reviewer assignment model**: The `VisaApplication` model's assignment field name must be confirmed before writing the reviewer check. Likely `assigned_reviewer_id` on `visa_applications` table, but could be a pivot. The implementer should read `VisaApplication` model and migration before writing the guard.

3. **`nullOnDelete()` vs `restrictOnDelete()`**: Changing from `restrictOnDelete` to `nullOnDelete` means deleting an ApplicationTask will null out the document's task reference. Confirm this is acceptable before running the migration. If tasks should not be deletable while documents exist, keep `restrictOnDelete` and only change `nullable`.
