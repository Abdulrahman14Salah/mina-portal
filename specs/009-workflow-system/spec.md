# Feature Specification: Workflow System (Core System)

**Feature Branch**: `009-workflow-system`
**Created**: 2026-03-21
**Status**: Draft
**Input**: User description: "Phase 2 — Workflow System (CORE SYSTEM) from UPDATE.md"

## Clarifications

### Session 2026-03-21

- Q: What happens when a task step is rejected — can it be re-opened, and who can do it? → A: The reviewer who owns the application can re-open a rejected step. Re-opening resets that specific step to active; all previously completed steps remain complete. The task status returns from "rejected" to "in progress".
- Q: What does "real time" mean for the client dashboard — auto-push, polling, or fresh on navigation? → A: Fresh on navigation — task progress data is always accurate when the client visits or refreshes their dashboard. No auto-push or polling is required.
- Q: What happens when a reviewer tries to advance/reject a step on an application they are not assigned to? → A: Hard block — the server refuses the action with an authorization error regardless of how the request was made. The UI additionally hides advance/reject controls for unassigned applications.
- Q: When all tasks for an application are complete, does the application status change automatically? → A: No automatic trigger — application status is always changed manually by an admin, even when all tasks are marked complete.
- Q: What happens when two requests attempt to act on the same step simultaneously? → A: First request wins — the step state change is atomic; the second concurrent request finds the step is no longer in an active state and receives an error.

## Overview

Phase 2 is the core product capability of the portal. Every visa application moves through a structured set of tasks, and each task progresses through a fixed sequence of up to 6 ordered steps. Progress is tracked and persisted at the step level. Clients can see exactly where their application stands. Reviewers advance or reject steps. Admins monitor all applications in one place. The task definitions themselves are configurable per visa type — no workflow is hardcoded.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Client Views Application Task Progress (Priority: P1)

After submitting an application, a client visits their dashboard and sees a list of tasks assigned to their application. Each task shows which step it is currently on, how many steps remain, and the overall status. The client does not take any action on tasks — they are observers of progress only.

**Why this priority**: Without visible task progress, a client has no feedback after submitting. This is the minimum viable output of the workflow system — a client seeing their tasks and status.

**Independent Test**: Can be fully tested by seeding an application with tasks and navigating to the client dashboard — the task list with step progress must render correctly and deliver a meaningful "application in progress" experience.

**Acceptance Scenarios**:

1. **Given** a client has a submitted application with tasks seeded, **When** they view their dashboard tasks tab, **Then** each task is listed with its name, current step number, total step count, and current status (e.g., pending, in progress, completed, rejected).
2. **Given** a task has not yet been started, **When** the client views it, **Then** it displays as "Pending" with step 0 or step 1 indicated as the starting point.
3. **Given** a task has been advanced through some steps, **When** the client views it, **Then** the current step number and completed steps are accurately reflected.
4. **Given** a task is fully completed (all steps passed), **When** the client views it, **Then** it is clearly marked as completed with a visual distinction from in-progress tasks.
5. **Given** a task step has been rejected, **When** the client views it, **Then** the task shows a rejected status with the rejection reason visible.

---

### User Story 2 - Reviewer Advances or Rejects a Task Step (Priority: P2)

A reviewer opens an assigned application and sees the current task step awaiting action. The reviewer can advance the step (marking it complete and moving to the next) or reject it (returning it with a reason). Steps must be processed in order — a reviewer cannot skip to a later step.

**Why this priority**: The workflow only progresses through reviewer action. Without this, tasks remain permanently pending. This is the engine of the workflow.

**Independent Test**: Can be fully tested by a reviewer navigating to an assigned application, advancing one task step, and confirming the step moves to the next state — delivers a single working cycle of the workflow engine.

**Acceptance Scenarios**:

1. **Given** a reviewer views an application, **When** they look at the task panel, **Then** they see all tasks for that application with each task's current step and available actions.
2. **Given** a task is on step N (not the final step), **When** the reviewer clicks Advance, **Then** step N is marked complete, step N+1 becomes the active step, and the change is reflected immediately.
3. **Given** a task is on its final step, **When** the reviewer advances it, **Then** the entire task is marked as completed and no further step actions are available.
4. **Given** a task step is active, **When** the reviewer clicks Reject and provides a reason, **Then** the step is marked as rejected, the reason is stored, the task status becomes rejected, and a "Re-open" action becomes available in place of Advance/Reject.
5. **Given** a task is in rejected status, **When** the reviewer clicks Re-open, **Then** the rejected step is reset to active, all previously completed steps remain complete, and the task status returns to "in progress".
6. **Given** a reviewer attempts to advance a step that is not the current active step, **When** they submit the action, **Then** the system refuses and displays an error — steps cannot be skipped or processed out of order.
7. **Given** a reviewer advances or rejects a step, **When** the action completes, **Then** the reviewer's view reflects the updated step state immediately (via redirect to the refreshed page). The client dashboard will show the updated state on their next visit or page refresh.

