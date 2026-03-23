# Feature Specification: Task Type Behavior

**Feature Branch**: `015-task-type-behavior`
**Created**: 2026-03-23
**Status**: Draft
**Input**: User description: "Phase 3 of UPDATE005.md — Task Types Behavior: support different task behaviors for question, payment, and info task types."

## Clarifications

### Session 2026-03-23

- Q: Are questions for a question task defined at the blueprint level (same for all applications of that visa type) or configured per individual application? → A: Blueprint level — questions are defined on the workflow blueprint task and are identical for every client application of the same visa type.
- Q: When a client uploads a second receipt for a payment task that already has one, should the system replace the existing receipt or reject the new upload? → A: Allow replacement — client can replace the receipt as long as the task is still in progress; once the task is completed the receipt is locked.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Client Submits Answers for a Question Task (Priority: P1)

A client with an active question task is shown a set of questions to answer. They fill in their answers and submit the form. The answers are saved against that task. The task itself remains in progress until a reviewer or admin explicitly marks it as done — submitting answers alone does not complete the task.

**Why this priority**: Question tasks are the primary data-collection mechanism of the workflow and are likely the most common task type. Without this, the workflow cannot gather information from clients.

**Independent Test**: Can be fully tested by creating a question task for an application, submitting answers as a client, and verifying the answers are stored and the task status remains unchanged.

**Acceptance Scenarios**:

1. **Given** a client has an active question task, **When** the client submits answers to the task's questions, **Then** each answer is saved and associated with the correct task and application.

2. **Given** a client has submitted answers for a question task, **When** a reviewer views the task, **Then** the reviewer can see the submitted answers.

3. **Given** a question task has answers submitted, **When** a reviewer marks the task as complete, **Then** the task status changes to complete and the next task becomes active.

4. **Given** a client has already submitted answers for a question task, **When** the client revisits the task (task still in progress), **Then** the previously submitted answers are displayed for review.

---

### User Story 2 - Client Uploads Payment Receipt for a Payment Task (Priority: P1)

A client with an active payment task is instructed to make a payment (e.g., a first installment) and then upload proof of payment. The client uploads their receipt. A reviewer then checks that the payment was made and the receipt is uploaded, and marks the task as complete, unlocking the next task.

**Why this priority**: Payment tasks are financially critical. Without working payment task behavior, the business cannot collect required payments during the visa process.

**Independent Test**: Can be fully tested by creating a payment task, uploading a receipt as a client, having a reviewer verify and mark it complete, and checking the task transitions correctly.

**Acceptance Scenarios**:

1. **Given** a client has an active payment task, **When** the client uploads a payment receipt file, **Then** the receipt is stored and associated with the task.

2. **Given** a payment receipt has been uploaded, **When** a reviewer views the payment task, **Then** the reviewer can see the uploaded receipt and a control to mark the task as complete.

3. **Given** a reviewer has verified payment and receipt, **When** the reviewer marks the payment task as complete, **Then** the task status changes to complete and the next task becomes active.

4. **Given** a client attempts to upload a receipt for a payment task that already has a receipt and the task is still in progress, **When** the upload is submitted, **Then** the new receipt replaces the existing one and the previous file is no longer accessible.

---

### User Story 3 - Client Views an Info Task (Priority: P2)

A client with an active info task is shown a page of instructions or information relevant to their application. No input is required. The client reads the content. The task can be completed immediately by the system or manually by a reviewer — no client action beyond viewing is needed.

**Why this priority**: Info tasks are simpler and lower risk than question or payment tasks. They provide guidance but do not block data collection or payment. Deprioritised relative to P1 tasks but still required for a complete workflow.

**Independent Test**: Can be fully tested by creating an info task, accessing it as a client, and verifying the content is displayed with no input form shown.

**Acceptance Scenarios**:

1. **Given** a client has an active info task, **When** the client opens the task, **Then** the task's title and instructional content are displayed with no form or upload input.

2. **Given** a client views an info task, **When** the task content is displayed, **Then** no submission action is required from the client to progress — the task is either auto-completed or awaits a reviewer/admin action.

---

### Edge Cases

