# Feature Specification: Document Management System

**Feature Branch**: `010-document-management`
**Created**: 2026-03-21
**Status**: Draft
**Input**: User description: "Phase 3 — Document Management System: File Upload System, Role-based Upload Permissions, File Validation & Security, Reviewer Upload Attribution"

## Overview

This feature delivers a secure, role-aware document management system for the visa application portal. Clients upload required documents as part of their application workflow; reviewers upload supporting or decision documents on behalf of cases; admins retain full visibility and control. Every file is traceable to its uploader and purpose within the application lifecycle.

---

## Clarifications

### Session 2026-03-21

- Q: Can a client delete their own uploaded document? → A: Yes, clients can delete their own uploads while the task is open; deletion is locked once the task closes.
- Q: What happens to stored files when an application is closed, rejected, or archived? → A: Files are retained indefinitely regardless of application status. Admins can delete any file at any time.
- Q: Can a client replace (overwrite) a previously uploaded document? → A: Add only — every upload creates a new independent document entry; no overwriting or versioning.
- Q: Is there a maximum number of documents a client can upload per task, and can clients upload documents not tied to any task? → A: Max 10 documents per task. Clients can also upload documents directly to their application (not tied to any task), with a separate cap of 10 application-level documents.
- Q: Can reviewers and admins also upload application-level documents (not tied to any task)? → A: Yes — all roles (Client, Reviewer, Admin) can upload application-level documents subject to their existing access constraints.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Client Uploads a Document (Task-Attached or Application-Level) (Priority: P1)

A client navigating their visa application can upload documents in two ways: attached to a specific task in their workflow (e.g., passport scan required by Step 2), or directly to the application without linking to any task (e.g., a general supporting letter). Both upload paths are available from the client's application view.

**Why this priority**: File upload is the primary action that unlocks workflow progression. Without it, clients cannot advance through the application process and the entire portal has no value.

**Independent Test**: Can be fully tested by creating a client account, uploading a valid file attached to an open task and confirming it appears in the task's document list, then uploading a second file with no task selected and confirming it appears in the application's general document list.

**Acceptance Scenarios**:

1. **Given** a client has an active application task requiring a document, **When** they upload a file linked to that task within allowed types and size limits, **Then** the file is saved, linked to that task, and appears in the task's document list with upload date and filename.
2. **Given** a client wants to upload a supporting document not tied to any task, **When** they upload a file at the application level, **Then** the file is saved, linked to the application (not to any specific task), and appears in the application's general document list.
3. **Given** a client uploads a file, **When** the upload completes successfully, **Then** the client sees a confirmation message and the document count (task-level or application-level) updates immediately.
4. **Given** a client has already uploaded documents, **When** they view the application or task, **Then** they can see all previously uploaded files with their upload timestamps.

---

### User Story 2 - File Validation Rejects Invalid Uploads (Priority: P1)

The system must prevent harmful or unsupported files from entering storage. When a client attempts to upload a file that is too large, is a disallowed type, or fails security checks, the system must reject it with a clear explanation.

**Why this priority**: Security and data integrity are critical. Accepting unchecked files exposes the system and other users to risk.

**Independent Test**: Can be fully tested by attempting to upload files of disallowed types (e.g., `.exe`, `.php`), oversized files, and files with mismatched content types, confirming each is rejected with an explanatory message.

**Acceptance Scenarios**:

1. **Given** a client attempts to upload a file with a disallowed extension, **When** they submit the upload, **Then** the file is rejected and a message explains which file types are allowed.
2. **Given** a client attempts to upload a file exceeding the maximum size, **When** they submit the upload, **Then** the file is rejected and a message states the size limit.
3. **Given** a file's declared type does not match its actual content, **When** the system validates it, **Then** the file is rejected before being stored.

---

### User Story 3 - Reviewer Uploads a Document with Attribution (Priority: P2)

A reviewer handling a client's application needs to attach an internal document (e.g., assessment notes, translated copy) to the application. The upload is recorded under the reviewer's identity, and the document is distinguishable from client-uploaded files.

**Why this priority**: Reviewer attribution enables auditability and ensures the source of every document is traceable, which is required for compliance and accountability.

**Independent Test**: Can be fully tested by having a reviewer upload a document to an application, then viewing the document list and confirming the reviewer's name and role are recorded as the uploader.

**Acceptance Scenarios**:

