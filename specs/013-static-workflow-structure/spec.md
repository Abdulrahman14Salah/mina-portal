# Feature Specification: Static Workflow Structure

**Feature Branch**: `013-static-workflow-structure`
**Created**: 2026-03-22
**Status**: Draft
**Input**: UPDATE005.md — Phase 1: Foundation and Architecture

## Overview

Define a predefined, reusable blueprint of sections and typed tasks for each visa type. This blueprint is the foundation that all later phases depend on: when a client applies for a visa, the system copies this blueprint to generate their personal task list.

Phase 1 delivers the structure only — no client-facing pages, no task progression logic. It answers the question: "What steps does a client need to complete for visa type X, and what kind of step is each one?"

## Clarifications

### Session 2026-03-22

- Q: What is the migration strategy for the existing `upload` task type? → A: Additive — allow `question`, `payment`, and `info` as new valid types alongside the existing `upload` type; removal of `upload` is deferred to a later phase.

## Scope

**In scope:**
- Data structure for workflow sections and tasks per visa type
- Three new task types: `question`, `payment`, `info` (added alongside existing `upload`)
- Predefined (seeded) workflow data for at least one real visa type
- No admin UI for managing blueprints (deferred to a later phase)

**Out of scope:**
- Removing or migrating existing `upload` type records (deferred to a later phase)
- Generating tasks for individual client applications (Phase 2)
- Task pages or client interaction with tasks (Phase 4)
- Task progression and unlocking logic (Phase 5)
- Reviewer approval flows (Phase 6)

---

## User Scenarios & Testing

### User Story 1 — Workflow Blueprint Exists for Each Visa Type (Priority: P1)

A business analyst or system administrator needs a predefined, ordered set of sections and tasks for each visa type so that the system knows exactly what steps a client must complete. The blueprint must be in place before any client application can generate tasks.

**Why this priority**: Every downstream phase (task generation, task pages, progression) depends on this blueprint existing. Nothing else can be built without it.

**Independent Test**: Seed the workflow blueprint for one visa type and verify the correct sections and tasks are retrievable in the correct order with the correct types.

**Acceptance Scenarios**:

1. **Given** a visa type exists, **When** the workflow blueprint is queried for that visa type, **Then** exactly 4 sections are returned in position order.
2. **Given** a section exists, **When** its tasks are queried, **Then** all tasks are returned in position order, each with a title and a type.
3. **Given** a visa type has a complete blueprint, **When** a second visa type is seeded with a different blueprint, **Then** each visa type returns only its own sections and tasks.

---

### User Story 2 — Task Types Are Correctly Defined (Priority: P1)

Each task in the blueprint must carry a type that determines how it behaves later: a question task collects client input, a payment task requires a receipt upload, and an info task displays instructions only. The three new types must be accepted; truly invalid values must still be rejected.

**Why this priority**: The type is the contract between the blueprint (Phase 1) and the task page (Phase 4). An incorrect or missing type means the wrong UI is shown to the client.

**Independent Test**: Seed one task of each of the three new types and verify each stores and retrieves its type correctly. Attempt to store a completely invalid type and verify it is rejected.

**Acceptance Scenarios**:

1. **Given** a workflow task is defined as type `question`, **When** retrieved, **Then** its type is exactly `question`.
2. **Given** a workflow task is defined as type `payment`, **When** retrieved, **Then** its type is exactly `payment`.
3. **Given** a workflow task is defined as type `info`, **When** retrieved, **Then** its type is exactly `info`.
4. **Given** an attempt to store a task with a completely invalid type (e.g., `foobar`), **When** the save is attempted, **Then** the system rejects it before the data reaches storage.
5. **Given** an existing task with type `upload` was created by a prior version of the system, **When** it is retrieved, **Then** it is returned without error (backward compatibility preserved).

---

### User Story 3 — Blueprint Is Stable and Shareable Across Applications (Priority: P2)

