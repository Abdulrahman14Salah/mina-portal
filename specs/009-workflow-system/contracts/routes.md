# Route Contracts: Workflow System (Core System)

**Date**: 2026-03-21
**Feature**: 009-workflow-system

> Routes marked **[CHANGE]** are being modified. Routes marked **[EXISTS]** are already implemented and must not be altered. Routes marked **[NEW]** do not yet exist.

---

## Reviewer Workflow Routes

### POST /reviewer/applications/{application}/tasks/{task}/reopen — Re-open Rejected Task
**Status**: [NEW]
**Name**: `reviewer.applications.tasks.reopen`
**Controller**: `Reviewer\ApplicationController@reopen`
**Middleware**: `auth`, `verified`, `can:tasks.advance`
**Request**: `Reviewer\ReopenTaskRequest`
**Authorization**: Task must belong to application; reviewer must have `tasks.advance` permission; task status must be `rejected`
**On success**: Redirect to `reviewer.applications.show` with success flash
**On failure (wrong status)**: Redirect back with error — task is not in a rejected state
**On failure (auth)**: 403 Forbidden

---

### POST /reviewer/applications/{application}/tasks/{task}/advance — Advance Task Step
**Status**: [EXISTS — behaviour change: no longer auto-updates application status]
**Name**: `reviewer.applications.tasks.advance`
**Controller**: `Reviewer\ApplicationController@advance`
**Middleware**: `auth`, `verified`
**Request**: `AdvanceTaskRequest` — `note` optional, max 2000 chars
**Change**: `WorkflowService::advanceTask()` no longer sets `application.status → 'approved'` on final task completion. Task is marked `completed`; application status unchanged.

---

### POST /reviewer/applications/{application}/tasks/{task}/reject — Reject Task Step
**Status**: [EXISTS — behaviour change: no longer auto-updates application status]
**Name**: `reviewer.applications.tasks.reject`
**Controller**: `Reviewer\ApplicationController@reject`
**Middleware**: `auth`, `verified`
**Request**: `RejectTaskRequest` — `note` required, min 5 chars, max 2000 chars
**Change**: `WorkflowService::rejectTask()` no longer sets `application.status → 'rejected'`. Task is marked `rejected`; application status unchanged.

---

### GET /reviewer/applications/{application} — View Application + Task Panel
**Status**: [EXISTS — UI addition: re-open button when task is rejected]
**Name**: `reviewer.applications.show`
**Controller**: `Reviewer\ApplicationController@show`
**Middleware**: `auth`, `verified`, `can:tasks.view`
**Change**: View must display a "Re-open" form button alongside any task whose status is `rejected`. Advance/Reject buttons must NOT appear for rejected tasks.

---

## Admin Routes

### GET /admin/applications — Applications List with Task Summary
**Status**: [EXISTS — data addition: task count columns]
**Name**: `admin.applications.index`
**Controller**: `Admin\ApplicationController@index`
**Middleware**: `auth`, `verified`, `can:dashboard.admin`
**Change**: Controller must eager-load `tasks_count` and `completed_tasks_count` per application. View must display task summary (e.g., "3 / 5 tasks complete") per application row.

---

## Unchanged Routes (reference)

| Route | Name | Status |
|---|---|---|
| GET /reviewer/dashboard | reviewer.dashboard | EXISTS — no change |
| POST /reviewer/applications/{app}/documents | reviewer.applications.documents.store | EXISTS — no change |
| GET /admin/dashboard | admin.dashboard | EXISTS — no change |
| GET /client/dashboard | client.dashboard | EXISTS — no change |

---

## ReopenTaskRequest Contract

**File**: `app/Http/Requests/Reviewer/ReopenTaskRequest.php`

```
authorize(): true  (authorization handled by policy in controller)
rules(): []        (no additional fields required — action is the submission itself)
```

The request exists solely to satisfy the constitution requirement that all state-changing requests use a Form Request class. No user-submitted data beyond the route parameters is needed.
