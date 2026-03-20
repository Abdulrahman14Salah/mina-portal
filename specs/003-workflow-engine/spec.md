# Feature Specification: Workflow Engine

**Feature Branch**: `003-workflow-engine`
**Created**: 2026-03-19
**Status**: Draft
**Input**: Phase 3 — Workflow System (CORE SYSTEM) from PLAN.md

## Clarifications

### Session 2026-03-19

- Q: Can certain workflow steps require explicit client action, or are all step advancements exclusively reviewer-driven in Phase 3? → A: Reviewer-only in Phase 3. Client Tasks tab is read-only. Client-actionable steps deferred to Phase 4+.
- Q: When Phase 3 is deployed, what happens to applications submitted before the workflow engine existed? → A: No retroactive task creation. Existing applications retain their current status unchanged. A documented artisan seeder/command is provided for operators to manually seed tasks onto existing applications if needed.
- Q: On the reviewer's workflow view, which applications should be visible? → A: Active applications only — those with status `pending_review` or `in_progress`. Approved and rejected applications are excluded from this view.
- Q: Where does the reviewer's workflow view live in the navigation? → A: Extend the existing `/reviewer/dashboard` stub into a tabbed layout mirroring the client dashboard pattern (`/reviewer/dashboard/{tab?}`). The "Applications" tab is the default and shows the active workflow queue.

## Context

Every visa application follows a defined sequence of stages before a final decision is made. Phase 3 introduces the **Workflow Engine**: a database-driven system that assigns an ordered list of processing steps to each application, tracks progress through those steps, updates the application's status automatically as steps are completed, and surfaces the current stage to both clients and reviewers.

This is the operational heart of the portal. Without it, applications sit in a permanent "Pending Review" state with no mechanism for advancement. Phase 4 (document uploads) and Phase 5 (payments) attach to workflow steps created here.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Reviewer Advances Application Through Workflow Steps (Priority: P1)

A reviewer logs into the portal and sees a list of applications assigned to their queue. They open an application, view its current step, and mark it complete (or rejected) to advance the application to the next step. When the final step is completed, the application's status changes to "Approved" or "Rejected".

**Why this priority**: This is the minimum viable workflow. Nothing moves in the portal without this. All other stories depend on steps being advanceable.

**Independent Test**: Seed one application in `pending_review`. Log in as a reviewer. Open the application. Mark Step 1 complete → application advances to Step 2. Mark each subsequent step complete through to the final step → application status becomes `approved`. Verify status at each transition.

**Acceptance Scenarios**:

1. **Given** an application is in `pending_review`, **When** the reviewer marks the current step as complete, **Then** the application advances to the next step and status changes to `in_progress`.
2. **Given** the reviewer is on the final step, **When** they mark it approved, **Then** the application status becomes `approved` and no further steps are available.
3. **Given** the reviewer is on any step, **When** they mark it as rejected, **Then** the application status becomes `rejected` and no further steps are available.
4. **Given** a reviewer opens an application, **When** they view the workflow, **Then** they see all steps in order with each step's status (pending / in progress / completed / rejected).
5. **Given** a reviewer marks a step complete, **When** an audit event is recorded, **Then** the audit log contains the step name, reviewer identity, and timestamp.

---

### User Story 2 — Client Tracks Their Application Progress (Priority: P2)

A client logs into their dashboard and visits the Tasks tab. They see a list of the steps in their application workflow, which step is currently active, which are complete, and which are yet to come. They can see a human-readable description of what each step means and what is happening with their application at this point.

**Why this priority**: Transparency is core to the client experience. Clients should never need to call or email to find out where their application stands.

**Independent Test**: Create an application with all workflow steps instantiated. Advance two steps as a reviewer. Log in as the client. Visit the Tasks tab → see 6 steps total; first 2 marked complete, third marked "In Progress", remainder marked "Pending". Verify no reviewer-only controls are visible.

**Acceptance Scenarios**:

