# Quickstart: Reviewer Validation (018)

## What This Feature Does

Adds a `pending_review` state between client task submission and reviewer action. Clients can save drafts freely; an explicit "Submit for Review" moves the task into the reviewer's queue. Reviewers receive email + dashboard badge notification. Payment tasks always need manual reviewer approval; question tasks can be configured to auto-complete.

## Key Concepts

| Concept | How It Works |
|---------|-------------|
| `pending_review` status | New status between `in_progress` and `approved`/`rejected` |
| Submit for Review | Client explicit action → `in_progress` → `pending_review` + email to reviewer |
| Save Draft | Client submits answers/receipt without status change (task stays `in_progress`) |
| Auto-complete | Question tasks with `approval_mode='auto'` skip `pending_review` entirely on submission |
| Reviewer badge | Dashboard count of `pending_review` tasks across all assigned applications |

## Status Machine (After This Feature)

```
pending → in_progress → pending_review → approved → (next task unlocks)
                     ↑           ↓
                     └──── rejected → reopen → in_progress

in_progress → approved  (auto-complete path only)
```

## New Migration Checklist

Run after branching:
```bash
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
```

## New Permission

`tasks.submit-for-review` — assigned to `client` role.

## Testing the Happy Path

1. Admin sets a question task's `approval_mode` to `'manual'` in task builder
2. Client opens task → sees "Save Draft" and "Submit for Review" buttons
3. Client submits answers → task stays `in_progress` (draft saved)
4. Client clicks "Submit for Review" → task moves to `pending_review`; email sent to assigned reviewer
5. Reviewer sees badge count on dashboard; opens application; sees task awaiting review
6. Reviewer clicks Approve → task becomes `approved`; next task becomes `in_progress`

## Testing Auto-Complete Path

1. Admin sets a question task's `approval_mode` to `'auto'`
2. Client submits answers → task immediately becomes `approved`; next task unlocks
3. No email sent; reviewer sees nothing to do

## Testing Payment Task (Always Manual)

1. Client uploads receipt → task stays `in_progress`
2. Client clicks "Submit for Review" → task moves to `pending_review`; email sent
3. Reviewer approves → next task unlocks

## Key Files

| File | Purpose |
|------|---------|
| `app/Services/Tasks/WorkflowService.php` | Core state transitions |
| `app/Services/Tasks/TaskAnswerService.php` | Auto-complete trigger |
| `app/Mail/TaskSubmittedForReviewMail.php` | Email to reviewer |
| `app/Policies/ApplicationTaskPolicy.php` | Authorization |
| `resources/views/client/tasks/show.blade.php` | Client task UI |
| `resources/views/reviewer/applications/show.blade.php` | Reviewer UI |
