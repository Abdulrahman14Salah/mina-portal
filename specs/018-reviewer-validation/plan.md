# Implementation Plan: Reviewer Validation

**Branch**: `018-reviewer-validation` | **Date**: 2026-03-24 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `specs/018-reviewer-validation/spec.md`

## Summary

Add reviewer-controlled task approval with a `pending_review` intermediate status. Clients explicitly submit tasks for review (draft saves remain separate). Payment tasks always require reviewer approval; question tasks support configurable auto-complete. Reviewers receive both email and in-system notification when tasks await their action. The existing WorkflowService, policy, and reviewer view are extended rather than replaced.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 11
**Primary Dependencies**: Blade SSR, Alpine.js v3, spatie/laravel-permission v6+, Laravel Queue (database driver)
**Storage**: MySQL (MAMP, port 8889 dev); SQLite in-memory (tests); private disk for file uploads
**Testing**: PHPUnit — Laravel feature tests + unit tests for WorkflowService
**Target Platform**: Web application (server-side rendered)
**Performance Goals**: Standard web — redirect responses under 500ms; queued mail delivery within 30s of submission
**Constraints**: Must not break existing tests; must reuse existing WorkflowService, ApplicationTaskPolicy, DocumentService; no new tables
**Scale/Scope**: Low-volume portal (~hundreds of applications); in-system badge uses a direct DB count query

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Modular Architecture | ✅ PASS | New mail class in Notifications module; logic stays in Tasks/WorkflowService |
| II. Separation of Concerns | ✅ PASS | Controllers → HTTP only; WorkflowService owns all state transitions; Blade → UI only |
| III. Database-Driven Workflows | ✅ PASS | `approval_mode` stored on `workflow_tasks` and copied to `application_tasks`; no hardcoded mode |
| IV. API-Ready Design | ✅ PASS | All mutations via Service classes; controller calls service only |
| V. Roles & Permissions | ✅ PASS | New `tasks.submit-for-review` permission seeded; all checks via `$user->can()` and Policy |
| VI. Payment Integrity | ✅ N/A | Receipt uploads are documents, not Stripe payments; not in scope |
| VII. Secure Document Handling | ✅ PASS | Existing DocumentService used for receipts; no change needed |
| VIII. Dynamic Workflow Engine | ✅ PASS | `pending_review` state persisted on `application_tasks`; approval_mode in DB |
| IX. Security by Default | ✅ PASS | Form Requests for all inputs; Policy authorization on every action; auth middleware |
| X. Multi-Language Support | ✅ PASS | All new strings use `__()` with lang keys; email template uses `app()->getLocale()` for RTL |
| XI. Observability | ✅ PASS | Two new audit events: `task_submitted_for_review`, `task_auto_completed` |
| XII. Testing Standards | ✅ PASS | Feature tests required for: client submit-for-review, reviewer approve, reviewer reject, auto-complete |

**Complexity Tracking**: No violations. No new tables. Existing infrastructure extended only.

## Project Structure

### Documentation (this feature)

```text
specs/018-reviewer-validation/
├── plan.md              ← This file
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output
├── quickstart.md        ← Phase 1 output
├── contracts/
│   └── routes.md        ← Phase 1 output
└── tasks.md             ← Phase 2 output (/speckit.tasks)
```

### Source Code Changes

```text
database/migrations/
├── ****_add_approval_mode_to_workflow_tasks.php
└── ****_add_reviewer_fields_to_application_tasks.php

app/Models/
├── WorkflowTask.php          (add approval_mode to fillable + validation)
└── ApplicationTask.php       (add approval_mode, reviewed_by, reviewed_at)

app/Services/Tasks/
└── WorkflowService.php       (add submitForReview, autoCompleteTask; update guards)

app/Services/Tasks/
└── TaskAnswerService.php     (call autoCompleteTask when approval_mode='auto')

app/Mail/
└── TaskSubmittedForReviewMail.php    (new)

app/Http/Requests/Client/
└── SubmitTaskForReviewRequest.php    (new)

app/Http/Requests/Reviewer/
├── ApproveTaskRequest.php    (new — replaces inline validation)
└── RejectTaskRequest.php     (new — replaces inline validation)

app/Policies/
└── ApplicationTaskPolicy.php (add submitForReview method)

app/Http/Controllers/Client/
└── TaskController.php        (add submitForReview action)

app/Http/Controllers/Reviewer/
└── ReviewerApplicationController.php (update approve/reject to use Form Requests;
                                        update $activeTask query to pending_review)
└── ReviewerDashboardController.php   (pass pendingReviewCount to view)

routes/web.php                (add client submit-for-review route)

resources/views/
├── client/tasks/show.blade.php               (add Save Draft / Submit for Review buttons;
│                                              add "Awaiting Review" state display)
├── reviewer/applications/show.blade.php      (update active task detection to pending_review)
├── reviewer/dashboard/index.blade.php        (add pending_review badge count)
├── components/admin/nav.blade.php            (no change — task builder already listed)
├── admin/task-builder/index.blade.php        (add approval_mode toggle for question tasks)
├── mail/task-submitted-for-review.blade.php  (new email template)
└── lang/en/tasks.php                         (add new translation keys)

database/seeders/
└── RolePermissionSeeder.php  (add tasks.submit-for-review permission to client role)
```

## Implementation Sequence