1. **Given** a client is on their dashboard Tasks tab, **When** the page loads, **Then** they see all workflow steps for their application in order, each labelled with a status (Pending / In Progress / Completed / Rejected).
2. **Given** a step is currently in progress, **When** the client views it, **Then** it is visually highlighted as the active step.
3. **Given** a step is completed, **When** the client views it, **Then** the completion is clearly indicated (e.g., checkmark, "Completed" label with date).
4. **Given** a client views the Tasks tab, **When** the application is in `pending_review` (no steps started), **Then** the first step is shown as the active step.
5. **Given** a client views the Tasks tab, **When** the application is `approved` or `rejected`, **Then** all steps reflect their final state and a clear outcome message is shown.

---

### User Story 3 — Workflow Steps Are Configurable Per Visa Type Without Code Changes (Priority: P3)

Each visa type has its own default set of ordered workflow steps defined in the database. When a new application is submitted, the system automatically creates a personalised copy of the applicable visa type's step template for that application. Adding a new step or reordering existing steps for a visa type takes effect for all future applications without a code deployment.

**Why this priority**: This is the constitution requirement for database-driven workflows. Without it, every workflow change requires a developer. With it, operations staff (Phase 6 admin UI) can manage workflows independently.

**Independent Test**: Seed two visa types with different step counts (e.g., Tourist Visa: 4 steps, Work Permit: 6 steps). Submit one application for each type. Verify Tourist Visa application has 4 steps and Work Permit has 6. Insert a new step row for Tourist Visa in the database without any code change. Submit a new Tourist Visa application → verify it has 5 steps.

**Acceptance Scenarios**:

1. **Given** a new application is submitted, **When** it is created, **Then** the system automatically generates one workflow task record per step in that visa type's step template, in the correct order.
2. **Given** two visa types have different step templates, **When** applications are submitted for each, **Then** each application has the step set matching its visa type.
3. **Given** a new step is added to a visa type's template via direct database insertion, **When** a subsequent application for that visa type is submitted, **Then** the new application includes the new step without any code change.
4. **Given** an existing application has already been created, **When** the step template for its visa type is changed, **Then** the existing application's steps are **not** modified (templates affect future applications only).

---

### Edge Cases

- What happens when a reviewer tries to advance a step out of order (skipping step 3 to go to step 5)? → Not permitted; steps must be completed in sequence.
- What happens when an application has no step template (visa type has zero steps seeded)? → Application is created but the Tasks tab shows a message indicating no workflow has been configured; application status remains `pending_review`.
- What happens if two reviewers try to advance the same step simultaneously? → The second action finds the step already completed and receives an appropriate message; no duplicate state transition occurs.
- What happens if a reviewer marks a step complete but then wants to revert it? → Step reversal is out of scope for Phase 3; it will be addressed in Phase 6 admin controls.
- What if the client navigates to the Tasks tab before any steps have been started? → The first step is shown as "In Progress" (the application is awaiting initial review).

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST maintain a catalogue of workflow step templates, where each template belongs to a visa type and has an ordered position, a name, and an optional description.
- **FR-002**: When a new visa application is submitted, the system MUST automatically create one application task record per step in that visa type's step template, in the correct order. Applications that existed before Phase 3 was deployed are not affected; a separate artisan command (`workflow:seed-tasks {application}`) MUST be provided for operators to manually initialise tasks on pre-existing applications.
- **FR-003**: Each application task MUST have a status: one of `pending`, `in_progress`, `completed`, or `rejected`.
- **FR-004**: At any point, only ONE task per application may have the status `in_progress`; all prior tasks must be `completed` and all subsequent tasks must be `pending`.
- **FR-005**: A reviewer MUST be able to mark the currently `in_progress` task as `completed`, which automatically sets the next task to `in_progress`.
- **FR-006**: A reviewer MUST be able to mark any `in_progress` task as `rejected`, which sets the application status to `rejected` and closes the workflow.
- **FR-007**: When all tasks in an application are `completed`, the application status MUST automatically be set to `approved`.
- **FR-008**: The application's `status` field MUST remain synchronised with the workflow: `in_progress` while tasks are being processed, `awaiting_documents` when a step requires client document submission (Phase 4 integration point), `under_review` when documents are submitted and under review (Phase 4), `approved` when all tasks complete, `rejected` when any task is rejected.
- **FR-009**: Clients MUST be able to view all workflow tasks for their application on the Tasks tab, including each task's name, description, status, and (if completed) the completion date.
- **FR-010**: Clients MUST NOT be able to advance, complete, or reject any workflow task; these actions are reserved for reviewers.
- **FR-011**: Reviewers MUST be able to add an optional note when completing or rejecting a task; this note is stored with the task record.
- **FR-012**: Every task status change MUST be recorded in the audit log with the actor (reviewer), the application reference number, the task name, the new status, and a timestamp.
- **FR-013**: The system MUST seed default workflow step templates for each visa type created in Phase 2 (Tourist Visa, Work Permit, Family Reunification), each with 6 ordered steps.
- **FR-014**: In Phase 3, all step advancements are exclusively reviewer-driven. The client's Tasks tab is read-only — clients may view step status and descriptions but cannot complete, advance, or reject any step. Client-actionable steps (e.g., document submission confirmation) are deferred to Phase 4 and beyond.
- **FR-015**: The reviewer dashboard MUST be accessible at `/reviewer/dashboard/{tab?}`, defaulting to the "Applications" tab. The existing Phase 1 stub at `/reviewer/dashboard` is replaced by this tabbed route. Additional tabs may be added in Phase 7.
- **FR-016**: The "Applications" tab MUST show all applications with status `pending_review` or `in_progress` (active applications only; approved and rejected are excluded). Each row MUST display the application reference number, client name, visa type, current active step name, and submission date. The list MUST be sortable by submission date and current step name.

