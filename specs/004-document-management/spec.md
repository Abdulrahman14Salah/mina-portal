# Feature Specification: Document Management System

**Feature Branch**: `004-document-management`
**Created**: 2026-03-19
**Status**: Draft
**Input**: Phase 4 — File & Document System from PLAN.md

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Client Uploads Required Documents (Priority: P1)

A client visits their dashboard's Documents tab and uploads files for the workflow step that is currently requesting documents. The system stores the file securely, shows the upload listed with its name and date, and reflects the new status on the application.

**Why this priority**: Document upload is the core interaction of Phase 4. Without it, the workflow cannot progress past the "Document Request" step from Phase 3. All other stories depend on files existing in storage.

**Independent Test**: Seed an application at the "Document Request" step. Log in as the client. Upload a valid PDF on the Documents tab. Verify the file is stored, appears in the list with its filename and upload date, and the application status transitions to `awaiting_documents`.

**Acceptance Scenarios**:

1. **Given** a client's application is at the "Document Request" step, **When** the client uploads a supported file (PDF, JPG, JPEG, or PNG ≤ 10 MB), **Then** the file is saved securely, listed with its filename and upload date, and the workflow step reflects documents submitted.
2. **Given** a client attempts to upload a file larger than 10 MB, **When** submission is attempted, **Then** the system rejects the upload with a clear error message and no file is stored.
3. **Given** a client attempts to upload an unsupported file type (e.g., `.exe`, `.zip`), **When** submission is attempted, **Then** the system rejects the upload with a descriptive error and no file is stored.
4. **Given** a client has already uploaded a document for a step, **When** they upload another file for the same step, **Then** it is appended to the existing list (multiple files per step are supported).
5. **Given** a client's application is not at a document-required step, **When** they visit the Documents tab, **Then** they see a read-only list of any previously uploaded documents with no active upload form.

---

### User Story 2 — Reviewer Downloads and Reviews Client Documents (Priority: P2)

A reviewer opens an application at the "Document Review" step and sees all documents the client has uploaded, grouped by step. The reviewer can download any file to inspect it, then advance or reject the step as usual via the Phase 3 workflow controls.

**Why this priority**: Reviewers must access uploaded files to evaluate them. Without download access, the document upload delivers no value to the review process.

**Independent Test**: Upload a document as a client. Log in as a reviewer. Open the application detail page. Verify all uploaded documents are listed with filename, upload date, and step name. Download a file and confirm it opens correctly. Confirm a client user cannot access the document download URL for another client's application.

**Acceptance Scenarios**:

1. **Given** a client has uploaded documents, **When** a reviewer opens that application's detail page, **Then** all uploaded files are listed with their filename, upload date, and the step they belong to.
2. **Given** a reviewer clicks a document download link, **When** the request is processed, **Then** the file is served only to that reviewer; an unauthenticated or unauthorized user requesting the same URL receives an access denied response.
3. **Given** a user who is not a reviewer or admin requests a document download URL directly, **When** the request is made, **Then** they receive a 403 Forbidden response.

---

### User Story 3 — Admin Uploads Documents on Behalf of a Client (Priority: P3)

An admin visits an application's management page and uploads a document (e.g., a corrected form or translated certificate) on the client's behalf. The upload appears in the application's document list attributed to staff.

**Why this priority**: Operational staff occasionally need to inject documents into an application without client action. This unblocks edge cases but is not required for the core document flow.

**Independent Test**: Log in as admin. Navigate to the application. Upload a file on behalf of the client. Log in as the client and verify the file appears on their Documents tab with a "Uploaded by staff" attribution.

**Acceptance Scenarios**:

1. **Given** an admin is viewing an application, **When** the admin uploads a file, **Then** the file appears in the application's document list attributed to staff (e.g., "Uploaded by staff").
2. **Given** an admin uploads a file on behalf of a client, **When** the client views their Documents tab, **Then** the document is visible with the staff attribution label.
3. **Given** an admin uploads a file, **When** file validation runs, **Then** the same 10 MB limit and allowed-type restrictions apply as for client uploads.

---

### User Story 4 — Secure Access Control for All Documents (Priority: P1)

Every document interaction — upload, listing, download — is gated so that only the owning client and authorized staff (reviewers, admins) can interact with the files. No document URL is guessable or publicly accessible.

**Why this priority**: Access control is co-P1 with upload because a document system for personal visa data with inadequate access control is a compliance and privacy risk. Security cannot be layered on after the fact.

**Independent Test**: Upload a document as client A. Attempt to download it while logged in as client B. Verify 403 is returned. Attempt to access the URL unauthenticated. Verify redirect to login.

**Acceptance Scenarios**:

1. **Given** a document belongs to application X, **When** a different authenticated client (not the owner of application X) requests the document URL, **Then** the system returns 403.
2. **Given** an unauthenticated visitor has a document download URL, **When** they visit it, **Then** they are redirected to the login page.
3. **Given** a reviewer requests a document for any application, **When** the request is made, **Then** the file is served without error.
4. **Given** an admin requests any document URL, **When** the request is made, **Then** the file is served without error.

---

### Edge Cases

