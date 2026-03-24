# Data Model: Task Type Behavior (015)

## New Tables

### `task_questions`

Stores question definitions for question-type workflow blueprint tasks. Defined once at blueprint level; shared across all applications of the same visa type.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | Auto-increment |
| `workflow_task_id` | bigint FK → workflow_tasks | The blueprint task this question belongs to |
| `prompt` | string(500) | The question text shown to the client |
| `required` | boolean | Whether the client must provide an answer |
| `position` | smallint | Display order within the task |
| `created_at` / `updated_at` | timestamps | Standard Laravel |

**Constraints**: No unique constraint — a workflow task may have multiple questions. Position ordering controls display.

---

### `task_answers`

Stores client answers to task questions. One row per (application task, question) pair.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | Auto-increment |
| `application_task_id` | bigint FK → application_tasks | The client's specific task instance |
| `task_question_id` | bigint FK → task_questions | The question being answered |
| `answer` | text | The client's free-text response |
| `created_at` / `updated_at` | timestamps | Standard Laravel |

**Constraints**: `UNIQUE(application_task_id, task_question_id)` — one answer per question per task. Enforced at both DB and application level.

---

## Existing Tables (unchanged schema, new behavior)

### `application_tasks` (extended behavior, no schema change)

| Column | Relevant to Phase 3 |
|--------|---------------------|
| `type` | `question` → answers stored in `task_answers`; `payment` → receipt stored in `documents`; `info` → no stored input |
| `status` | `in_progress` → client can submit; `approved` → locked, no further submission |

### `documents` (reused for payment receipts)

A payment receipt is a `Document` row with:
- `application_task_id` = the payment task's ID
- `source_type` = `'client'`
- Standard file columns: `path`, `disk`, `mime_type`, `size`, `original_filename`, `stored_filename`

At most one non-archived document per payment task is treated as the current receipt. Replacement: the old document is hard-deleted via `DocumentService::delete()` before the new one is stored.

---

## New Models

### `App\Models\TaskQuestion`

```
fillable: [workflow_task_id, prompt, required, position]
casts: [required => boolean, position => integer]
relationships:
  workflowTask(): BelongsTo(WorkflowTask)
  answers(): HasMany(TaskAnswer)
```

### `App\Models\TaskAnswer`

```
fillable: [application_task_id, task_question_id, answer]
relationships:
  applicationTask(): BelongsTo(ApplicationTask)
  question(): BelongsTo(TaskQuestion)
```

---

## Entity Relationships

```
WorkflowTask (blueprint)
  └── task_questions (1→many, ordered by position)
        └── task_answers (1→many per application task)

ApplicationTask (client copy)
  ├── task_answers (1→many, one per question)
  └── documents (1→many; for payment tasks, one active receipt at a time)
```

---

## New Migrations

| Migration | Purpose |
|-----------|---------|
| `create_task_questions_table` | New table linked to `workflow_tasks` |
| `create_task_answers_table` | New table with unique constraint on `(application_task_id, task_question_id)` |

No changes to existing tables.

---

## Answer Submission Flow

```
submitAnswers(ApplicationTask $task, array $answers):
  GUARD: task.status must be 'in_progress' (not approved/rejected)
  DB.transaction:
    FOR each answer in $answers:
      upsert task_answers on (application_task_id, task_question_id)
      → values: answer text
  auditLog.log('task_answers_submitted', client, {task_id, reference})
```

## Receipt Upload Flow

```
uploadReceipt(ApplicationTask $task, UploadedFile $file, User $client):
  GUARD: task.type must be 'payment'
  GUARD: task.status must be 'in_progress'
  Validate file (MIME, size) via UploadReceiptRequest
  DB.transaction:
    existing = documents where application_task_id = task.id, source_type = 'client'
    IF existing:
      DocumentService::delete(existing, client)   ← removes file + DB row + audit log
    DocumentService::upload(application, task, file, client, 'client')
```

## Reviewer Completion Flow (existing, unchanged)

```
WorkflowService::approveTask(ApplicationTask $task, ?string $note):
  → transitions task to status='approved', sets completed_at
  → activates next task (sets status='in_progress')
  → audit log entry 'task_approved'
```
