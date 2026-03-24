# Quickstart: Task Progression (017)

**Date**: 2026-03-23

---

## Manual Test Scenario — Happy Path

**Goal**: Verify that approving a task unlocks the next one and the final approval closes the workflow.

### Setup

1. Log in as admin, create a visa type with at least 2 workflow tasks.
2. Log in as a client, submit an application for that visa type.
3. Log in as admin, assign a reviewer to the application.

### Steps

1. **Log in as reviewer** and navigate to the application.
2. Approve the first task.
3. **Log in as the client** and open the dashboard Tasks tab.
   - Expected: First task shows "Approved" badge; second task shows "In Progress" badge with a clickable link.
4. **Log in as reviewer** and approve the second (final) task.
5. **Log in as the client** and refresh the dashboard.
   - Expected: A "All Tasks Complete" banner appears. All tasks show "Approved".

---

## Manual Test Scenario — Locked Task Access Attempt

**Goal**: Verify that a client cannot access or submit to a locked task.

### Steps

1. After application creation, the second task is `pending`.
2. As the client, copy the URL of the second task's show page.
3. Navigate directly to that URL.
   - Expected: Redirect to dashboard.
4. Using a REST client (e.g., Postman), POST to `client.tasks.answers.submit` for the pending task.
   - Expected: Redirect response with error flash "This task is not yet available."

---

## Key URLs

| Route | Method | Description |
|---|---|---|
| `reviewer.tasks.approve` | POST | Approves a task → triggers progression |
| `reviewer.tasks.advance` | POST | Advances a task → triggers progression (legacy) |
| `client.tasks.show` | GET | Task detail page (blocks pending) |
| `client.tasks.answers.submit` | POST | Submit answers (blocks pending) |
| `client.tasks.receipt.upload` | POST | Upload receipt (blocks pending) |
| `client.dashboard` (tasks tab) | GET | Shows task list with progression state |

---

## Database Verification

After approving the last task:

```sql
SELECT status FROM visa_applications WHERE id = :appId;
-- Expected: 'workflow_complete'

SELECT status, position FROM application_tasks
WHERE application_id = :appId ORDER BY position;
-- Expected: all rows have status = 'approved'
```
