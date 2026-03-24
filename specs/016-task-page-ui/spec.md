# Feature Specification: Task Page UI

**Feature Branch**: `016-task-page-ui`
**Created**: 2026-03-23
**Status**: Draft
**Input**: User description: "Phase 4 of UPDATE005.md — Task Page: each application task has its own dedicated page. The route already exists. The page must render the task title and description, then render a type-specific UI: for question tasks show the questions and a form to submit answers; for payment tasks show payment instructions and a receipt upload form; for info tasks show the content only with no input. Client can view previously submitted answers on question tasks. The existing backend endpoints for answer submission and receipt upload are already implemented."

## Clarifications

### Session 2026-03-23

- Q: When a question task is approved, should previously submitted answers still be visible to the client on the read-only page? → A: Yes — approved task pages show the submitted answers in read-only mode so the client can see what was recorded.
- Q: For payment tasks, should a previously uploaded receipt be displayed (with its filename) when the client revisits the page? → A: Yes — if a receipt exists and the task is still in progress, display the current receipt alongside the option to replace it.
- Q: Should a client be able to navigate directly to a locked (pending) task page? → A: No — a locked task redirects the client to the dashboard. Only in-progress, approved, and rejected tasks are accessible.
- Q: When a client submits answers on a rejected question task, should the task status automatically reopen, or must a reviewer reopen first? → A: Auto-reopen — submitting answers on a rejected question task automatically changes status to `in_progress`, consistent with how payment receipt upload already behaves.
- Q: After a successful answer submission or receipt upload, where does the client land? → A: Stay on the task page — a success flash message is shown and the client remains on the current task page.
- Q: Should the existing receipt filename on a payment task page be a clickable download link or plain text only? → A: Clickable download link — the filename links to the existing document download route so the client can verify the correct file was uploaded.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Client Submits Answers on a Question Task Page (Priority: P1)

A client opens their active question task. The page displays the task title, description, and a list of questions with text input fields. The client fills in the answers and submits the form. A success message confirms the answers were saved. If the client returns to the page, their previously submitted answers are pre-populated in the form.

**Why this priority**: Question tasks are the primary data-collection mechanism. Without a rendered form, clients have no way to interact with question tasks — the entire workflow is blocked.

**Independent Test**: Can be fully tested by navigating to a question task page as a client, verifying questions are displayed, submitting answers, and returning to confirm answers are pre-populated.

**Acceptance Scenarios**:

1. **Given** a client has an active question task with questions defined, **When** the client opens the task page, **Then** all questions are displayed as labelled input fields with a submit button.
2. **Given** a client fills in answers and submits the form, **When** the submission succeeds, **Then** a success message is shown and the page remains on the task.
3. **Given** a client has already submitted answers and the task is still in progress, **When** the client revisits the task page, **Then** previously submitted answers are pre-populated in the input fields.
4. **Given** a question task has no questions defined, **When** the client opens the task page, **Then** the task description is shown with a message indicating no questions are required — awaiting reviewer action.
5. **Given** a question task is approved, **When** the client opens the task page, **Then** submitted answers are displayed in read-only format with no form or submit button.

---

### User Story 2 — Client Uploads a Receipt on a Payment Task Page (Priority: P1)

A client opens their active payment task. The page displays the payment instructions from the task description and a file upload control. The client selects their receipt file and submits it. A success message confirms the upload. If a receipt was already uploaded, it is shown alongside the option to replace it.

**Why this priority**: Payment tasks are financially critical. Without a receipt upload interface, clients cannot prove payment and the workflow cannot advance past payment steps.

**Independent Test**: Can be fully tested by navigating to a payment task page, verifying the upload control is present, uploading a file, and confirming the receipt filename appears on the page.

**Acceptance Scenarios**:

1. **Given** a client has an active payment task, **When** the client opens the task page, **Then** payment instructions and a file upload control are displayed.
2. **Given** a client selects and submits a valid receipt file, **When** the upload succeeds, **Then** a success message is shown and the uploaded filename is displayed on the page.
3. **Given** a client has already uploaded a receipt and the task is still in progress, **When** the client opens the task page, **Then** the existing receipt filename is shown alongside the upload control to indicate it will be replaced.
4. **Given** a client submits an invalid file (wrong type or oversized), **When** the submission fails validation, **Then** a descriptive error message is shown and no file is stored.
5. **Given** a payment task is approved, **When** the client opens the task page, **Then** the uploaded receipt filename is displayed in read-only format with no upload control.

---

### User Story 3 — Client Views an Info Task Page (Priority: P2)

A client opens their active info task. The page displays only the task title and instructional content. No form, upload control, or submit button is shown. The client reads the information and returns to the dashboard.

**Why this priority**: Info tasks deliver guidance content only. They add lower immediate value than question and payment tasks but are required for a complete workflow.

**Independent Test**: Can be fully tested by navigating to an info task page and confirming no form elements are rendered — only the title and content.

**Acceptance Scenarios**:

1. **Given** a client has an active info task, **When** the client opens the task page, **Then** the task title and description are displayed with no form, upload control, or submit button.
2. **Given** an info task is approved, **When** the client opens the task page, **Then** the content is displayed with a visual indicator that the task is complete and still no input controls.

---

### User Story 4 — Client Resubmits After Rejection (Priority: P2)

A client returns to a task that a reviewer has rejected. The rejection reason is shown prominently. The appropriate submission form (answers for question, upload for payment) is re-enabled so the client can resubmit corrected content.

