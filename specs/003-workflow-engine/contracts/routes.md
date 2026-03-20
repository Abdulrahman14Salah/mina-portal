# Route Contracts: Phase 3 — Workflow Engine

**Date**: 2026-03-19

All routes are web routes (Blade, CSRF enabled, session-based auth).

---

## Reviewer Dashboard Routes

### GET `/reviewer/dashboard/{tab?}`
- **Controller**: `Reviewer\DashboardController@show`
- **Purpose**: Render the tabbed reviewer dashboard; `{tab}` defaults to `applications`
- **Middleware**: `['auth', 'verified']`
- **Authorization**: `$user->can('tasks.view')` — reviewers and admins only; others get 403
- **Valid tab values**: `applications` (Phase 3); additional tabs in Phase 7
- **Invalid tab value**: silently falls back to `applications`
- **Data passed to view**: `$activeTab`
- **Named route**: `reviewer.dashboard`
- **Breaking change**: Replaces the Phase 1 stub `Route::get('/reviewer/dashboard', fn() => view('dashboard.reviewer'))->middleware('can:dashboard.reviewer')->name('reviewer.dashboard')`

---

## Reviewer Application Routes

### GET `/reviewer/applications/{application}`
- **Controller**: `Reviewer\ApplicationController@show`
- **Purpose**: Display a specific application's details and its ordered task list with advance/reject controls on the active task
- **Middleware**: `['auth', 'verified']`
- **Authorization**: `$user->can('tasks.view')` — reviewers and admins only
- **Route model binding**: `{application}` → `VisaApplication` by `id`
- **Data passed to view**: `$application` (with `visaType`, `tasks` eager-loaded ordered by `position`), `$activeTask` (the `in_progress` task, or `null`)
- **Named route**: `reviewer.applications.show`

### POST `/reviewer/applications/{application}/tasks/{task}/advance`
- **Controller**: `Reviewer\ApplicationController@advance`
- **Purpose**: Mark the current `in_progress` task as `completed`; auto-activate next task (or approve application if final)
- **Middleware**: `['auth', 'verified']`
- **Authorization**: `$this->authorize('advance', $task)` → `ApplicationTaskPolicy::advance` checks `$user->can('tasks.advance')`
- **Request**: `Reviewer\AdvanceTaskRequest` (`note`: nullable, string, max:2000)
- **Validation guard**: If `$task->status !== 'in_progress'`, abort with 422
- **Success response**: `redirect()->route('reviewer.applications.show', $application)->with('success', __('reviewer.task_advanced'))`
- **Named route**: `reviewer.applications.tasks.advance`

### POST `/reviewer/applications/{application}/tasks/{task}/reject`
- **Controller**: `Reviewer\ApplicationController@reject`
- **Purpose**: Mark the current `in_progress` task as `rejected`; set application status to `rejected`
- **Middleware**: `['auth', 'verified']`
- **Authorization**: `$this->authorize('reject', $task)` → `ApplicationTaskPolicy::reject` checks `$user->can('tasks.reject')`
- **Request**: `Reviewer\RejectTaskRequest` (`note`: nullable, string, max:2000)
- **Validation guard**: If `$task->status !== 'in_progress'`, abort with 422
- **Success response**: `redirect()->route('reviewer.applications.show', $application)->with('success', __('reviewer.task_rejected'))`
- **Named route**: `reviewer.applications.tasks.reject`

---

## Client Dashboard (modified)

### GET `/client/dashboard/{tab?}` (existing route — no route change)
- The `tasks` tab partial (`resources/views/client/dashboard/tabs/tasks.blade.php`) is **updated** from its empty-state stub to render the application's task list.
- **Data change**: `DashboardController@show` now eager-loads `tasks` on the application: `VisaApplication::with(['visaType', 'tasks' => fn($q) => $q->orderBy('position')])`
- No route, controller signature, or middleware changes.

---

## Middleware Applied

| Middleware | Where applied | Purpose |
|---|---|---|
| `auth` | All reviewer routes | Requires login |
| `verified` | All reviewer routes | Requires email verification (currently no-op) |
| `can:tasks.view` | Reviewer dashboard + application detail | Permission gate — reviewers and admins only |

---

## Route Registration in `routes/web.php`

```php
// Reviewer dashboard (replaces Phase 1 stub)
Route::middleware(['auth', 'verified'])->prefix('reviewer')->name('reviewer.')->group(function () {
    Route::get('/dashboard/{tab?}', [Reviewer\DashboardController::class, 'show'])
        ->middleware('can:tasks.view')
        ->name('dashboard');

    Route::get('/applications/{application}', [Reviewer\ApplicationController::class, 'show'])
        ->middleware('can:tasks.view')
        ->name('applications.show');

    Route::post('/applications/{application}/tasks/{task}/advance', [Reviewer\ApplicationController::class, 'advance'])
        ->name('applications.tasks.advance');

    Route::post('/applications/{application}/tasks/{task}/reject', [Reviewer\ApplicationController::class, 'reject'])
        ->name('applications.tasks.reject');
});
```

**Note**: The Phase 1 stub `Route::get('/reviewer/dashboard', fn () => view('dashboard.reviewer'))->middleware('can:dashboard.reviewer')->name('reviewer.dashboard')` **MUST** be removed and replaced by the group above. The route name `reviewer.dashboard` is preserved.
