# Feature Specification: Reviewer Panel

**Feature Branch**: `007-reviewer-panel`
**Created**: 2026-03-20
**Status**: Draft
**Input**: User description: "Reviewer Panel: dashboard for reviewing client applications, client review workflow, and document management for reviewers."

## Clarifications

### Session 2026-03-20

- Q: What is the actual document access boundary for reviewers — per-assignment or shared queue? → A: Any reviewer can access documents on any application in the shared queue (no per-reviewer assignment).
- Q: When a reviewer rejects a task, what status does the application move to? → A: Application status becomes `rejected`; it leaves the active queue immediately.

## Overview

Reviewers are internal staff members responsible for processing visa applications submitted by clients. They need a dedicated workspace to manage their review queue, step through the structured workflow for each application, inspect client-submitted documents, and upload supporting documents on the client's behalf. This panel is the primary daily tool for the review team.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Application Queue Dashboard (Priority: P1)

A reviewer logs in and immediately sees their active workload: all applications that are awaiting review or currently in progress, ordered by submission date (oldest first) so the highest-priority work surfaces at the top.

**Why this priority**: Without a queue, reviewers have no way to discover or manage their work. This is the entry point for all other reviewer activity.

**Independent Test**: Can be fully tested by logging in as a reviewer and verifying that submitted applications appear in the queue sorted oldest-first, and that completed/cancelled applications are excluded.

**Acceptance Scenarios**:

1. **Given** I am logged in as a reviewer, **When** I visit the reviewer dashboard, **Then** I see a list of all applications in "pending review" or "in progress" status, sorted oldest submission first.
2. **Given** there are no active applications, **When** I visit the reviewer dashboard, **Then** I see an empty-state message indicating no applications require attention.
3. **Given** an application is approved or cancelled, **When** I view the queue, **Then** that application does not appear in the list.
4. **Given** the queue has many applications, **When** I view the dashboard, **Then** I can see each application's client name, visa type, submission date, and current status at a glance.

---

### User Story 2 — Application Detail & Task Review Workflow (Priority: P1)

A reviewer opens a specific application and steps through the workflow one task at a time. For each active task they can either advance it (approve) with an optional note or reject it with a required reason. The system shows the full task history so the reviewer understands what has already been done.

**Why this priority**: The task-by-task review workflow is the core function of the reviewer role. All document review and decision-making happens here.

**Independent Test**: Can be fully tested by creating a test application with tasks, logging in as a reviewer, advancing and rejecting tasks, and verifying application status updates correctly.

**Acceptance Scenarios**:

1. **Given** I open an application, **When** the page loads, **Then** I see the client's details, visa type, all tasks in order with their statuses, and a clear indicator of the currently active task.
2. **Given** there is an active task, **When** I click "Advance" with an optional note, **Then** the task is marked complete, the next task becomes active, and I see a success confirmation.
3. **Given** all tasks are complete, **When** I advance the final task, **Then** the application status changes to "Approved" and I am notified.
4. **Given** there is an active task, **When** I click "Reject" and provide a reason, **Then** the task is marked rejected, the application status changes to "Rejected", it is removed from the active queue, and the reason is saved for client visibility.
5. **Given** I reject a task without providing a reason, **When** I submit, **Then** the form fails validation and I am prompted to enter a reason.
6. **Given** I advance a task, **When** the action completes, **Then** I am returned to the same application detail page to continue reviewing.

---

### User Story 3 — Document Review (Priority: P2)

While reviewing an application task, a reviewer can inspect all documents the client has uploaded. Each document can be downloaded. Viewing a document is audit-logged so there is a traceable record of who accessed which files.

**Why this priority**: Document verification is an essential part of most task steps. Reviewers must be able to access client files without leaving the review workflow.

**Independent Test**: Can be fully tested by uploading a document as a client, then logging in as a reviewer, navigating to the application, and verifying the document appears and can be downloaded with the action recorded in the audit log.

**Acceptance Scenarios**:

1. **Given** a client has uploaded documents for an application, **When** I view the application detail, **Then** I see each document listed with its filename, upload date, and uploader name.
2. **Given** I click "Download" on a document, **Then** the file downloads to my device and the access is recorded in the audit log.
3. **Given** a client has not uploaded any documents yet, **When** I view that section, **Then** I see a clear message indicating no documents have been uploaded.
4. **Given** I am logged in as a reviewer, **When** I request a document from an application that does not exist or belongs to a non-active status, **Then** I receive an access-denied response. Any reviewer may access documents on any application visible in the shared queue.

---

### User Story 4 — Reviewer Document Upload (Priority: P2)

A reviewer can upload documents on behalf of a client — for example, official letters, processed forms, or translated documents produced by the review office. These uploads are clearly attributed to the reviewer (not the client) in the document log.

