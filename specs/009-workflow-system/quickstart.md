# Quickstart: Workflow System (Core System)

**Date**: 2026-03-21
**Feature**: 009-workflow-system

## What This Phase Delivers

The workflow engine is already functional. This phase closes four specific gaps:
1. Reviewers can re-open rejected task steps
2. Task completion/rejection no longer auto-changes application status
3. Admin applications list shows task completion summary per application
4. Step transitions are atomic (concurrent requests handled safely)

## Prerequisites

- MAMP running with MySQL on port 8889
- `.env` configured with `DB_*`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`
- Dependencies installed: `composer install`
- Database seeded: `php artisan migrate:refresh --seed`

## Running the Application

```bash
php artisan serve
```

## Key Flows to Verify

### Reviewer Advance Task (Happy Path)
1. Log in as reviewer
2. Navigate to `/reviewer/dashboard`
3. Open an application with `in_progress` status
4. Advance the active task with an optional note
5. Verify the task moves to the next step (or `completed` if it was the last)
6. **Verify application status does NOT change to `approved`** (this was removed)

### Reviewer Reject + Re-open (New Flow)
1. Log in as reviewer, open an application
2. Reject the active task with a rejection reason (min 5 chars)
3. Verify task shows status `rejected` and the reason is displayed
4. Verify the "Re-open" button appears; Advance/Reject buttons are gone
5. **Verify application status does NOT change to `rejected`** (this was removed)
6. Click Re-open
7. Verify task returns to `in_progress`, rejection reason is cleared
8. Verify Advance/Reject buttons return; Re-open button is gone

### Client Views Task Progress
1. Log in as the client whose application has the above tasks
2. Navigate to `/client/dashboard` → Tasks tab
3. Verify task statuses reflect the current state (no stale data)
4. Verify rejection reason is visible when task is rejected

### Admin Task Summary
1. Log in as admin
2. Navigate to `/admin/applications`
3. Verify each application row shows a task summary ("X / Y tasks complete")
4. Drill into one application to verify full task breakdown

### Unauthorized Reviewer Guard
1. Log in as reviewer A
2. Attempt to POST directly to `/reviewer/applications/{app_owned_by_reviewer_B}/tasks/{task}/advance`
3. Verify 403 Forbidden is returned

## Running Tests

```bash
php artisan test --filter=WorkflowTest
php artisan test --filter=ReopenTaskTest
php artisan test --filter=ApplicationTaskSummaryTest
php artisan test  # full suite — must stay green
```

## Key Files

| File | Purpose |
|---|---|
| `app/Services/Tasks/WorkflowService.php` | Core engine: advanceTask (modified), rejectTask (modified), reopenTask (new) |
| `app/Http/Controllers/Reviewer/ApplicationController.php` | reopen() action added |
| `app/Http/Requests/Reviewer/ReopenTaskRequest.php` | New Form Request for re-open |
| `app/Policies/ApplicationTaskPolicy.php` | reopen() policy method added |
| `app/Http/Controllers/Admin/ApplicationController.php` | Task count eager-loading added |
| `resources/views/reviewer/applications/show.blade.php` | Re-open button conditional |
| `resources/views/admin/applications/index.blade.php` | Task summary column |
| `lang/en/tasks.php` + `lang/ar/tasks.php` | New lang keys for re-open UI |
| `routes/web.php` | New reopen route |

## Seeding Test Data

```bash
# Seed a specific application's tasks manually
php artisan workflow:seed-tasks --application=1

# Seed all applications missing tasks
php artisan workflow:seed-tasks
```
