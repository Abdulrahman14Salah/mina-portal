# Research: Document Management System

**Feature**: 010-document-management
**Date**: 2026-03-21
**Status**: Complete — no NEEDS CLARIFICATION items remain

---

## Existing Codebase Audit

The project already has a substantial document management foundation. This research summarises what exists, identifies gaps against the spec, and records decisions for each gap.

---

### Finding 1: `application_task_id` Is Not Nullable

**Problem**: The `create_documents_table` migration defines `application_task_id` as a required foreign key (`->constrained('application_tasks')->restrictOnDelete()`). All Form Requests require it. `DocumentService::upload()` accepts an `ApplicationTask` parameter (not optional).

**Spec requirement**: FR-001 and the Q4 clarification require application-level uploads (no task association).

**Decision**: Add a new migration that makes `application_task_id` nullable. Update all three Form Requests to make the field `nullable|integer|exists:...`. Update `DocumentService::upload()` to accept `?ApplicationTask`. Update all controllers to pass null when no task is selected.

**Rationale**: A new migration (rather than modifying the existing one) preserves the migration history. Making the column nullable is the minimal change.

**Alternatives considered**: Adding a separate `application_documents` table — rejected as unnecessary complexity; a nullable FK on the existing table is sufficient.

---

### Finding 2: No Document Delete Functionality

**Problem**: No `destroy` method exists in any DocumentController. No `documents.delete` or `documents.delete-own` permission exists in the seeder. `DocumentPolicy` has no `delete` gate. `DocumentService` has no `delete` method.

**Spec requirements**: FR-012 (clients delete own docs while task open), FR-003 (admins delete any), FR-013 (audit log on delete), Q1 clarification.

**Decision**:
- Add `documents.delete-own` permission → assign to `client` role.
- Add `documents.delete` permission → assign to `admin` role (already has all permissions via syncPermissions).
- Add `DocumentPolicy::delete()` gate:
  - Admin: always true.
  - Client (own doc, task-attached): allowed only if associated task status is not closed/completed.
  - Client (own doc, application-level): allowed while application is active.
- Add `DocumentService::delete()`: removes from storage, soft-deletes or hard-deletes record, writes audit log entry.
- Add `destroy` to `Client\DocumentController` and `Admin\DocumentController`.
- Add DELETE routes for both.

**Rationale**: Keeping delete logic in DocumentService keeps Controllers thin and the Service testable.

---

### Finding 3: No Document Cap Enforcement

**Problem**: `DocumentService::upload()` performs no count check. The spec (Q4 clarification) caps uploads at 10 per task and 10 per application-level bucket.

**Decision**: Add count enforcement inside `DocumentService::upload()`:
- Task-attached: count existing documents where `application_task_id = $task->id`.
- Application-level: count existing documents where `application_id = $application->id AND application_task_id IS NULL`.
- Abort with HTTP 422 if the count reaches 10.

**Rationale**: Enforcing in the Service (not the Form Request) keeps the rule in one place and ensures it applies regardless of which controller calls upload.

---

### Finding 4: Closed Task Upload Blocking Not Enforced for Clients

**Problem**: `Client\DocumentController::store()` does no task-state check before uploading. `ApplicationTask` has a `status` field (confirmed via `$fillable`).

**Spec requirement**: FR-014 — uploads to closed/completed tasks must be blocked.

**Decision**: In `Client\DocumentController::store()`, after loading the task, add `abort_if(in_array($task->status, ['completed', 'rejected']), 422)`. The same check should apply in `Client\DocumentController::destroy()` — clients cannot delete documents from closed tasks.

**Rationale**: The status values `completed` and `rejected` are observable from `ApplicationTask::$fillable` and existing workflow logic.

---

### Finding 5: Admin Source Type Recorded as 'client'

**Problem**: `Admin\DocumentController::store()` calls `$this->documentService->upload(...)` without passing `$sourceType`. The service defaults to `'client'`, so admin-uploaded documents are mislabelled.

**Spec requirement**: FR-008/FR-009 — uploader identity and role must be recorded and displayed accurately.

**Decision**: Pass `'admin'` as the `$sourceType` argument in `Admin\DocumentController::store()`. This is a one-line fix.

---

