# Research: Reviewer Validation (018)

## Existing System Inventory

### application_tasks — Current Schema
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| application_id | bigint FK | → visa_applications |
| workflow_task_id | bigint FK nullable | → workflow_tasks |
| workflow_step_template_id | bigint FK nullable | legacy, superseded |
| position | smallint unsigned | ordering |
| name | varchar(150) | |
| description | text nullable | |
| type | varchar(50) | 'upload', 'question', 'payment', 'info' |
| status | varchar(20) | 'pending', 'in_progress', 'approved', 'rejected' |
| reviewer_note | text nullable | note from reviewer on approval |
| rejection_reason | text nullable | reason shown to client |
| completed_at | timestamp nullable | set on approve/reject |
| created_at / updated_at | timestamps | |

### workflow_tasks — Current Schema
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | PK |
| workflow_section_id | bigint FK | → workflow_sections |
| name | varchar(255) | |
| description | text nullable | |
| type | varchar(50) | validated: 'upload', 'question', 'payment', 'info' |
| position | smallint unsigned | |
| created_at / updated_at | timestamps | |

### WorkflowService — Existing Methods
- `seedTasksForApplication(VisaApplication)` — clones workflow into application_tasks
- `advanceTask(ApplicationTask, ?note)` → status: `approved`, advances next
- `approveTask(ApplicationTask, ?note)` → status: `approved`, advances next *(requires `in_progress`)*
- `rejectTask(ApplicationTask, ?note)` → status: `rejected` *(requires `in_progress`)*
- `rejectTaskWithReason(ApplicationTask, reason)` → status: `rejected` with rejection_reason *(requires `in_progress`)*
- `reopenTask(ApplicationTask)` → status: `rejected` → `in_progress`

**Critical gap**: All service methods require `in_progress` status. With `pending_review` as a new intermediate state, these guard conditions must be updated.

### ApplicationTaskPolicy — Existing Methods
- `approve(user, task)` → admin bypass OR `assigned_reviewer_id === user->id`
- `reject(user, task)` → admin bypass OR `assigned_reviewer_id === user->id`
- `reopen(user, task)` → `tasks.advance` permission

### Reviewer View — Existing UI
`resources/views/reviewer/applications/show.blade.php` already contains:
- Approve form (POST `reviewer.tasks.approve`, optional `note` textarea)
- Reject form (POST `reviewer.applications.tasks.reject`, required `rejection_reason` textarea, minlength=5)
- Reopen form (POST `reviewer.applications.tasks.reopen`, amber button)
- Active task detection via `$activeTask` variable (controller resolves current `in_progress` task)

**Critical gap**: Active task detection uses `in_progress` status. Must be updated to detect `pending_review` tasks.

### Reviewer Routes — Existing
```
POST /reviewer/applications/{application}/tasks/{task}/advance  → advance
POST /reviewer/applications/{application}/tasks/{task}/reject   → reject
POST /reviewer/applications/{application}/tasks/{task}/reopen   → reopen
POST /reviewer/tasks/{task}/approve                             → approve
POST /reviewer/tasks/{task}/reject                             → rejectById
```

### Notifications — Existing
- Zero notification classes exist. `app/Notifications/` is empty.
- Mail: one class `PaymentConfirmedMail implements ShouldQueue`, sent via `Mail::to()->queue()`.
- Queue: `database` driver (jobs table exists).
- User model has `Notifiable` trait.

### AuditLogService
Single method: `log(string $event, ?User $user, array $metadata): void`
Inserts into `audit_logs` with: user_id, event, ip_address, user_agent, metadata (JSON).

---

## Decisions

### Decision 1 — New Status: `pending_review`
**Chosen**: Add `pending_review` as a status value between `in_progress` and `approved`/`rejected`.

**State machine**:
```
pending → in_progress → pending_review → approved  (reviewer approves)
                                       ↓
                                    rejected → in_progress (reopened by reviewer)

in_progress → approved  (auto-complete path for question tasks with approval_mode='auto')
```