1. **Given** a reviewer is viewing an application assigned to them, **When** they upload a document, **Then** the file is stored and labelled with the reviewer's name, role, and upload timestamp.
2. **Given** multiple documents exist on an application, **When** an admin views the document list, **Then** each document clearly shows whether it was uploaded by a client or a reviewer, along with the uploader's identity.
3. **Given** a reviewer uploads a document, **When** the client views their application, **Then** reviewer-uploaded documents are visible to the client but marked as uploaded by the review team and cannot be deleted by the client.

---

### User Story 4 - Role-Based Upload Permissions Are Enforced (Priority: P2)

Each role (Client, Reviewer, Admin) has defined permissions over what they can upload, view, and delete. A client must not be able to upload to another client's application; a reviewer must not be able to upload to applications not assigned to them.

**Why this priority**: Unauthorized uploads would corrupt application data and violate privacy. Strict access control is foundational to system trust.

**Independent Test**: Can be fully tested by attempting cross-application uploads as each role and confirming that only permitted actions succeed, while unauthorized attempts are blocked.

**Acceptance Scenarios**:

1. **Given** a client is authenticated, **When** they attempt to upload a document to another client's application, **Then** the upload is rejected with an access-denied response.
2. **Given** a reviewer is assigned to specific applications, **When** they attempt to upload to an unassigned application, **Then** the upload is blocked.
3. **Given** an admin is authenticated, **When** they upload or delete any document on any application, **Then** the action succeeds and is logged with the admin's identity.
4. **Given** a client views their application, **When** they attempt to delete a reviewer-uploaded document, **Then** the delete action is rejected.
5. **Given** a client uploaded a document to an open task, **When** they choose to delete it while the task is still open, **Then** the document is removed from storage and the document list.
6. **Given** a client uploaded a document to a task that has since been closed, **When** they attempt to delete that document, **Then** the delete action is blocked with a message indicating the task is closed.

---

### User Story 5 - Admin Views and Manages All Documents (Priority: P3)

An admin needs a centralised view of all documents across all applications, with the ability to download any file, see who uploaded it, and delete inappropriate or incorrect files.

**Why this priority**: Admin oversight is a governance function. It is valuable but not blocking for primary client and reviewer workflows.

**Independent Test**: Can be fully tested by having an admin navigate to an application's document section, confirming all uploads from clients and reviewers are listed, and successfully downloading and deleting a file.

**Acceptance Scenarios**:

1. **Given** an admin views an application's document section, **When** the page loads, **Then** all documents from both clients and reviewers are listed with uploader identity, upload date, and file type.
2. **Given** an admin selects a document, **When** they initiate a download, **Then** the correct file is served securely without exposing the underlying storage path.
3. **Given** an admin deletes a document, **When** the action is confirmed, **Then** the file is removed from storage and the audit log records the admin's action with timestamp.

---

### Edge Cases