The workflow blueprint must be defined once and remain unchanged regardless of how many client applications are created. The same section/task blueprint is used as the template for every application of that visa type. Editing the blueprint does not retroactively change existing applications' tasks.

**Why this priority**: Stability of the blueprint is essential for data integrity. Applications created before a blueprint change must not be affected.

**Independent Test**: Create two applications of the same visa type and verify both reference the same blueprint without modifying it.

**Acceptance Scenarios**:

1. **Given** a blueprint with 3 tasks for a visa type, **When** two separate applications are generated from it, **Then** both applications have 3 tasks and the blueprint itself is unchanged.
2. **Given** a blueprint task's title is updated after an application is created, **When** the existing application's tasks are queried, **Then** the existing application's task titles are unchanged.

---

### Edge Cases

- What happens when a visa type has no sections defined? System must return an empty blueprint cleanly with no error.
- What happens when a section has no tasks? The section exists but its task list is empty — this is a valid intermediate state.
- Can two sections within the same visa type share the same position value? Must be rejected — positions must be unique per visa type.
- Can two tasks within the same section share the same position value? Must be rejected — positions must be unique per section.
- What happens when a task with the legacy `upload` type is retrieved alongside new-type tasks? Both are returned without error; `upload` is treated as a valid legacy value.

---

## Requirements

### Functional Requirements

- **FR-001**: The system MUST support a workflow blueprint structure where each visa type can have multiple ordered sections.
- **FR-002**: Each section MUST contain an ordered list of tasks.
- **FR-003**: Each task MUST have a title, an optional description, a type, and a position within its section.
- **FR-004**: Task types MUST accept the values `question`, `payment`, and `info` for new blueprint tasks. The legacy `upload` type MUST remain valid for backward compatibility; removal of `upload` is deferred to a later phase. Any value outside these four MUST be rejected.
- **FR-005**: Section positions MUST be unique within a visa type; task positions MUST be unique within a section.
- **FR-006**: The system MUST allow a visa type to have zero sections (no workflow defined yet) without errors.
- **FR-007**: The system MUST provide a way to seed a complete workflow blueprint for at least one real visa type, covering all three new task types.
- **FR-008**: Modifying the blueprint (sections or tasks) MUST NOT retroactively alter tasks already copied to existing client applications.

### Key Entities

- **WorkflowSection**: Belongs to a visa type. Has a name and a position (ordering within the visa type). Contains one or more workflow tasks.
- **WorkflowTask**: Belongs to a workflow section. Has a title, an optional description, a type (`question`, `payment`, `info`, or legacy `upload`), and a position (ordering within the section). This is the blueprint record — not a client-specific task.

---

## Success Criteria

### Measurable Outcomes

- **SC-001**: A complete workflow blueprint for one real visa type (4 sections, multiple tasks per section, all three new task types represented) can be seeded and retrieved correctly in a single operation.
- **SC-002**: 100% of blueprint queries return sections and tasks in the correct position order without requiring additional sorting by the caller.
- **SC-003**: Attempting to store a task with a truly invalid type (outside `question`, `payment`, `info`, `upload`) is rejected 100% of the time before the data reaches persistent storage.
- **SC-004**: The seeded blueprint for a real visa type passes a full end-to-end verification: correct section count, correct task count per section, correct types, correct ordering.

---

## Assumptions

- The existing `workflow_sections` and `workflow_tasks` tables are already present in the database schema from prior work and will be extended rather than replaced.
- The `upload` task type is a legacy value that remains valid for existing records; new blueprint tasks seeded in Phase 1 will use only `question`, `payment`, or `info`.
- At least one real visa type with a meaningful blueprint will be seeded as part of this phase to validate the full structure end-to-end.
- No admin UI is required to manage blueprints in Phase 1 — the data is seeded programmatically.
- The blueprint is version-stable for Phase 1; versioned blueprint history is not required.
- Each visa type will have exactly 4 sections in Phase 1, as specified in UPDATE005.