### Finding 6: DOCX File Type Missing from Validation

**Problem**: All three Form Requests use `mimes:pdf,jpg,jpeg,png`. The spec assumption lists DOCX as an allowed type.

**Decision**: Add `docx` to the `mimes` list in all three Form Requests. Laravel maps the `docx` short name to the correct MIME type (`application/vnd.openxmlformats-officedocument.wordprocessingml.document`) using PHP's `finfo` — actual file content is checked, not just the extension.

**Rationale**: Content-based MIME validation (via Laravel's `mimes` rule) satisfies FR-006 (actual content must match declared type).

---

### Finding 7: Reviewer Assignment Check Missing

**Problem**: `Reviewer\DocumentController::store()` checks that the task belongs to the application but does not verify the reviewer is assigned to that application.

**Spec requirement**: FR-011 — reviewers may only upload to assigned applications.

**Decision**: Load `VisaApplication` with the reviewer check — use the same pattern as `Reviewer\ApplicationController::show()`. The `VisaApplication` model has an `assigned_reviewer_id` (or equivalent) field. Add `abort_if($application->assigned_reviewer_id !== Auth::id(), 403)` in the reviewer upload controller.

**Note**: If reviewer assignment is managed via a many-to-many pivot rather than a single column, the check should query that pivot. This will be confirmed during implementation by reading `VisaApplication` model.

---

### Finding 8: Missing Language Strings for Deletion and Application-Level Uploads

**Problem**: `resources/lang/en/documents.php` and `ar/documents.php` have no strings for delete actions or the application-level upload context.

**Decision**: Add the following keys to both files:
- `delete_success` — confirmation after deletion
- `delete_confirm` — confirmation prompt text
- `delete_error_task_closed` — error when task is closed
- `delete_error_forbidden` — error for unauthorised delete
- `application_documents` — label for the application-level documents section
- `upload_application_level` — label for the task-free upload action

---

### Finding 9: File Serving (Signed URLs)

**Problem**: For local disk, `DocumentService::serve()` uses `Storage::disk($disk)->download(...)` without time-limiting. The constitution (§VII) says files MUST be served through signed, time-limited URLs.

**Decision**: The download route (`GET /documents/{document}/download`) is already protected by the `auth` middleware and `DocumentPolicy::download()`. For local disk, streaming via the authenticated route is equivalent to a signed URL in terms of access control — unauthenticated users cannot reach the URL. S3 uses `temporaryUrl()` (5-minute TTL). This is acceptable. A Laravel signed route could be added for additional defence-in-depth but is not a blocker for this phase.

**Alternatives considered**: Wrapping the download route in `URL::signedRoute()` — deferred to a hardening phase since the auth middleware already protects it.

---

### Finding 10: Soft Delete vs Hard Delete

**Problem**: The `Document` model has no `SoftDeletes` trait. Deletion would be hard (permanent).

**Spec requirement**: FR-017 — files are retained indefinitely unless explicitly deleted by an admin.

**Decision**: Use **hard delete** for admin-initiated deletions (the admin is making a deliberate permanent removal). For client self-deletions, also hard delete (the file is wrong/duplicate; there is no audit need to retain it). This is simpler than soft deletes and consistent with the spec's intent. Audit logging in the `AuditLogService` provides the deletion record.

**Rationale**: The spec does not require a recycle bin or recovery. Soft deletes would add complexity without a documented benefit.

---

## Summary of Required Changes

| # | Change | Scope | Type |
|---|--------|-------|------|
| 1 | Make `application_task_id` nullable | Migration + Service + FormRequests + Controllers | Gap |
| 2 | Add document delete (client own + admin any) | Policy + Service + Controllers + Routes + Permissions | Gap |
| 3 | Enforce 10-doc cap per task and per app-level | Service | Gap |
| 4 | Block uploads to closed tasks (client) | Client Controller | Gap |
| 5 | Fix admin source_type to 'admin' | Admin Controller | Bug |
| 6 | Add DOCX to allowed types | All FormRequests | Gap |
| 7 | Enforce reviewer assignment on upload | Reviewer Controller | Gap |
| 8 | Add missing language strings | en/ar documents.php | Gap |
| 9 | Add new permissions (delete-own, delete) | Migration/Seeder | Gap |
