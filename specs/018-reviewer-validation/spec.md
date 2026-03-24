# Feature Specification: Reviewer Validation

**Feature Branch**: `018-reviewer-validation`
**Created**: 2026-03-23
**Status**: Draft
**Input**: Phase 6 of UPDATE005 — Reviewer controls task approval for payment and question tasks, gating workflow progression.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Reviewer Approves a Payment Task (Priority: P1)

A reviewer opens an assigned application, views a payment task that has a receipt uploaded by the client, reviews the receipt, and marks the task as approved. The system then automatically unlocks the next task in the workflow for the client.

**Why this priority**: Payment task approval is the hard gate in the workflow — no next task can unlock without it. Without this, the entire workflow is blocked.

**Independent Test**: Can be fully tested by creating an application with a payment task in `pending_review` state, logging in as a reviewer, approving it, and verifying the next task becomes `in_progress`.

**Acceptance Scenarios**:

1. **Given** a payment task has status `pending_review` and a receipt is attached, **When** the reviewer clicks "Approve", **Then** the task status changes to `completed` and the next task status changes to `in_progress`.
2. **Given** a payment task has status `pending_review`, **When** the reviewer approves the task, **Then** the client sees the next task as unlocked on their dashboard.
3. **Given** a payment task has no receipt uploaded, **When** the reviewer views the task, **Then** the approval action is still available but the reviewer can see that no receipt has been submitted.

---

### User Story 2 - Reviewer Rejects a Payment Task (Priority: P1)

A reviewer reviews a payment task where the receipt is invalid or unclear, and rejects it with a reason. The task returns to an editable state so the client can re-submit.

**Why this priority**: Rejection is the only way to send a task back for correction. Without this, a bad submission permanently blocks the workflow.

**Independent Test**: Can be tested by creating a payment task in `pending_review`, logging in as a reviewer, rejecting it with a reason, and verifying the task reverts to an editable state and the client sees the rejection reason.

**Acceptance Scenarios**:

1. **Given** a payment task is in `pending_review`, **When** the reviewer submits a rejection with a reason, **Then** the task status changes back to `in_progress` and the rejection reason is stored.
2. **Given** a task has been rejected, **When** the client views the task, **Then** they see the rejection reason and can re-submit their answer or receipt.
3. **Given** the reviewer tries to reject without providing a reason, **When** they submit, **Then** the system requires a rejection reason before accepting the action.

---

### User Story 3 - Reviewer Approves a Question Task (Priority: P2)

A reviewer opens a question task that is configured to require manual approval, reviews the client's submitted answers, and approves the task to advance the workflow.

**Why this priority**: Question task approval extends the reviewer's control to non-payment tasks. Needed for complete workflow coverage but lower priority than payment gating.

**Independent Test**: Can be tested by configuring a question task to require reviewer approval, having a client submit answers, then logging in as a reviewer and approving the task.

**Acceptance Scenarios**:

1. **Given** a question task is configured to require reviewer approval and the client has submitted answers, **When** the reviewer approves the task, **Then** the task status becomes `completed` and the next task unlocks.
2. **Given** a question task is configured to auto-complete, **When** the client submits answers, **Then** the task completes without waiting for reviewer action.
3. **Given** a question task requiring reviewer approval has not yet been submitted by the client, **When** the reviewer views the application, **Then** the approval action is not available.

---

### User Story 4 - Reviewer Views All Tasks for an Application (Priority: P2)

A reviewer opens an assigned application and sees a structured view of all workflow tasks — with their types, statuses, and which tasks are awaiting their action — so they know exactly what needs to be done.

**Why this priority**: Reviewers need a clear queue of pending actions. Without this view, they cannot efficiently manage multiple applications.

**Independent Test**: Can be tested by creating an application with mixed task statuses and verifying the reviewer sees correct status labels and pending actions.

**Acceptance Scenarios**:

1. **Given** a reviewer opens an assigned application, **When** the page loads, **Then** they see all tasks grouped by section with current status (pending, in_progress, pending_review, completed, rejected).
2. **Given** multiple tasks are awaiting approval, **When** the reviewer views the application, **Then** tasks requiring action are visually distinguished from tasks in other states.

---

### Edge Cases

- What happens when a client saves a draft and then navigates away — is the draft preserved on return? Yes, the saved draft content persists and is pre-populated when the client returns to the task.
- What happens when a reviewer tries to approve a task that is not yet submitted by the client (still `in_progress` or saved as draft)? The approval action must not be available.
- What happens when a reviewer tries to approve an already-completed task? The system must block the action and show an appropriate message.
- What happens when the approved task is the final task in the workflow? The system must handle gracefully — no "next task" to unlock.
- What happens when a reviewer is not assigned to the application they are trying to validate? The system must deny access.
- What happens when two reviewers try to approve the same task simultaneously? The last write wins; the task status is updated once to `completed`.
- What happens when a question task's approval mode is changed after some tasks are already completed? Changes only affect tasks not yet completed.