- What happens when a client uploads the same filename twice for the same step? The system stores both as separate uploads; filenames in storage are replaced with unique generated names, so collisions are impossible.
- What happens if storage is unavailable during upload? The upload fails gracefully with a user-facing error; no partial database record is saved.
- What happens when a reviewer tries to download a document after the application is approved or rejected? Download remains accessible — completed applications retain their full document history for audit purposes.
- What happens if a file is deleted from storage but the database record still exists? A graceful error page is shown rather than a PHP exception.
- What is the maximum number of files per application? No hard limit is enforced in Phase 4; an admin-configurable limit is deferred to Phase 6.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Clients MUST be able to upload one or more files per workflow step from their Documents tab, but only when the active step requires documents.
- **FR-002**: The system MUST accept PDF, JPG, JPEG, and PNG file types only; all other types MUST be rejected with a clear error message before storage.
- **FR-003**: The system MUST enforce a maximum file size of 10 MB per upload; files exceeding this limit MUST be rejected before any storage write occurs.
- **FR-004**: Uploaded files MUST be stored under a path scoped to the application (e.g., `documents/{application_id}/`) and MUST NOT be stored in a publicly accessible location.
- **FR-005**: Uploaded files MUST have their original filename replaced with a randomly generated unique name on disk to prevent path-guessing and filename collisions.
- **FR-006**: The system MUST record each upload as a document record containing: application reference, `application_task_id` (the specific per-application task the document belongs to), uploader identity, original filename, stored filename, file size, MIME type, and upload timestamp.
- **FR-007**: Reviewers and admins MUST be able to view the full list of uploaded documents for any application, grouped by workflow step.
- **FR-008**: Reviewers and admins MUST be able to download any document via a secure, authorized route that validates their permission before serving the file.
- **FR-009**: Clients MUST only be able to view and download documents belonging to their own application; any cross-application access attempt MUST be denied with 403.
- **FR-010**: Admins MUST be able to upload documents on behalf of a client application; such uploads MUST be recorded with the admin as the uploader and displayed with a "Uploaded by staff" label.
- **FR-011**: When a client uploads the first document on a document-required step, the application status MUST transition to `awaiting_documents` if it was previously `in_progress`.
- **FR-012**: The system MUST display a clear empty state on the Documents tab when no documents have been uploaded for the application.
- **FR-013**: All document upload and download access events MUST be recorded in the audit log with: actor, action type, document ID, and application reference number.
- **FR-014**: The storage backend (local disk or cloud object storage) MUST be selectable via environment configuration without code changes; local disk is the default for development.
- **FR-015**: Documents MUST remain accessible for download after an application reaches `approved` or `rejected` status; no automatic deletion occurs on status change.

### Key Entities

- **Document**: Represents a single uploaded file. Belongs to a `VisaApplication` and references the `ApplicationTask` it was uploaded against (FK: `application_task_id` — the per-application step instance, not the blueprint template). Records: uploader user ID, original filename (for display), stored filename (on disk), storage disk name, MIME type, file size in bytes, upload timestamp. Supports multiple documents per application task.
- **VisaApplication** (extended): Gains an active `awaiting_documents` status value (set on first document upload) and a `documents` relationship. The `under_review` status is a Phase 7 concern and is NOT set in Phase 4.
- **WorkflowStepTemplate** (extended): The `is_document_required` boolean flag (seeded in Phase 3) becomes active in Phase 4 — steps with this flag set to `true` show the upload form on the client's Documents tab.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A client can upload a document and see it listed on their Documents tab within 5 seconds of submission on a standard broadband connection.
- **SC-002**: A reviewer can download any client document from the application detail page without navigating away from the reviewer interface.
- **SC-003**: 100% of document download URLs return 403 or redirect to login when accessed by an unauthorized or unauthenticated user.
- **SC-004**: All accepted file types (PDF, JPG, JPEG, PNG) upload successfully; all rejected types produce a clear error with no file written to storage.
- **SC-005**: Admin staff can upload a document on behalf of a client and the upload appears correctly attributed in the document list within 10 seconds.
- **SC-006**: A client can upload up to 20 files across their application's workflow steps without errors or data loss.

---

## Clarifications

### Session 2026-03-19

- Q: Should the Document entity reference `application_tasks.id` (per-application step instance) or `workflow_step_templates.id` (the blueprint)? → A: `application_task_id` — FK to the per-application task instance.
- Q: Which status is set when a client uploads their first document, and when does `under_review` get set? → A: First upload sets `awaiting_documents`; `under_review` is deferred to Phase 7 (reviewer panel action).

---

## Assumptions

- Storage defaults to local disk (`storage/app/private/documents/`); switching to S3-compatible cloud storage requires only an environment variable change — no additional code paths.
- The six workflow steps from Phase 3 are seeded; steps 3 ("Document Request") and 4 ("Document Review") are assumed to have `is_document_required = true`, making them the active upload steps.
- The Phase 3 workflow engine is fully operational and the `awaiting_documents` status value is already defined in the Phase 3 data model as a Phase 4 integration point. The `under_review` status is deferred to Phase 7 (reviewer panel) — it is NOT set by any Phase 4 action.
- Virus/malware scanning of uploaded files is out of scope for Phase 4.
- Client-initiated document deletion is out of scope for Phase 4; only admins may remove documents (via admin UI in Phase 6 or direct action).
- In-browser document preview is out of scope for Phase 4 — download-only access.
- Email notifications triggered by document uploads are out of scope for Phase 4 (covered by Phase 9 Notification System).
- The reviewer's document access applies to all applications in the queue — there is no per-reviewer application assignment in Phase 4 (deferred to Phase 6).
