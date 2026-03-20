# Quickstart: Phase 4 — Document Management System

**Purpose**: Manual validation scenarios to run after implementation.

---

## Prerequisites

1. Phase 3 is complete — a client account with an application exists and workflow tasks are seeded.
2. The application's "Document Request" step (position 3) is active (`status = 'in_progress'`) and `is_document_required = true` on that task's template.
3. Run: `php artisan db:seed --class=RolePermissionSeeder` (adds `documents.upload`, `documents.download`, `documents.admin-upload`)
4. A reviewer account exists with the `reviewer` role.
5. An admin account exists with the `admin` role.
6. Dev server running: `php artisan serve`

---

## Scenario 1 — Client Uploads a Document

**Steps**:
1. Log in as the client whose application is at the "Document Request" step
2. Navigate to `/client/dashboard/documents`
3. Verify: an upload form is visible for the "Document Request" task
4. Upload a valid PDF (< 10 MB)
5. Observe the page redirects back to the Documents tab with a success message

**Expected**:
- The document appears in the list with its original filename and upload date under the "Document Request" step
- `documents` table has 1 row for this application
- `visa_applications.status = 'awaiting_documents'`
- `audit_logs` has a row with `event = 'document_uploaded'`

---

## Scenario 2 — Upload Validation Rejections

**Steps**:
1. Try uploading a `.exe` file → should be rejected with an error (type not allowed)
2. Try uploading a PDF that is 11 MB → should be rejected with an error (too large)
3. Try uploading a valid PNG → should succeed

**Expected**:
- Rejected files produce a clear validation error message, no file is stored, no DB record created
- Valid PNG uploads successfully

---

## Scenario 3 — Multiple Documents for the Same Step

**Steps**:
1. While at the "Document Request" step, upload a second document (different filename)

**Expected**:
- Both documents appear in the Documents tab list under the "Document Request" step
- `documents` table has 2 rows for this application + task
- Application status remains `awaiting_documents` (no double-transition)

---

## Scenario 4 — Reviewer Sees and Downloads Client Documents

**Steps**:
1. Log in as a reviewer
2. Navigate to `/reviewer/applications/{application_id}`
3. Scroll to the document list section

**Expected**:
- All documents uploaded in Scenarios 1–3 are listed with filename, upload date, and step name
- Click a "Download" link → file downloads with its original filename
- `audit_logs` has a row with `event = 'document_downloaded'`

---

## Scenario 5 — Cross-Client Access Denied

**Steps**:
1. Note the download URL from Scenario 4 (e.g., `/documents/1/download`)
2. Log out and log in as a **different** client (client B, who has their own application)
3. Visit that URL directly

**Expected**:
- Response: 403 Forbidden
- Client B cannot download client A's documents

---

## Scenario 6 — Unauthenticated Access Denied

**Steps**:
1. Log out completely
2. Visit `/documents/1/download` directly

**Expected**:
- Redirect to `/login`

---

## Scenario 7 — Admin Uploads on Behalf of Client

**Steps**:
1. Log in as admin
2. Navigate to `/admin/applications`
3. Find the application from Scenario 1, click to open its documents page
4. Select the "Document Request" task from the dropdown
5. Upload a file on behalf of the client

**Expected**:
- Document appears in the admin document list with "Uploaded by staff" attribution
- Log in as the client → verify the document appears on their Documents tab with the staff label
- `documents.uploaded_by` = admin user's ID

---

## Scenario 8 — Documents Remain After Application Approved

**Steps**:
1. Advance the application through all 6 tasks via the reviewer panel until `status = 'approved'`
2. Visit the download URL for a previously uploaded document

**Expected**:
- Document is still downloadable
- No 404 or missing file error

---

## Scenario 9 — Upload Form Hidden When Not at Document Step

**Steps**:
1. Create a fresh application (status = `in_progress`, step 1 = "Application Received" which is NOT `is_document_required`)
2. Visit `/client/dashboard/documents`

**Expected**:
- Documents tab shows empty state with no upload form (step 1 is not a document step)
- After reviewer advances to step 3 ("Document Request"), the upload form appears

---

## Database Spot-Checks (via `php artisan tinker`)

```php
// Confirm document record fields
App\Models\Document::where('application_id', 1)->first()->toArray();
// → Check: original_filename, stored_filename, disk, path, mime_type, size, uploaded_by

// Confirm application status transition
App\Models\VisaApplication::find(1)->status;
// → 'awaiting_documents' (after first upload)

// Confirm audit log
DB::table('audit_logs')->where('event', 'document_uploaded')->count();
// → Number of uploads

// Confirm stored file exists on disk
Storage::disk('private')->exists(App\Models\Document::first()->path);
// → true

// Confirm cross-client 403 (from tinker — use actingAs in tests)
// Auth check is best tested via feature tests

// Confirm task relationship
App\Models\ApplicationTask::find(1)->documents()->count();
// → Number of documents uploaded for that task
```
