# Feature Specification: Task Progression

**Feature Branch**: `017-task-progression`
**Created**: 2026-03-23
**Status**: Draft
**Input**: User description: "Phase 5 — Task Progression: enforce sequential workflow where only one task is active at a time and the next task unlocks only after the current task is approved."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Next Task Unlocks After Approval (Priority: P1)

When a reviewer approves a task, the system automatically activates the next task in the workflow so the client can continue without manual intervention.

**Why this priority**: This is the core progression mechanic. Without it the workflow is a dead end after every task — clients are blocked permanently regardless of reviewer action. All other stories depend on a next task existing in an active state.

**Independent Test**: Create an application with two tasks in sequence. Approve the first task as a reviewer. Verify the second task transitions from locked to active and the client can navigate to it.

**Acceptance Scenarios**:

1. **Given** a client has an active task and a reviewer approves it, **When** the approval is saved, **Then** the immediately following task in the application automatically becomes active for the client.
2. **Given** a task is the last task in one workflow section and a reviewer approves it, **When** the approval is saved, **Then** the first task of the next section automatically becomes active.
3. **Given** a task has already been approved and its successor is already active, **When** the approval action is triggered again, **Then** the successor is not activated a second time and no data is corrupted.

---

### User Story 2 - Locked Tasks Are Inaccessible to Clients (Priority: P2)

A client cannot view, interact with, or submit data for a task that has not yet been unlocked — they may only work on the one currently active task.

**Why this priority**: Without this guard, clients could skip ahead and submit answers or receipts out of order, defeating the purpose of sequential workflow and creating invalid application states.

**Independent Test**: Create an application with multiple tasks, leave all but the first in locked state, and verify that direct URL navigation to a locked task is blocked and that a form submission to a locked task is rejected.

**Acceptance Scenarios**:

1. **Given** a task is locked, **When** a client navigates directly to that task's URL, **Then** they are redirected to their dashboard rather than seeing task content.
2. **Given** a client views their dashboard, **When** the task list is rendered, **Then** locked tasks are visually distinct (disabled, greyed out) and not presented as clickable navigation links.
3. **Given** a client attempts to submit data for a locked task via a direct form POST, **Then** the submission is rejected and no data is stored.

---

### User Story 3 - Final Task Approval Closes the Workflow (Priority: P3)

When the last task in the entire workflow is approved, the application transitions to a completion state so the team and client know all work is done.

**Why this priority**: Without a defined end state the system has no signal to treat an application as fully processed. This is required for operational tracking and for communicating completion to the client.

**Independent Test**: Create an application with a single task. Approve it. Verify the application reaches a completed state and no further activation is attempted.

**Acceptance Scenarios**:

1. **Given** the final task in the workflow is approved, **When** the approval is saved, **Then** no further tasks are activated and the application status reflects that all tasks are complete.
2. **Given** an application whose workflow is complete, **When** a client views their dashboard, **Then** they see a clear indication that their application workflow is finished.

---

### Edge Cases

- What happens when a task is approved and there is no next task (end of workflow)?
- What happens if a task is rejected after its successor was already activated — does the successor revert to locked?
- What if two reviewers attempt to approve the same task at the same moment? → Both requests succeed; the second finds the task already approved and the successor already active. FR-007 idempotency ensures no duplicate activation or data corruption (last write wins).
- What if a task is re-opened (returned to active status after approval) — does the successor task revert?
- What if a task has no successor within its section but a next section exists?
- What if all tasks in the workflow are already approved — is the approval action idempotent?
- What if an application task record exists with no corresponding next task in the ordered sequence?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST automatically activate the next task when the current task is approved by a reviewer. Client answer submission alone does NOT trigger progression — reviewer approval is always required regardless of task type.
- **FR-002**: System MUST determine the next task by ascending position order within a section, then by ascending section position order across sections.
- **FR-003**: System MUST prevent a client from viewing the content of a locked task — navigation to a locked task URL must redirect the client to their dashboard.
- **FR-004**: System MUST prevent a client from submitting data (answers, receipt upload) for a locked task — such submissions must be rejected with an appropriate response.
- **FR-005**: System MUST detect when the approved task is the last in the workflow and not attempt to activate a non-existent next task.
- **FR-006**: System MUST transition the application to `workflow_complete` status when the last task is approved. This is a distinct intermediate state — it does not constitute final admin approval of the application.
- **FR-007**: System MUST NOT activate the next task more than once — if the successor is already active or already approved, no state change occurs.
- **FR-008**: System MUST NOT revert the successor task's status when a previously approved task is re-opened; task re-opening affects only the re-opened task.
- **FR-009**: The client dashboard MUST visually differentiate locked tasks from the active task and from completed tasks.
- **FR-010**: The sequential progression rule MUST apply to all task types equally (question, payment, info) — activation is type-agnostic. Auto-complete for question tasks is out of scope for this phase.

### Key Entities

- **Application Task**: A single step in a client's workflow. Has a position, a status (pending / in_progress / approved / rejected), and belongs to one application. Progression acts on the status of this entity.
- **Workflow Section**: A logical grouping of tasks with its own position. Progression moves across sections after a section's last task is approved.
- **Visa Application**: The parent record. Gains `workflow_complete` status when all tasks are approved — this is an intermediate state signalling that all client tasks are done, pending final admin review and approval.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of task approvals that have a successor task result in that successor becoming active within the same operation — no manual step is required to unlock the next task.
- **SC-002**: 0% of locked-task access attempts succeed — every attempt to view or submit data for a locked task is blocked without exception.
- **SC-003**: Final-task approval correctly closes the workflow in 100% of cases — no application is left in a partially-complete state after all tasks are approved.
- **SC-004**: Re-approving an already-approved task does not change the status of any other task — the operation is idempotent.
- **SC-005**: The dashboard task list correctly reflects workflow state (one active, prior tasks approved or rejected, subsequent tasks locked) immediately after any status change.

## Assumptions

- The first task in a newly created application is already set to active at the time the application is created. This feature covers progression from one task to the next — initial activation is handled by the existing application creation flow.
- Task ordering is determined by an integer position field on each task within its section, and by a position field on each section within the visa type. Lower numbers come first.
- A task can only be approved once it is in the active state. The reviewer approval action is already implemented; this feature adds the progression hook into that action.
- An application has exactly one active task at any given time at steady state.
- Locked tasks are those with status `pending`. All tasks except the first are set to `pending` when the application is created.
- Rejection does not trigger progression — a rejected task remains the client's responsibility and the successor remains locked until the current task is eventually approved.

## Clarifications

### Session 2026-03-23

- Q: Should re-opening a task (reverting it from approved back to active) also revert its successor to locked? → A: No — re-opening affects only the re-opened task itself; the successor's status is unchanged. This avoids cascading state reversals that would confuse clients who have already started work on the successor task.
- Q: For question tasks, does client answer submission alone trigger progression, or must a reviewer always approve first? → A: Reviewer approval is always required for all task types. Auto-complete is out of scope for this phase.
- Q: What status value should the application be set to when all tasks are approved? → A: `workflow_complete` — a new intermediate status distinct from final admin approval. Completing all tasks signals readiness for admin review, not final approval.
- Q: When two reviewers approve the same task concurrently, what is the expected outcome? → A: Last write wins — both requests succeed; the second finds the task already approved and the successor already active; FR-007 idempotency ensures no duplicate activation or data corruption.