---

### User Story 3 - Tasks Auto-Seeded When Application Is Created (Priority: P3)

When a client submits a new application, the system automatically creates all required tasks and their steps based on the visa type selected. No manual task creation is required from admins or reviewers. The task definitions are driven by the visa type configuration.

**Why this priority**: Without task seeding, there are no tasks for reviewers to act on. This is a prerequisite for all workflow activity, but it is a background process invisible to most users — hence lower priority than the visible progress and review flows.

**Independent Test**: Can be fully tested by submitting an application for a configured visa type and verifying the resulting task list matches the visa type's defined tasks and steps — delivers a correct, populated workflow for a new application.

**Acceptance Scenarios**:

1. **Given** a visa type has 3 tasks configured with defined steps, **When** a client submits an application for that visa type, **Then** exactly 3 tasks are created for that application, each with the correct steps in the correct order.
2. **Given** tasks are seeded for a new application, **When** the reviewer views the application, **Then** all tasks show as "Pending" with step 1 as the starting point.
3. **Given** a visa type has no tasks configured, **When** an application is submitted for that type, **Then** the application is still created successfully and the task list is empty (no error).
4. **Given** tasks are seeded for an application, **When** the task seeding process is repeated for the same application, **Then** no duplicate tasks are created.

---

### User Story 4 - Admin Monitors Task Status Across All Applications (Priority: P4)

An admin views a list of all applications and can see the workflow status of each — how many tasks are complete, in progress, or rejected. The admin can drill into an individual application to see the full task and step breakdown.

**Why this priority**: Operational visibility is important but not blocking for the core workflow to function. Reviewers and clients can operate without admin monitoring.

**Independent Test**: Can be fully tested by navigating to the admin applications list, verifying task status summaries appear per application, and drilling into one application to see full task detail — delivers a working admin monitoring view.

**Acceptance Scenarios**:

1. **Given** an admin views the applications list, **When** the page loads, **Then** each application shows a task status summary (e.g., "2/4 tasks complete").
2. **Given** an admin clicks into a specific application, **When** the detail view loads, **Then** all tasks for that application are listed with their current step, step count, and status.
3. **Given** an admin views a task that has a rejected step, **When** they view the detail, **Then** the rejection reason is visible alongside the rejected step.

---

### Edge Cases

- A reviewer who attempts to advance or reject a step on an application they are not assigned to receives an authorization error — the action is refused at the server level regardless of how the request was submitted. The reviewer UI does not display advance/reject controls for unassigned applications.
- What happens if the visa type's task configuration changes after an application has already been seeded — do existing application tasks remain as-is or update?
- What if a task has only 1 step — does advancing it immediately complete the task?
- When all tasks for an application are completed, the application status does NOT change automatically. An admin must manually update the application status. The admin dashboard may surface applications where all tasks are complete to assist with this decision, but no system action is triggered.
- Concurrent step actions (e.g., two reviewers submitting advance and reject simultaneously for the same step) are resolved by first-write-wins: the first request commits the state change atomically; the second request finds the step is no longer active and receives an error message. No silent data corruption occurs.
- A rejected task step can be re-opened by the reviewer assigned to that application. Re-opening resets the specific rejected step to active; all preceding completed steps are preserved. The task status returns to "in progress".

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST automatically create all tasks and their ordered steps for a new application immediately after submission, based on the visa type's configured task definitions.
- **FR-002**: Tasks MUST be created in the exact sequence defined by the visa type configuration — task order and step order MUST be preserved.
- **FR-003**: Each task MUST progress through its steps in strict sequential order — no step may be marked complete unless all preceding steps are already complete.
- **FR-004**: A reviewer MUST be able to advance the current active step of a task, marking it complete and activating the next step.
- **FR-005**: When the final step of a task is advanced, the system MUST automatically mark the entire task as completed.
- **FR-006**: A reviewer MUST be able to reject the current active step of a task by providing a mandatory rejection reason.
- **FR-007**: When a step is rejected, the system MUST record the rejection reason and mark the task status as rejected.
- **FR-007a**: A reviewer assigned to an application MUST be able to re-open a rejected step. Re-opening MUST reset only the rejected step to active status, preserve all previously completed steps, and return the task status to "in progress". The rejection reason is cleared on re-open.
- **FR-008**: A reviewer MUST NOT be able to advance or reject a step that is not the current active step — actions on non-current steps MUST be refused with an error.
- **FR-008a**: A reviewer MUST NOT be able to advance, reject, or re-open any task step on an application they are not assigned to. The server MUST enforce this authorization check independently of the UI. The reviewer UI MUST NOT display advance/reject/re-open controls for applications the reviewer is not assigned to.
- **FR-009**: Step completion state (including timestamps and rejection reasons) MUST be persisted individually per application, per task, and per step — progress MUST NOT be derived or recalculated.
- **FR-009a**: Step state transitions MUST be atomic — concurrent requests acting on the same step MUST be handled such that only the first request succeeds. The second request MUST receive an error indicating the step is no longer in an actionable state.
- **FR-010**: The client dashboard MUST display each task's name, current step number, total step count, and status accurately whenever the client visits or refreshes their dashboard — data MUST always reflect the latest step state at page load time. No auto-push or background polling is required.
- **FR-011**: When a task step is rejected, the client's view MUST display the rejection reason alongside the task.
- **FR-012**: The reviewer's application view MUST display all tasks for an application with their current step and available advance/reject actions.
- **FR-013**: The admin application list MUST display a task status summary per application (completed tasks vs. total tasks).
- **FR-014**: The admin application detail view MUST display the full task and step breakdown including rejection reasons.
- **FR-015**: Task and step definitions MUST be stored as configurable records per visa type — no task structure or step sequence may be hardcoded.
- **FR-016**: Re-seeding tasks for an application that already has tasks MUST NOT create duplicate task records.
- **FR-017**: All task step transitions (advance, reject) MUST be logged with the acting user, timestamp, and action taken.