**Rationale**: The clarification session confirmed clients need explicit draft-save + submit-for-review actions. The `pending_review` state is the gate between client submission and reviewer action.

**Impact**: WorkflowService guard checks (`requires in_progress`) must be updated for `approveTask` and `rejectTask` to also accept `pending_review`.

### Decision 2 — Status Naming Alignment
**Spec used** `completed` for approved tasks. **Code uses** `approved`. Decision: use `approved` throughout to match the existing codebase. The spec's language is updated in implementation to reflect actual status values.

### Decision 3 — `approval_mode` Column on `workflow_tasks`
**Chosen**: Add `approval_mode` varchar(10) to `workflow_tasks`, values: `'auto'` | `'manual'`, nullable (null treated as `'manual'`).

**Only relevant for question-type tasks**. Payment tasks always require manual reviewer approval regardless of this field.

**Rationale**: Constitution Principle III mandates workflow configuration lives in the database. The column lives on `workflow_tasks` (the template), and is copied to `application_tasks` at seeding time so the runtime task carries its own approval mode without join lookups.

**Rationale for copying to application_tasks**: Consistent with how `name`, `description`, and `type` are already copied from `workflow_tasks` to `application_tasks` at seeding time.

### Decision 4 — Audit Trail for Approvals
**Chosen**: Add `reviewed_by` (FK users, nullable) and `reviewed_at` (timestamp, nullable) columns to `application_tasks` instead of a separate `task_approval_events` table.

**Rationale**: The `AuditLogService` already records every approve/reject event with actor identity and timestamp. A separate events table adds join overhead with no functional benefit at this scale. The `reviewed_by`/`reviewed_at` columns provide fast "who acted last" access on the task record itself.

### Decision 5 — Email Notification via Mail Class (ShouldQueue)
**Chosen**: Create `TaskSubmittedForReviewMail` (Mailable, ShouldQueue) following the pattern of `PaymentConfirmedMail`. Sent via `Mail::to()->queue()` in WorkflowService when task transitions to `pending_review`.

**Rationale**: Consistent with the one existing mail pattern. Queue already configured with database driver. No new infrastructure required.

### Decision 6 — In-System Badge via Dashboard Query
**Chosen**: The reviewer dashboard controller queries `pending_review` task count across all assigned applications and passes it to the view. No separate notification model needed.

**Rationale**: Simple, no extra tables. The count is always fresh from DB — no stale cache issues. Given low reviewer count, the query is negligible.

### Decision 7 — Auto-Complete Path for Question Tasks
**Chosen**: When `approval_mode = 'auto'` on a question task, `TaskAnswerService::submitAnswers()` will call `WorkflowService::approveTask()` directly after saving answers, bypassing `pending_review` entirely. No email sent (no reviewer action needed).

**Rationale**: This keeps the auto-complete path invisible to reviewers and avoids noisy notifications for tasks they don't need to act on.

### Decision 8 — Draft Save via Existing Submission Routes
**Chosen**: Draft saving is not a separate action for question tasks — `TaskAnswerService::submitAnswers()` already upserts answers without changing status. A new "Save Draft" button simply POSTs to the existing answers route. For receipts, `TaskAnswerService::uploadReceipt()` already stores the file without status change.

**The new action is "Submit for Review"** — a separate button/route that transitions `in_progress` → `pending_review` after the client has saved their content.

### Decision 9 — Admin Task Builder `approval_mode` Toggle
**Chosen**: Add a simple checkbox/select to the existing task builder UI for question-type tasks. No new page — inline toggle within the existing task card.

**Rationale**: The spec scopes this to question tasks only. The admin builder already exists; this is a minor addition.

---

## Alternatives Considered

| Alternative | Rejected Because |
|-------------|-----------------|
| Separate `task_approval_events` table | AuditLogService already covers audit; over-engineering at this scale |
| Laravel Notifications class instead of Mail | No in-app notification UI needed; Mail is sufficient and already established |
| `completed` status for approved tasks | Code already uses `approved`; changing would break existing tests |
| Single submit-only (no draft) | Clarification Q1 explicitly chose both draft + explicit submit |
