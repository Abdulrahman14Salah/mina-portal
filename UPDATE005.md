# UPDATE004 — Task-Based Visa Workflow System

## Executive Summary

Refactor the existing system to support a structured task-based workflow per visa type.

Each visa contains predefined sections and tasks.
Each task has its own page and behavior (question, payment, or action).

The system must guide the client step-by-step:
- Complete current task
- Reviewer validates
- Next task unlocks

Do NOT rebuild the system. Refactor and extend the existing implementation.

---

## Phase 1 — Static Workflow Structure

### Goal
Define fixed sections and tasks per visa type.

### Specs

- Each visa has:
  - 4 sections
  - Each section contains multiple tasks

- Tasks are predefined (no admin builder required now)

- Data structure:
  - workflow_sections
  - workflow_tasks

Each task must include:
- title
- description (optional)
- type:
  - question
  - payment
  - info

---

## Phase 2 — Application Task Generation

### Goal
Generate tasks for each client application

### Specs

- When client selects a visa:
  - Clone workflow tasks into application_tasks

- Each application_task must include:
- title
- type
- status

### Status values:
- pending
- in_progress
- completed

---

## Phase 3 — Task Types Behavior

### Goal
Support different task behaviors

---

### 1. Question Task

#### Behavior:
- Task page shows questions
- Client submits answers

#### Requirements:
- Store answers per task
- Task is marked as “completed” when an Admin or Reviewer marks it as done.

---

### 2. Payment Task

#### Behavior:
- Task instructs user to pay (e.g., first installment)
- Client uploads payment receipt

#### Reviewer Flow:
- Reviewer checks:
  - Payment done
  - Receipt uploaded

- Reviewer marks task as completed

---

### 3. Info Task

#### Behavior:
- Task shows instructions only
- No input required

---

## Phase 4 — Task Page

### Goal
Each task has its own page

### Specs

Route:
- /client/tasks/{task}

Page must display:
- title
- description

Render based on type:
- question → form
- payment → upload receipt
- info → text only

---

## Phase 5 — Task Progression (CRITICAL)

### Goal
Enforce sequential workflow

### Rules

- Only ONE task is active at a time
- Next task unlocks ONLY after current task is completed

### Behavior

- When task is completed:
  - Next task status → in_progress

- No skipping allowed

---

## Phase 6 — Reviewer Validation

### Goal
Reviewer controls approval for certain tasks

### Specs

- Reviewer can:
  - view tasks
  - approve completion

### Rules

- For payment tasks:
  - reviewer MUST approve before next task unlocks

- For question tasks:
  - can auto-complete OR require reviewer approval (configurable)

---

## Phase 7 — Authorization

### Rules

Client:
- can only access own tasks
- cannot skip tasks

Reviewer:
- can only access assigned applications
- can approve tasks

Admin:
- full access

---

## Phase 8 — UI Behavior

### Client Dashboard

- Show sections with tasks
- Highlight current active task
- Locked tasks are disabled

---

## Phase 9 — Constraints

- Do NOT break existing tests
- Do NOT rewrite entire system
- Reuse existing:
  - application_tasks
  - document system
  - reviewer assignment

---

## Phase 10 — Edge Cases

- Prevent completing already completed task
- Prevent accessing future tasks
- Prevent uploading receipt twice incorrectly
- Ensure next task always exists before activation

---

## Final Goal

Create a guided workflow system where:

Client:
- follows steps one by one

Reviewer:
- validates critical steps

System:
- controls progression automatically