# Feature Specification: Workflow Integrity Fixes

**Feature Branch**: `012-workflow-integrity`
**Created**: 2026-03-22
**Status**: Draft
**Input**: UPDATE004.md — Phase 1: Workflow Integrity Fixes only

## Overview

This feature corrects two logic bugs in the workflow execution engine. No new behaviour is introduced. The fixes restore correct operation under edge cases that exist in the current codebase.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Accurate Audit Trail for Workflow Start (Priority: P1)

An admin reviews the audit log to understand when a visa application's workflow began. Currently, the system records a "workflow started" event even when no tasks were created — because the visa type has no workflow configured. This produces misleading audit entries that cannot be reconciled with the actual application state.

After this fix, the audit log only records a workflow-started event when tasks are actually created and the application enters active processing.

**Why this priority**: Audit logs are relied upon for compliance and operational review. False entries undermine trust in the audit trail.

**Independent Test**: Create a visa application for a visa type with no workflow tasks defined, trigger seeding, and verify the audit log contains no workflow-started entry for that application.

**Acceptance Scenarios**:

1. **Given** a visa application is submitted for a visa type with no workflow tasks configured, **When** the workflow seeding process runs, **Then** no "workflow started" audit log entry is created for that application.

2. **Given** a visa application is submitted for a visa type with one or more workflow tasks configured, **When** the workflow seeding process runs and tasks are created, **Then** exactly one "workflow started" audit log entry is created.

3. **Given** an application already has tasks (seeding already ran), **When** the seeding process is triggered again, **Then** no new audit log entry is created and no tasks are duplicated.

---

### User Story 2 — Reliable Workflow Task Progression (Priority: P1)

A reviewer advances a task and expects the next task in the workflow to become active automatically. Currently, if workflow tasks have non-sequential position numbers (for example, positions 1, 3, 5 instead of 1, 2, 3), the system fails to find the next task. The workflow silently stalls: the current task is approved but no subsequent task becomes active.

After this fix, the system always activates the correct next task regardless of how position numbers are assigned.

**Why this priority**: A stalled workflow leaves the client's application stuck with no progress and no indication to the reviewer that anything went wrong.

**Independent Test**: Create a workflow where tasks have non-contiguous positions (e.g., 1, 3, 5). Advance the first task and verify the second task (position 3) becomes active — not a missing position 2.

**Acceptance Scenarios**:

1. **Given** a workflow has tasks at positions 1, 2, 3, **When** a reviewer advances task at position 1, **Then** the task at position 2 becomes active.

2. **Given** a workflow has tasks at positions 1, 3, 5, **When** a reviewer advances task at position 1, **Then** the task at position 3 becomes active.

3. **Given** a reviewer advances the final task in the workflow, **When** the action completes, **Then** no subsequent task is activated and the operation succeeds without errors.

4. **Given** a reviewer approves (not advances) a task in a non-contiguous workflow, **When** the approval completes, **Then** the task with the next highest position becomes active — same behaviour as advance.

---

### Edge Cases

- What if a visa type has workflow sections defined but all sections contain zero tasks? → No tasks are seeded; no audit log entry is created.
- What if two tasks share the same position number? → The task encountered first in ascending position order is selected; no crash occurs.
- What if there is a large gap between positions (e.g., current is 1, next is 1000)? → The task at position 1000 still becomes active correctly.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST only emit a "workflow started" audit log entry when at least one task is successfully created for an application.
- **FR-002**: The system MUST NOT emit a "workflow started" audit log entry when zero tasks are seeded (regardless of the reason — no templates, no sections, or empty sections).
- **FR-003**: When a workflow task is advanced, the system MUST activate the task with the lowest position value strictly greater than the current task's position.
- **FR-004**: When a workflow task is approved, the system MUST apply the same next-task selection logic as advance (lowest position strictly greater than current).
- **FR-005**: The next-task selection logic MUST produce correct results whether task position numbers are contiguous or contain gaps of any size.
- **FR-006**: The fix to audit logging MUST NOT alter the existing behaviour when tasks are successfully seeded — the log entry must still be created exactly once in that case.

### Key Entities

- **Application Task**: A work unit in a visa application's workflow. Has a `position` integer determining display and execution order. Valid statuses: `pending`, `in_progress`, `approved`, `rejected`.
- **Visa Application**: The parent record whose status moves to `in_progress` when the first task is activated during seeding.
- **Audit Log Entry**: An immutable event record. The `workflow_started` event type must only exist when the application genuinely entered active processing with tasks.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Zero false "workflow started" audit log entries — every such entry corresponds to an application that has at least one created task.
- **SC-002**: 100% of workflow advance and approve actions correctly activate the next task by ordered position, with no silent stalls due to position gaps.
- **SC-003**: All existing automated tests continue to pass without modification to any test assertions.
- **SC-004**: Applications with contiguous positions behave identically before and after the change — no regressions for the standard case.

---

## Scope

### In Scope

- Audit log condition in workflow seeding: only log when tasks are created.
- Next-task query in both the advance and approve workflow operations: select by ordered position greater than current.

### Out of Scope

- Authorization fixes (Phase 2 of UPDATE004.md).
- Transaction wrapping for document uploads (Phase 3).
- Position assignment race condition (Phase 4).
- Any changes to the workflow UI or user-facing messages.
- Modifications to existing test assertions.

---

## Assumptions

- Task positions are integers assigned at creation time and are not guaranteed to be contiguous.
- Both `advance` and `approve` are separate code paths; each requires the same next-task fix independently.
- The audit log call happens after the database transaction that creates tasks; this ordering is preserved by the fix.
- "Workflow seeding" is triggered once when an application is first processed; the fix does not affect re-seeding guards already in place.
