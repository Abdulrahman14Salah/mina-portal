# Quickstart: Phase 3 — Workflow Engine

**Purpose**: Manual validation scenarios to run after implementation.

---

## Prerequisites

1. Phase 2 is complete and a client account + application exists.
2. Workflow step templates are seeded: `php artisan db:seed --class=WorkflowStepTemplateSeeder`
3. Permissions seeded (run full seeder or diff): `php artisan db:seed --class=RolePermissionSeeder`
4. A reviewer account exists with the `reviewer` role (create via admin panel or tinker).
5. Dev server running: `php artisan serve` + `npm run dev`

---

## Scenario 1 — New Application Gets Workflow Tasks Automatically

**Steps**:
1. Visit `http://localhost:8000/apply` as a guest
2. Complete the onboarding wizard and submit

**Expected**:
- `application_tasks` table has 6 rows for the new application (one per seeded step)
- First task has `status = 'in_progress'`; remaining 5 have `status = 'pending'`
- `visa_applications.status = 'in_progress'` (transitioned from `pending_review`)

---

## Scenario 2 — Reviewer Sees Active Applications Queue

**Steps**:
1. Log in as a user with the `reviewer` role
2. Navigate to `/reviewer/dashboard`

**Expected**:
- The Applications tab is active and shows the application from Scenario 1
- Row displays: reference number, client name, visa type, "Initial Review" (current step), submission date
- Applications with status `approved` or `rejected` are NOT shown

---

## Scenario 3 — Reviewer Advances Application Through All Steps

**Steps**:
1. From the reviewer dashboard, click on the application to open it
2. Verify step 1 ("Application Received") is shown as "In Progress"
3. Click "Mark Complete" (optionally add a note) → submit
4. Verify step 2 is now "In Progress"; step 1 shows "Completed" with a timestamp
5. Repeat for steps 3, 4, 5
6. On step 6 ("Final Decision"), click "Mark Complete"

**Expected**:
- After step 6: `visa_applications.status = 'approved'`
- All 6 tasks show `status = 'completed'` with `completed_at` timestamps
- `audit_logs` table has 6 rows with `event = 'task_completed'` and 1 row with `event = 'application_approved'`
- Application no longer appears in the reviewer's active queue

---

## Scenario 4 — Reviewer Rejects an Application Mid-Workflow

**Steps**:
1. Open a fresh application in the reviewer panel (step 2 active)
2. Click "Reject" with a note: "Insufficient information provided"
3. Confirm rejection

**Expected**:
- `visa_applications.status = 'rejected'`
- The rejected task shows `status = 'rejected'`, `reviewer_note = 'Insufficient information provided'`, and a `completed_at` timestamp
- Remaining tasks remain `status = 'pending'`
- `audit_logs` has a `task_rejected` and `application_rejected` event
- Application no longer appears in the active queue

---

## Scenario 5 — Client Views Their Task Progress

**Steps**:
1. Log in as the client from Scenario 1 (after reviewer has completed 2 steps)
2. Navigate to `/client/dashboard/tasks`

**Expected**:
- 6 tasks shown in order
- Steps 1 and 2 are marked "Completed" with completion dates
- Step 3 is highlighted as "In Progress"
- Steps 4–6 show "Pending"
- No "Mark Complete" or "Reject" buttons visible to the client

---

## Scenario 6 — Dynamic Template: New Step Without Code Deploy

**Steps**:
1. Via tinker or direct DB: `INSERT INTO workflow_step_templates (visa_type_id, position, name, description, is_document_required) VALUES (1, 7, 'Post-Decision Review', 'A secondary review after the decision.', 0)`
2. Submit a new application for that visa type

**Expected**:
- The new application has 7 `application_tasks` rows
- Existing applications from before the INSERT still have 6 tasks (not retroactively modified)

---

## Scenario 7 — Backfill Command for Pre-Phase-3 Applications

**Steps**:
1. Via tinker, check an application created in Phase 2 (before Phase 3 deploy): `App\Models\ApplicationTask::where('application_id', 1)->count()` → returns 0
2. Run: `php artisan workflow:seed-tasks --application=1`
3. Re-check count

**Expected**:
- Count is now 6
- `visa_applications.status` for application ID 1 is now `in_progress` (was `pending_review`)
- Running the command a second time on the same application does nothing (idempotent)

---

## Database Spot-Checks (via `php artisan tinker`)

```php
// Confirm task count and first task status
App\Models\ApplicationTask::where('application_id', 1)->count(); // → 6
App\Models\ApplicationTask::where('application_id', 1)->where('position', 1)->first()->status; // → 'in_progress'

// Confirm application status updated
App\Models\VisaApplication::find(1)->status; // → 'in_progress'

// Confirm approved after all tasks complete
App\Models\VisaApplication::find(1)->status; // → 'approved' (after Scenario 3)

// Confirm audit log entries
DB::table('audit_logs')->where('event', 'task_completed')->count(); // → 6
DB::table('audit_logs')->where('event', 'application_approved')->first(); // → not null
```