- What happens when a user uploads a file and the connection drops mid-upload? The system detects the incomplete upload, discards any partial data, and prompts the user to retry.
- How does the system handle duplicate filenames uploaded by the same user for the same task? Each upload is stored independently with a unique internal identifier; original filenames are preserved for display only.
- What happens when a reviewer's assignment to an application is revoked after they uploaded documents? Previously uploaded documents remain on the application with their original attribution; the reviewer can no longer add new documents.
- How does the system behave when file storage is unavailable? The upload fails with a user-friendly error; no partial record is created.
- What if a client attempts to upload to a completed or closed task? The upload is blocked with a message indicating the task is no longer accepting documents. The client may still upload application-level documents (not linked to any task) if the application itself is still active.
- What happens when a client reaches the 10-document limit for a task or the application-level cap? The upload is blocked with a message stating the limit has been reached for that context.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST allow authenticated clients to upload files either attached to a specific active task on their application, or directly to their application without linking to any task.
- **FR-001a**: The system MUST enforce a maximum of 10 documents per task (task-attached uploads) and a maximum of 10 documents at the application level (task-free uploads) per application.
- **FR-002**: The system MUST allow authenticated reviewers to upload files — either attached to a specific task or at the application level — on applications assigned to them.
- **FR-003**: The system MUST allow admins to upload, view, download, and delete files on any application, both task-attached and application-level.
- **FR-004**: The system MUST reject files that do not match an approved list of allowed file types.
- **FR-005**: The system MUST reject files that exceed the defined maximum size per upload.
- **FR-006**: The system MUST validate that a file's actual content matches its declared type, rejecting files where the two do not match.
- **FR-007**: The system MUST store uploaded files in a location inaccessible via direct public URL, requiring an authenticated request to access any file.
- **FR-008**: The system MUST record the uploader's identity (name and role) and timestamp for every stored file.
- **FR-009**: The system MUST display uploader identity and upload date on every document listing visible to authorised roles.
- **FR-010**: The system MUST prevent clients from uploading to, downloading, or deleting documents on applications other than their own.
- **FR-011**: The system MUST prevent reviewers from uploading to applications not assigned to them.
- **FR-012**: The system MUST allow clients to delete their own uploaded documents only while the associated task is in an open state; deletion must be blocked once the task is closed. Clients MUST NOT be able to delete documents uploaded by reviewers or admins.
- **FR-013**: The system MUST log all upload, download, and delete actions to the audit log, capturing actor identity, action type, file reference, and timestamp.
- **FR-014**: The system MUST block new uploads to tasks that are in a closed or completed state.
- **FR-017**: The system MUST retain all uploaded files indefinitely regardless of application status (closed, rejected, or archived). Files are only removed when explicitly deleted by an admin.
- **FR-015**: The system MUST serve files through an authenticated download route that verifies the requester's access rights before delivering the file.
- **FR-016**: Reviewer-uploaded documents MUST be visible to the client on their application view, labelled as uploaded by the review team.

### Key Entities

- **Document**: A single uploaded file. Key attributes: unique identifier, original filename, file type, file size, storage reference (opaque to end users), uploader identity (user and role), associated application (always required), associated task (optional — null for application-level uploads), upload timestamp, active/deleted status.
- **Application**: The parent record to which documents are attached. One application may have many documents.
- **ApplicationTask**: A workflow step within an application that may require one or more documents. Its state (open/closed) controls whether new uploads are accepted.
- **Uploader**: The authenticated user who performed the upload (Client, Reviewer, or Admin). Attribution is stored permanently on the document record regardless of subsequent user changes.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Clients can upload a valid document to an active task in under 30 seconds under normal network conditions.
- **SC-002**: 100% of upload attempts that fail type, size, or content validation are rejected, with a clear reason shown to the user.
- **SC-003**: Every document stored in the system has a recorded uploader identity — zero documents exist in the system without attribution.
- **SC-004**: Unauthorised upload and download attempts across role boundaries are blocked 100% of the time with no data exposed.
- **SC-005**: All upload, download, and delete events are captured in the audit log — zero unlogged file actions.
- **SC-006**: No file is directly accessible via a guessable public URL — all file retrieval requires a valid authenticated session.
- **SC-007**: Admins can locate, view metadata for, and download any document across all applications within 3 navigation steps from the admin dashboard.

---

## Assumptions

- Allowed file types are: PDF, JPG, JPEG, PNG, DOCX. This list can be adjusted by configuration without code changes.
- Maximum file size per upload is 10 MB; adjustable per environment.
- Files are stored on a private local disk in development and a private S3-compatible bucket in production, using the existing `FILESYSTEM_DISK` environment variable pattern.
- Content validation is performed by verifying actual MIME type against expected type based on file content, not relying solely on the file extension. Full antivirus integration is out of scope for this phase.
- Documents can be uploaded in two modes: task-attached (linked to a specific application task) or application-level (linked to the application only, with no task). All roles (Client, Reviewer, Admin) can upload in both modes, subject to their existing access constraints. Documents appear in the client's application view, with task-attached documents shown under their respective task and application-level documents shown in a general section.
- Each task can hold a maximum of 10 documents. The application-level (task-free) bucket also has a maximum of 10 documents per application.
- Each upload is an independent record — there is no overwrite or versioning mechanism. Clients who need to correct an upload must delete the incorrect file (while the task is open, for task-attached files) and upload the replacement.
- The audit log referenced in FR-013 uses the existing `AuditLogService` already present in the project.
- Files have no automatic expiry or scheduled deletion. Retention is indefinite until an admin explicitly removes a file. Automated retention policies are out of scope for this phase.
- Reviewer-uploaded documents are read-only for clients (visible but not deletable or replaceable). Clients may only delete their own uploads, and only while the task remains open.