### Key Entities

- **WorkflowStepTemplate**: A blueprint step belonging to a visa type. Has an order position, name, description, and assignee type (reviewer-driven or client-action). Managed in a database table; changes take effect for future applications immediately.
- **ApplicationTask**: A concrete instance of a WorkflowStepTemplate, attached to a specific visa application. Records the current status (`pending`, `in_progress`, `completed`, `rejected`), a reviewer note, and a completed-at timestamp.
- **VisaApplication** (extended from Phase 2): Status field gains new valid values: `in_progress`, `awaiting_documents`, `under_review`, `approved`, `rejected`. Status transitions are driven exclusively by ApplicationTask state changes, not set manually.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A reviewer can view, advance, and complete all workflow steps for an application in under 2 minutes of total interaction time.
- **SC-002**: Clients can see their current application step and estimated stage within 3 seconds of opening the Tasks tab.
- **SC-003**: Application status is updated instantly when a reviewer completes a step — no manual refresh required.
- **SC-004**: 100% of new applications automatically receive the correct number of workflow tasks matching their visa type's template; zero applications are created without tasks (assuming the visa type has a template seeded).
- **SC-005**: Adding a new workflow step to a visa type's template in the database produces the correct step set in all subsequent applications for that visa type, without a code deployment.
- **SC-006**: Every workflow state transition is traceable in the audit log; no step completion or rejection occurs without a corresponding audit record.

---

## Assumptions

- **Phase 4 integration point**: Step templates may designate a step as "document required", which will trigger the `awaiting_documents` status transition in Phase 4. Phase 3 seeds this flag on the template but the document upload mechanics are out of scope here.
- **Reviewer assignment**: Phase 3 does not implement reviewer assignment per application (Phase 6 admin panel). Any authenticated user with the `reviewer` role can advance any application's steps.
- **6 default steps**: The seeded default template for each visa type contains 6 steps: (1) Application Received, (2) Initial Review, (3) Document Request, (4) Document Review, (5) Assessment, (6) Final Decision. These are configurable via the database.
- **No concurrent multi-step**: Only one step is `in_progress` per application at a time; parallel step tracks are out of scope.
- **Phase 3 UI**: The existing `/reviewer/dashboard` stub from Phase 1 is expanded into a tabbed layout at `/reviewer/dashboard/{tab?}`, mirroring the client dashboard pattern. The default tab is "Applications" and shows the active workflow queue. Additional reviewer tabs (e.g., history, profile) are added in Phase 7. The client Tasks tab is updated from the empty state stub created in Phase 2.
- **No email notifications**: Step advancement notifications are out of scope for Phase 3 (Phase 9 adds the notification system).
- **Retroactive task seeding**: Existing `pending_review` applications from Phase 2 are not automatically given workflow tasks on deploy. Operators may run `php artisan workflow:seed-tasks {application_id}` (or without argument to seed all taskless applications) to backfill tasks manually.