**Why this priority**: Reviewers frequently need to attach official documents to an application record during processing. Without this capability they would need an out-of-band process.

**Independent Test**: Can be fully tested by logging in as a reviewer, uploading a document to an application, and verifying it appears in the document list attributed to the reviewer's account with a "Reviewer Upload" label.

**Acceptance Scenarios**:

1. **Given** I am viewing an application, **When** I upload a document, **Then** the document is saved and appears in the document list labelled with my name and "Reviewer Upload".
2. **Given** I try to upload a file that is not an allowed type, **When** I submit, **Then** the upload is rejected with a clear error message.
3. **Given** I upload a document, **When** the upload succeeds, **Then** the page refreshes to show the newly uploaded file in the list.
4. **Given** a client views their own application dashboard, **When** a reviewer-uploaded document exists, **Then** the client can see the document is present, attributed to the reviewer/office.

---

### Edge Cases

- What happens when a reviewer tries to advance a task that is not currently in "in progress" status?
- What is shown when an application has no tasks defined (workflow template missing)?
- What happens if a file upload fails mid-transfer?
- Rejecting any task sets the application to "Rejected" status and removes it from the queue permanently (resubmission is out of scope for this phase).
- What is shown on the dashboard when the same application somehow has multiple tasks simultaneously in progress?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST display all applications in "pending review" or "in progress" status on the reviewer dashboard, sorted by submission date ascending (oldest first).
- **FR-002**: System MUST allow reviewers to open any application in the queue to view its full detail.
- **FR-003**: System MUST show the complete ordered task list for each application, with clear indication of the currently active task and all previous task statuses.
- **FR-004**: System MUST allow reviewers to advance the currently active task with an optional note.
- **FR-005**: System MUST allow reviewers to reject the currently active task and MUST require a non-empty rejection reason.
- **FR-006**: System MUST automatically update the application status to "Approved" when the final task is advanced.
- **FR-006b**: System MUST automatically update the application status to "Rejected" when any task is rejected; the application MUST no longer appear in the reviewer queue.
- **FR-007**: System MUST display all documents attached to an application (both client-uploaded and reviewer-uploaded) on the application detail page.
- **FR-008**: System MUST allow reviewers to download any document attached to an application.
- **FR-009**: System MUST record an audit log entry each time a reviewer downloads a document, capturing reviewer identity, document identity, and timestamp.
- **FR-010**: System MUST allow reviewers to upload documents to any application in the queue.
- **FR-011**: System MUST label reviewer-uploaded documents distinctly from client-uploaded documents in all document listings.
- **FR-012**: System MUST restrict all reviewer panel routes to users with the reviewer role; non-reviewer roles MUST receive an access-denied response.
- **FR-013**: System MUST show an empty-state message on the dashboard when no applications require attention.
- **FR-014**: System MUST validate uploaded file types and reject disallowed formats with a user-facing error message.

### Key Entities

- **Visa Application**: A client's visa request. Has a status (pending_review, in_progress, approved, rejected). Contains tasks and documents.
- **Application Task**: A single step in the review workflow for an application. Has a position, status (pending, in_progress, completed, rejected), and optional notes/rejection reason.
- **Document**: A file attached to an application. Has a filename, storage path, upload timestamp, uploader identity, and a source type (client upload vs reviewer upload).
- **Audit Log Entry**: A record of a reviewer downloading a document, capturing reviewer identity, document identity, and timestamp.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A reviewer can open the dashboard and identify their highest-priority application in under 10 seconds.
- **SC-002**: A reviewer can complete an advance or reject action on a task in under 30 seconds from opening the application detail page.
- **SC-003**: 100% of document download events are captured in the audit log — zero missed entries.
- **SC-004**: Reviewer-uploaded documents are correctly attributed and labelled in 100% of cases — zero misattributions.
- **SC-005**: All non-reviewer roles are blocked from reviewer panel routes in 100% of access attempts — zero unauthorized access events.
- **SC-006**: The application queue excludes approved and cancelled applications in 100% of renders — zero completed applications appear in the active queue.

## Assumptions

- Reviewer assignment to specific applications is out of scope for this phase; all reviewers see the full shared queue.
- Email notifications to clients upon task rejection are out of scope for this phase (covered in Phase 9 — Notifications).
- The workflow template (task definitions per visa type) is already in place from Phase 3 and Phase 6.
- Allowed document file types follow the same rules established in Phase 4 — Document Management (PDF, JPG, PNG, DOCX).
- There is no pagination requirement on the reviewer queue for this phase; queue size is expected to be manageable.
- Reviewers cannot edit or delete documents; they can only upload and download.
- Once an application is rejected (a task is rejected), it is halted. Resubmission logic is out of scope for this phase.