### Step 1 — Database Migrations
1. `add_approval_mode_to_workflow_tasks` — add `approval_mode varchar(10) nullable`
2. `add_reviewer_fields_to_application_tasks` — add `approval_mode varchar(10) nullable`, `reviewed_by bigint FK nullable`, `reviewed_at timestamp nullable`; also add `'pending_review'` as valid status (documentation only — status is varchar, no enum constraint)

### Step 2 — Model Updates
1. `WorkflowTask` — add `approval_mode` to `$fillable`; update booted validation
2. `ApplicationTask` — add `approval_mode`, `reviewed_by`, `reviewed_at` to `$fillable`; add `reviewed_at` cast; add `reviewer()` relationship

### Step 3 — Permission Seeder
1. Add `tasks.submit-for-review` permission
2. Assign to `client` role

### Step 4 — WorkflowService Updates
1. Update `approveTask()` guard: accept `pending_review` (not `in_progress`)
2. Update `rejectTask()` and `rejectTaskWithReason()` guards: accept `pending_review`
3. Update both to write `reviewed_by` and `reviewed_at`
4. Add `submitForReview(ApplicationTask $task): void`
5. Add `autoCompleteTask(ApplicationTask $task): void` (internal)

### Step 5 — TaskAnswerService Update
1. After `submitAnswers()` upsert: check `$task->approval_mode === 'auto'` → call `WorkflowService::autoCompleteTask()`

### Step 6 — Policy Update
1. Add `submitForReview(User, ApplicationTask): bool` to `ApplicationTaskPolicy`

### Step 7 — Form Requests
1. `SubmitTaskForReviewRequest` (Client)
2. `ApproveTaskRequest` (Reviewer)
3. `RejectTaskRequest` (Reviewer)

### Step 8 — Mail Class & Template
1. `TaskSubmittedForReviewMail` (Mailable, ShouldQueue)
2. `resources/views/mail/task-submitted-for-review.blade.php`

### Step 9 — Client Controller & Route
1. `TaskController@submitForReview` — validates via Form Request, calls `WorkflowService::submitForReview()`, redirects back
2. Register route: `POST /client/tasks/{task}/submit-for-review`

### Step 10 — Reviewer Controller Updates
1. `ReviewerApplicationController` — update `$activeTask` query to detect `pending_review` tasks; wire `ApproveTaskRequest`, `RejectTaskRequest`
2. `ReviewerDashboardController` — pass `$pendingReviewCount` to view

### Step 11 — Admin Task Builder UI
1. Add `approval_mode` select/checkbox to question-type task cards in `admin/task-builder/index.blade.php`

### Step 12 — Client Task Page UI
1. Add "Save Draft" button (submits to existing answers/receipt routes; no status change)
2. Add "Submit for Review" button (POSTs to new submit-for-review route)
3. Add "Awaiting Review" status display panel (shown when `status === 'pending_review'`)
4. Client cannot re-submit while in `pending_review` state (buttons hidden/disabled)

### Step 13 — Reviewer UI Updates
1. `reviewer/applications/show.blade.php` — update active task condition from `in_progress` → `pending_review`; ensure approve/reject forms visible on `pending_review` tasks
2. `reviewer/dashboard/index.blade.php` — add badge showing `$pendingReviewCount`

### Step 14 — Translation Keys
Add to `lang/en/tasks.php`:
- `submit_for_review` — "Submit for Review"
- `save_draft` — "Save Draft"
- `awaiting_review` — "Awaiting Review"
- `awaiting_review_description` — "Your submission is being reviewed. You'll be notified of the outcome."
- `submitted_for_review_subject` — email subject line
- (Repeat pattern for `lang/ar/tasks.php`)

### Step 15 — Feature Tests
Tests to write (in order):
1. `SubmitTaskForReviewTest` — client can submit in_progress task; cannot submit pending/approved/rejected task; cannot submit another client's task
2. `AutoCompleteQuestionTaskTest` — question task with approval_mode='auto' completes on answer submission; next task unlocks
3. `ReviewerApproveTaskTest` — reviewer approves pending_review task; next task unlocks; reviewed_by/reviewed_at set; audit log entry created
4. `ReviewerRejectTaskTest` — reviewer rejects with reason; task back to in_progress; rejection_reason visible; cannot reject without reason
5. `ReviewerValidationAccessTest` — reviewer cannot approve tasks on unassigned applications; admin can approve any task
6. `TaskNotificationTest` — `TaskSubmittedForReviewMail` queued when task moves to pending_review; not queued on auto-complete

## Definition of Done

- [ ] All 15 implementation steps complete
- [ ] All 6 feature test suites pass (no existing tests broken)
- [ ] `pending_review` status correctly gates reviewer approve/reject (service guards updated)
- [ ] Email queued on every `submitForReview()` call (when reviewer assigned)
- [ ] Auto-complete bypasses `pending_review` entirely for question tasks with `approval_mode='auto'`
- [ ] Reviewer dashboard badge shows live `pending_review` count
- [ ] `reviewed_by` and `reviewed_at` populated on every approve/reject
- [ ] Two new audit events logged (`task_submitted_for_review`, `task_auto_completed`)
- [ ] Admin task builder persists `approval_mode` for question tasks
- [ ] All new strings use `__()` — no hardcoded English
- [ ] No business logic in controllers or Blade templates
- [ ] All inputs validated via Form Requests