---

## Clarifications

### Session 2026-03-23

- Q: How does a task transition from `in_progress` to `pending_review`? → A: Both — client can save answers/receipts as a draft (task stays `in_progress`) and separately submit for review via an explicit "Submit for Review" action (task moves to `pending_review`).
- Q: Are reviewers notified when a task enters `pending_review`? → A: Both in-system (dashboard badge/count) and email notification.
- Q: Where is the question task approval mode (auto-complete vs require reviewer approval) configured? → A: In the admin task builder, set once per workflow task definition and inherited by all application task instances.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-000**: Clients MUST be able to save answers or receipts as a draft without submitting for review (task remains `in_progress`).
- **FR-000b**: Clients MUST be able to explicitly submit a task for review via a distinct "Submit for Review" action, which transitions the task status from `in_progress` to `pending_review`.
- **FR-001**: Reviewers MUST be able to view all tasks for any application assigned to them, including task type, current status, and submitted content (answers or receipt).
- **FR-002**: Reviewers MUST be able to approve a task that is in `pending_review` status, transitioning it to `completed`.
- **FR-003**: Reviewers MUST be able to reject a task that is in `pending_review` status, providing a mandatory rejection reason, transitioning the task back to `in_progress`.
- **FR-004**: The system MUST automatically unlock the next sequential task (set it to `in_progress`) when a task is approved by a reviewer, provided a next task exists.
- **FR-005**: Payment tasks MUST always require reviewer approval before the next task unlocks — auto-completion is not permitted for payment tasks.
- **FR-006**: Question tasks MUST support a configurable approval mode — either "auto-complete on submission" or "require reviewer approval" — set by an admin in the task builder at the workflow task definition level and inherited by all application task instances.
- **FR-007**: When a question task is configured as auto-complete, the system MUST complete the task and unlock the next task immediately upon the client's submission, without reviewer action.
- **FR-008**: The rejection reason MUST be visible to the client on the task page after a rejection.
- **FR-009**: Reviewers MUST NOT be able to access applications that are not assigned to them.
- **FR-010**: The approval and rejection actions MUST only be available when the task is in `pending_review` status — not for tasks in `pending`, `in_progress`, or `completed` states.
- **FR-011**: The system MUST record who approved or rejected each task and when, for audit purposes.
- **FR-012**: If the approved task is the last task in the workflow, the system MUST NOT attempt to unlock a next task, and MUST mark the workflow as complete if applicable.
- **FR-013**: When a task transitions to `pending_review`, the system MUST send an email notification to the assigned reviewer(s) for that application.
- **FR-014**: The reviewer dashboard MUST display a visible count or indicator of tasks currently awaiting their review across all assigned applications.

### Key Entities

- **Application Task**: Represents a single task instance for a client application. Attributes: title, type (question / payment / info), status (pending / in_progress / pending_review / completed / rejected), submitted content, rejection reason, approval mode (for question tasks), approved/rejected by, approved/rejected at.
- **Reviewer**: A user with reviewer role assigned to one or more applications. Can view tasks, approve, and reject.
- **Workflow Section**: A grouping of tasks within the application workflow. Tasks within a section are sequential.
- **Task Approval Event**: A record of who performed an approval or rejection action, what task was affected, and when it occurred.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of payment tasks require explicit reviewer approval before the next task unlocks — no payment task can auto-complete under any configuration.
- **SC-002**: Reviewers can locate, review, and act on a pending task within a single application view, with no more than 2 clicks from the application list to the approval action.
- **SC-003**: A rejected task is returned to an editable state for the client within the same request cycle — no manual intervention required.
- **SC-004**: The auto-complete configuration for question tasks is respected in 100% of cases — a task configured as auto-complete never waits for reviewer action, and one configured as requiring approval never auto-completes.
- **SC-005**: All approval and rejection events are recorded with actor identity and timestamp, enabling a full audit trail to be reconstructed for any application.
- **SC-006**: Reviewers cannot access or act on tasks belonging to applications not assigned to them — 0 unauthorized approvals permitted.
- **SC-007**: Every transition to `pending_review` results in both an in-system dashboard indicator update and an email delivery to the assigned reviewer — 0 missed notifications for successfully submitted tasks.

---

## Assumptions

- The `pending_review` status already exists or will be introduced as the state a task enters after client submission, before reviewer action.
- Reviewer assignment to applications is handled by the existing reviewer assignment system (not in scope for this feature).
- Info tasks do not require reviewer approval at any point — they are read-only and do not transition through `pending_review`.
- The configurable approval mode for question tasks is set in the admin task builder at the workflow task definition level, not per individual application task instance. No new configuration surface is needed beyond the existing task builder.
- Only one reviewer acts on a task at a time — concurrent approval conflicts are resolved by last-write-wins, which is acceptable given low concurrency expectations.
- Admin users have full access to approve/reject tasks in addition to reviewers (covered by Phase 7 authorization rules, not re-specified here).
