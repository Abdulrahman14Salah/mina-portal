# Route Contracts: Reviewer Validation (018)

## New Client Routes

### Submit Task for Review
```
POST /client/tasks/{task}/submit-for-review
Name:        client.tasks.submit-for-review
Middleware:  auth, verified, can:tasks.submit-for-review,task
Controller:  Client\TaskController@submitForReview
Request:     SubmitTaskForReviewRequest
Response:    redirect back with success flash
Error:       redirect back with error flash if task not in_progress
```

*Draft saving (answers, receipts) uses existing routes — no new route needed.*

---

## Updated Reviewer Routes

### Approve Task *(updated: now requires `pending_review` status)*
```
POST /reviewer/applications/{application}/tasks/{task}/approve
Name:        reviewer.applications.tasks.approve
Middleware:  auth, verified
Controller:  Reviewer\ReviewerApplicationController@approve
Request:     ApproveTaskRequest  [note: nullable string]
Response:    redirect back with success flash
Error:       403 if not assigned reviewer or admin; 422 if task not pending_review
```

### Reject Task *(updated: now requires `pending_review` status)*
```
POST /reviewer/applications/{application}/tasks/{task}/reject
Name:        reviewer.applications.tasks.reject
Middleware:  auth, verified
Controller:  Reviewer\ReviewerApplicationController@reject
Request:     RejectTaskRequest  [rejection_reason: required string min:5]
Response:    redirect back with success flash
Error:       403 if unauthorized; 422 if task not pending_review; 422 if reason missing
```

*(Existing reopen, advance routes unchanged.)*

---

## Admin Task Builder Route (extended)

No new route. The existing task builder form gains an `approval_mode` field for question-type tasks. The existing store/update routes handle persistence.

---

## Response Contracts (Blade SSR)

All responses are redirects (no JSON). Flash messages use session:
- `status` key → success message (localized)
- `error` key → error message (localized)

Client sees rejection reason on task page via `$task->rejection_reason`.
Reviewer sees `reviewed_by` name and `reviewed_at` timestamp on task detail.