**Why this priority**: Without visible rejection feedback and a re-enabled form, clients are stuck with no path forward after a reviewer rejects their submission.

**Independent Test**: Can be fully tested by setting a task to rejected with a reason, navigating to the page, and confirming the rejection reason is visible and the form is active.

**Acceptance Scenarios**:

1. **Given** a question task is rejected with a reason, **When** the client opens the task page, **Then** the rejection reason is displayed prominently and the answer form is enabled with previous answers pre-populated.
2. **Given** a client submits answers on a rejected question task, **When** the submission succeeds, **Then** the task status changes to `in_progress` automatically and a success message is shown.
3. **Given** a payment task is rejected, **When** the client opens the task page, **Then** the rejection reason is visible and the receipt upload control is re-enabled.

---

### Edge Cases

- What if a client navigates directly to the URL of a pending (locked) task? The system must redirect to the dashboard — locked task content must not be displayed.
- What if a question task has no questions defined at the blueprint level? The page must handle this gracefully, showing description content and a message that no input is required.
- What if the client submits the answer form with required fields left empty? Validation errors must be shown without losing any answers that were entered.
- What if a client submits answers for a task that was approved while they had the form open (race condition)? The system must reject the submission with an appropriate message.
- What if a receipt upload fails mid-stream? The previously stored receipt must remain intact and still be displayed.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The task page MUST display the task title, step number, and description for all task types and all statuses.
- **FR-002**: The task page MUST display a status badge reflecting the current task state.
- **FR-003**: The task page MUST display the reviewer's note when one is present.
- **FR-004**: The task page MUST display the rejection reason prominently when the task status is rejected.
- **FR-005**: For question tasks in `in_progress` or `rejected` status, the page MUST display each question with a labelled input field and a submit button.
- **FR-005a**: Submitting answers on a `rejected` question task MUST automatically transition the task status to `in_progress` — no reviewer action is required to re-enable submission.
- **FR-006**: For question tasks, previously submitted answers MUST be pre-populated in the form when the client revisits an in-progress or rejected task.
- **FR-007**: For question tasks in `approved` status, the page MUST display submitted answers in read-only format with no form or submit button.
- **FR-008**: For question tasks with no questions defined, the page MUST display a message indicating no input is required.
- **FR-009**: For payment tasks in `in_progress` or `rejected` status, the page MUST display the task description and a file upload control.
- **FR-010**: For payment tasks, if a receipt already exists, the filename MUST be shown as a clickable download link alongside the upload control, indicating it will be replaced on submission.
- **FR-011**: For payment tasks in `approved` status, the uploaded receipt filename MUST be displayed in read-only format with no upload control.
- **FR-012**: For info tasks, the page MUST display only the task title and description — no form, upload control, or submit button for any task status.
- **FR-013**: A client accessing a `pending` task MUST be redirected to the client dashboard.
- **FR-014**: The task page MUST include a back link to the client dashboard.

### Key Entities

- **ApplicationTask**: The client's specific task instance. Carries type (`question`, `payment`, `info`), status, reviewer note, rejection reason, and position. The source of truth for what the page renders.
- **TaskQuestion**: A question defined at the blueprint level. Carries a prompt and a required flag. Displayed as form fields on question task pages.
- **TaskAnswer**: The client's stored response to a question. Used to pre-populate form fields on revisit and displayed in read-only mode after approval.
- **PaymentReceipt**: The uploaded proof-of-payment file linked to a payment task. Surfaced on the page as a clickable download link with an option to replace it while the task is open.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Clients can open a question task page, read all questions, submit answers, and receive confirmation — all within a single page interaction.
- **SC-002**: 100% of previously submitted answers are visible and pre-populated when a client returns to an in-progress or rejected question task.
- **SC-003**: Clients can upload a payment receipt from the payment task page in a single form submission with no additional navigation.
- **SC-004**: Info task pages render with zero form elements, upload controls, or submit buttons visible under any task status.
- **SC-005**: Approved task pages display all previously submitted data in read-only format with zero editable fields or submission controls.
- **SC-006**: Rejected task pages display the rejection reason and re-enable the appropriate submission form in 100% of cases.
- **SC-007**: Navigating to a pending task URL redirects the client to the dashboard in 100% of cases — locked task content is never shown.

---

## Assumptions

- The route (`GET /client/applications/{application}/tasks/{task}`), the `TaskController::show()` action, and the backend POST endpoints for answer submission and receipt upload are already in place from Phase 3. Phase 4 scope is the view layer and any controller eager-loading changes required to support it.
- Payment instructions (amount, method, reference details) are stored in the task's `description` field. No separate structured payment-instruction fields are added in this phase.
- The "replace receipt" experience shows the current filename and the upload control on the same page — no separate confirmation step before replacement.
- Required vs. optional questions are already defined on each `TaskQuestion`. The form enforces required fields via standard validation. Visual distinction between required and optional questions (e.g., asterisk) is a polish detail left to implementation discretion.
- Existing Blade layout components, status badge styling, and flash message conventions from earlier phases are reused — no new design system components are introduced.
- A `workflow_task_id` foreign key must be added to `application_tasks` (as a nullable column) so the controller can load the questions associated with each question task. This migration is in scope for Phase 4.
- The `upload` task type from the legacy view stub is out of scope for Phase 4 — only `question`, `payment`, and `info` types are addressed.