- What if a client submits a question task with no answers (empty submission)? The system must reject the submission and display a validation error.
- What if a client uploads a file that is not a valid document format for a payment receipt? The system must reject the file and display a descriptive error before saving anything.
- What if a reviewer attempts to mark a question or payment task as complete before the client has submitted any answers or receipt? The system must either prevent this or warn the reviewer that no submission exists.
- What if the task is already complete when a client tries to submit answers or upload a receipt again? The system must prevent overwriting a completed task's data.
- What if there are no questions defined for a question task? The system must handle this gracefully without showing a broken empty form.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST display the questions defined on the workflow blueprint task when a client opens a question-type application task. Questions are shared across all applications of the same visa type and are not customisable per client.
- **FR-002**: System MUST allow a client to submit answers to a question task's questions and persist those answers linked to the task and application.
- **FR-003**: Submitting answers to a question task MUST NOT automatically change the task's status — status change requires explicit reviewer or admin action.
- **FR-004**: System MUST allow a reviewer or admin to mark a question task as complete, which transitions the task to the completed state and activates the next task.
- **FR-005**: System MUST display payment instructions (amount, method, reference) on a payment task page.
- **FR-006**: System MUST allow a client to upload a payment receipt file against a payment task. If a receipt already exists and the task is still in progress, the new upload replaces the previous receipt.
- **FR-007**: Uploaded payment receipts MUST be stored securely and accessible only to the client who owns the application and authorized reviewers and admins.
- **FR-008**: System MUST allow a reviewer to view the uploaded receipt and mark the payment task as complete.
- **FR-009**: Marking a payment task as complete MUST transition the task to the completed state and activate the next task.
- **FR-010**: System MUST display the instructional content of an info task when a client opens it, with no input form or upload control shown.
- **FR-011**: System MUST validate that uploaded receipt files are of an allowed type and size before storing them.
- **FR-012**: System MUST prevent a client from submitting answers or uploading receipts for a task that is already in a completed state.
- **FR-013**: System MUST display previously submitted answers when a client revisits an in-progress question task.

### Key Entities

- **TaskAnswer**: A client's answer to a specific question within a question task. Linked to both the task and the application. Stores the question identifier and the answer text.
- **ApplicationTask**: The client-specific task instance (already established in Phase 2). In Phase 3 it gains type-specific behavior: question tasks store answers, payment tasks store receipt references, info tasks have no stored input.
- **PaymentReceipt**: A file uploaded by a client as proof of payment for a payment task. Linked to the task and application. Stored securely outside the public web root.
- **TaskQuestion**: A question definition associated with a question-type workflow blueprint task. Defined once at the blueprint level and shared across all applications of the same visa type. Holds the prompt text and whether a response is required.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of question task submissions that pass validation result in stored answers with zero data loss.
- **SC-002**: 100% of payment receipt uploads that pass file validation result in a stored, accessible receipt linked to the correct task.
- **SC-003**: Reviewers can mark question and payment tasks as complete in 100% of cases where the client has made a valid submission.
- **SC-004**: Info tasks display their content to clients in 100% of cases with zero input forms rendered.
- **SC-005**: Invalid file uploads (wrong type or size) are rejected 100% of the time before any data is stored.
- **SC-006**: Zero completed tasks can be overwritten by a subsequent client submission.

## Assumptions

- A question task may have one or more questions defined at the blueprint level. If no questions are defined, the task page shows the task description only and awaits reviewer action to complete.
- Payment instructions (amount, payment method, reference details) are stored as part of the task description or a structured field on the task — not dynamically fetched from a payment processor in Phase 3. Stripe integration is out of scope for this phase.
- "Completing" a task in the context of Phase 3 means transitioning its status to the terminal approved/completed state and activating the next task, consistent with the existing `WorkflowService::advanceTask()` / `approveTask()` mechanism.
- Info tasks are considered complete either automatically upon being viewed by the client, or manually by a reviewer — Phase 3 will default to reviewer-controlled completion to remain consistent with question and payment tasks. Auto-completion of info tasks is deferred to Phase 6.
- Receipt file storage follows the existing document management system (private disk, served via signed URLs) established in Feature 004.
- Phase 3 does not implement the task page UI (that is Phase 4). Phase 3 defines the data model and behavior rules — the backend logic for storing answers, handling receipt uploads, and enabling reviewer completion actions.