### Key Entities

- **Task Definition**: A template describing a task that belongs to a visa type. Key attributes: name, description, order position within the visa type, total number of steps. One visa type has many task definitions.
- **Task Step Definition**: A template for a single step within a task. Key attributes: step number (position), name/label, field structure (if any data is collected). One task definition has many step definitions.
- **Application Task**: A concrete instance of a task definition for a specific application. Key attributes: reference to task definition, reference to application, current step number, overall status (pending / in progress / completed / rejected), timestamps. One application has many application tasks.
- **Application Task Step Progress**: The persisted progress record for a single step within an application task. Key attributes: reference to application task, step number, status (pending / active / complete / rejected), completion timestamp, rejection reason (nullable; cleared on re-open), acting reviewer (nullable). A step may transition: pending → active → complete, or active → rejected → active (re-open).
- **Visa Type**: The container for task definitions. Key attribute relevant here: its associated task definitions drive what tasks get seeded when an application is created.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of new applications have tasks automatically seeded within the same request that creates the application — zero manual task creation required.
- **SC-002**: Task step order is enforced in 100% of cases — no step can be advanced or rejected unless it is the current active step.
- **SC-003**: Task progress data displayed on the client dashboard is always accurate at page load time — clients see the current state on every visit or refresh, with no stale or cached data shown.
- **SC-004**: Rejection reasons are captured and displayed to both clients and admins in 100% of rejection events.
- **SC-005**: All step transitions are logged with acting user and timestamp, providing a complete audit trail for every application workflow.
- **SC-006**: Visa type task configurations can be modified without any code changes — 100% of task structure changes are achievable through data alone.
- **SC-007**: Task status summaries on the admin list view are accurate and up to date, reflecting real step completion counts rather than cached or estimated values.

## Assumptions

- Each task has exactly 6 steps maximum, as described in the UPDATE.md spec ("Task Steps Spec (6 Steps)"). The step count per task may be fewer than 6 depending on the task definition.
- Task definitions and step definitions are managed by admins through the task builder (Phase 5 scope) — for Phase 2, they are assumed to be seeded via the database.
- A rejected task step is re-opened by the reviewer assigned to the application. Re-opening resets only the rejected step to active; all preceding completed steps are preserved. The task status returns to "in progress".
- Reviewers are assigned to applications before they can act on tasks — the assignment mechanism is out of scope for Phase 2.
- The workflow does not send notifications to clients when steps are advanced or rejected — notifications are out of scope for Phase 2 (deferred to Phase 6).
- An application's overall status (e.g., "in progress", "approved") is always changed manually by an admin. Completion of all tasks does not trigger any automatic status change — admin retains full control over when an application is formally concluded.
- Each application has one reviewer assigned to it; multiple concurrent reviewer assignments are not supported in Phase 2.
