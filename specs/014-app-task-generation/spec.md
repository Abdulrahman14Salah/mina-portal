# Feature Specification: Application Task Generation

**Feature Branch**: `014-app-task-generation`
**Created**: 2026-03-22
**Status**: Draft
**Input**: User description: "Phase 2 of UPDATE005.md — Application Task Generation"

## Clarifications

### Session 2026-03-22

- Q: Should Phase 2 rename the `name` column to `title` on application_tasks (as UPDATE005.md calls it "title"), or keep `name` as the canonical field? → A: Keep `name`; "title" in UPDATE005.md is a display label synonym only — no rename migration needed.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Tasks Generated on Application Submission (Priority: P1)

When a client submits a visa application, the system automatically creates a personal set of tasks for that application by copying the predefined workflow blueprint for the chosen visa type. Each copied task carries forward the name (referred to as "title" in UPDATE005.md — the existing `name` field is kept, no rename migration needed), type, and position, giving the client a clear checklist to follow from day one.

**Why this priority**: Without task generation, the entire workflow system has nothing to act on. This is the foundational step that makes every subsequent phase possible.

**Independent Test**: Can be fully tested by creating an application for a visa type that has a workflow blueprint and verifying that the correct number of application tasks exist with the correct names, types, and positions.

**Acceptance Scenarios**:

1. **Given** a visa type has a workflow blueprint with 8 tasks across 4 sections, **When** a client submits an application for that visa type, **Then** 8 application tasks are created for that application, each matching the blueprint task's name, type, and position.

2. **Given** a visa type has no workflow blueprint, **When** a client submits an application for that visa type, **Then** no application tasks are generated and the application is still created successfully.

3. **Given** two clients each submit an application for the same visa type, **When** both applications are created, **Then** each client has their own independent set of application tasks, and changes to one client's tasks do not affect the other.

---

### User Story 2 - Initial Task Status Assignment (Priority: P1)

When tasks are generated for a new application, the system assigns statuses that reflect the correct starting point: the first task is immediately active and ready to begin, while all subsequent tasks are locked until the preceding task is completed.

**Why this priority**: Status assignment is part of the generation step and must be correct from the start. Incorrect initial statuses would break the sequential workflow that all later phases depend on.

**Independent Test**: Can be fully tested by verifying that after task generation, exactly one task has status `in_progress` (the first by position) and all remaining tasks have status `pending`.

**Acceptance Scenarios**:

1. **Given** an application has just been created with 8 workflow tasks, **When** the task list is inspected, **Then** the task with the lowest position has status `in_progress` and all other tasks have status `pending`.

2. **Given** an application is created for a visa type with a single-task blueprint, **When** the task list is inspected, **Then** that one task has status `in_progress`.

---

### User Story 3 - Blueprint Changes Do Not Affect Existing Application Tasks (Priority: P2)

Once tasks have been generated for an application, they are independent of the workflow blueprint. If an administrator later modifies the blueprint (renames a task, changes a description), the already-generated application tasks remain exactly as they were at the time of creation.

**Why this priority**: Data integrity for in-progress applications. Clients must not see their active task details change mid-application because an admin edited the template.

**Independent Test**: Can be fully tested by modifying a blueprint task after application creation and confirming the application task still holds the original values.

**Acceptance Scenarios**:

1. **Given** an application was created with a task named "Application Fee Payment", **When** an admin renames the blueprint task to "Processing Fee Payment", **Then** the application task still reads "Application Fee Payment".

2. **Given** an application task has type `payment`, **When** the blueprint task type is changed to something else, **Then** the application task type remains `payment`.

---

### Edge Cases

- What happens when the workflow blueprint is empty (has sections but no tasks)? No application tasks should be generated; application creation must still succeed.
- What happens when task generation fails mid-way (e.g., database error)? The entire generation must be rolled back so the application is not left with a partial task set.
- What happens if an application is created for a visa type that predates the blueprint structure? The system falls back gracefully with zero tasks rather than throwing an error.
- Can duplicate application tasks be created if generation is triggered twice for the same application? The system must produce only one complete task set per application.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST automatically generate application tasks when a new visa application is created for a visa type that has a workflow blueprint.
- **FR-002**: Each generated application task MUST include the name, type, and position copied from the corresponding workflow blueprint task. (UPDATE005.md refers to this field as "title"; the existing `name` column is kept — no rename migration required.)
- **FR-003**: System MUST assign status `in_progress` to the first application task (lowest position) and status `pending` to all remaining tasks at the moment of generation.
- **FR-004**: Application tasks MUST be independent of the workflow blueprint after generation; blueprint modifications MUST NOT alter existing application tasks.
- **FR-005**: System MUST generate tasks from the section-based workflow blueprint when one exists for the visa type.
- **FR-006**: System MUST handle visa types with no workflow blueprint gracefully by creating the application with zero tasks and no error.
- **FR-007**: Task generation MUST be atomic; a partial failure MUST leave the application with either a complete task set or no tasks — never a partial set.
- **FR-008**: Each application MUST receive at most one generated task set; re-triggering generation for an application that already has tasks MUST NOT create duplicates.

### Key Entities

- **ApplicationTask**: A client-specific copy of a workflow task. Holds name (the task's display label), type, status, and position. Once created, it is fully independent of the blueprint and tracks the client's progress through that step.
- **VisaApplication**: The client's submitted application for a specific visa type. Owns the generated task set; each application has its own isolated set of tasks.
- **WorkflowTask** (blueprint): The source template task. Read during generation to copy values; not linked to the resulting application tasks after creation.
- **WorkflowSection** (blueprint): Groups of blueprint tasks ordered by position. Determines the ordering of tasks during generation.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of new visa applications for a visa type with a blueprint receive a complete task set matching the blueprint at the moment of application creation.
- **SC-002**: The first task in every newly created application has status `in_progress` and every subsequent task has status `pending` — with zero exceptions.
- **SC-003**: Zero application tasks are ever left in an inconsistent (partial) state due to generation failures.
- **SC-004**: Blueprint modifications after application creation have zero effect on any previously generated application task values.
- **SC-005**: Applications created for visa types without a blueprint are created successfully 100% of the time with zero application tasks generated.

## Assumptions

- The workflow blueprint for a visa type is fully seeded before any client submits a production application for that visa type.
- Task generation is triggered synchronously at the point of application creation (not deferred or async), ensuring tasks are available immediately after submission.
- The `position` field on workflow tasks determines ordering; the task with the lowest position value is the one assigned `in_progress` status.
- The existing `WorkflowService::seedTasksForApplication()` method is the correct extension point; this feature extends rather than replaces it.
- Phase 2 scope is limited to task generation only. Task completion, progression logic, and reviewer validation are addressed in later phases.
