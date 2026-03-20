# Tasks: Phase 3 — Workflow Engine

**Input**: Design documents from `/specs/003-workflow-engine/`
**Prerequisites**: plan.md ✅, spec.md ✅, research.md ✅, data-model.md ✅, contracts/routes.md ✅, quickstart.md ✅
**Branch**: `003-workflow-engine`
**Stack**: PHP 8.2+ / Laravel 11 / Breeze (Blade) / Alpine.js v3 / spatie/laravel-permission v6+ / MySQL (dev) / SQLite in-memory (tests)

> **Context for implementors**: This is Phase 3 of a Visa Application Client Portal. Phases 1 and 2 are complete. Phase 3 adds: (1) `workflow_step_templates` — a database table of ordered step blueprints per visa type; (2) `application_tasks` — instance records created from those blueprints when an application is submitted; (3) a reviewer panel for advancing/rejecting tasks; (4) an update to the client Tasks tab; (5) an artisan command for backfilling existing applications. All controllers delegate to `WorkflowService`. No hardcoded step names in PHP. No inline `$request->validate()`. No `$guarded = []`. All strings via `__()`.
>
> **⚠️ CRITICAL: Phase 2 file modifications required**
> - `app/Services/Client/OnboardingService.php` — inject `WorkflowService`, call `seedTasksForApplication()` AFTER the main `DB::transaction()` returns
> - `app/Http/Controllers/Client/DashboardController.php` — eager-load `tasks` relationship on the application query
> - `database/seeders/RolePermissionSeeder.php` — add 3 new permissions and assign to reviewer/admin roles
> - `database/seeders/DatabaseSeeder.php` — add `WorkflowStepTemplateSeeder::class` call
> - `routes/web.php` — replace the Phase 1 reviewer dashboard stub with the new tabbed route group
>
> **⚠️ Lang file pattern**: Following Phase 1/2 convention, real content lives in `resources/lang/{locale}/{file}.php`. A proxy file in `lang/{locale}/{file}.php` does `return require resource_path('lang/{locale}/{file}.php')`. Both files must be created for each new lang file.

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Which user story ([US1]–[US3])

---

## Phase 1: Setup (Database Foundation)

**Purpose**: Create the two new tables, seed step templates, and update permissions before any application code is written.

- [X] T001 Create the `workflow_step_templates` migration: run `php artisan make:migration create_workflow_step_templates_table`. In `up()`, define: `$table->id()`, `$table->foreignId('visa_type_id')->constrained()->cascadeOnDelete()`, `$table->unsignedSmallInteger('position')`, `$table->string('name', 150)`, `$table->text('description')->nullable()`, `$table->boolean('is_document_required')->default(false)`, `$table->timestamps()`. Add unique constraint: `$table->unique(['visa_type_id', 'position'])`. In `down()`, call `Schema::dropIfExists('workflow_step_templates')`. Run `php artisan migrate`.

- [X] T002 Create the `application_tasks` migration: run `php artisan make:migration create_application_tasks_table`. In `up()`, define in this exact order: `$table->id()`, `$table->foreignId('application_id')->constrained('visa_applications')->cascadeOnDelete()`, `$table->foreignId('workflow_step_template_id')->constrained()->restrictOnDelete()`, `$table->unsignedSmallInteger('position')`, `$table->string('name', 150)`, `$table->text('description')->nullable()`, `$table->string('status', 20)->default('pending')`, `$table->text('reviewer_note')->nullable()`, `$table->timestamp('completed_at')->nullable()`, `$table->timestamps()`. In `down()`, call `Schema::dropIfExists('application_tasks')`. **Important**: this migration must run AFTER `workflow_step_templates`. Run `php artisan migrate`.

- [X] T003 [P] Create `database/seeders/WorkflowStepTemplateSeeder.php`: namespace `Database\Seeders`. Import `App\Models\VisaType` and `App\Models\WorkflowStepTemplate`. In `run()`, define the 6 steps as an array: `[['position' => 1, 'name' => 'Application Received', 'description' => 'Your application has been received and is awaiting initial review.'], ['position' => 2, 'name' => 'Initial Review', 'description' => 'Our team is reviewing your submitted application details.'], ['position' => 3, 'name' => 'Document Request', 'description' => 'We are preparing a list of required documents for your application.'], ['position' => 4, 'name' => 'Document Review', 'description' => 'Your submitted documents are under review by our team.'], ['position' => 5, 'name' => 'Assessment', 'description' => 'Your application is being assessed for a final recommendation.'], ['position' => 6, 'name' => 'Final Decision', 'description' => 'A final decision is being made on your visa application.']]`. Then loop over `VisaType::all()`, and for each visa type loop over the 6 steps and call `WorkflowStepTemplate::firstOrCreate(['visa_type_id' => $visaType->id, 'position' => $step['position']], ['name' => $step['name'], 'description' => $step['description'], 'is_document_required' => false])`. Result: 18 rows total (6 steps × 3 visa types).

- [X] T004 Update `database/seeders/DatabaseSeeder.php`: add `$this->call(WorkflowStepTemplateSeeder::class)` as the fourth call in `run()`, after `VisaTypeSeeder::class`. Run `php artisan db:seed --class=WorkflowStepTemplateSeeder` to verify 18 rows in `workflow_step_templates`.

- [X] T005 Update `database/seeders/RolePermissionSeeder.php`: In the `$permissions` array, add three new entries: `'tasks.view'`, `'tasks.advance'`, `'tasks.reject'`. After the existing `$admin->givePermissionTo($permissions)` line (which gives admin ALL permissions), add `$reviewer->givePermissionTo(['dashboard.reviewer', 'tasks.view', 'tasks.advance', 'tasks.reject'])`. The `$client` line stays unchanged. Do NOT re-run the seeder yet — it will run via `migrate:fresh --seed` in Phase 6.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Models, service, policy, form requests, modified OnboardingService, and language files — everything all user stories depend on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T006 [P] Create `app/Models/WorkflowStepTemplate.php`: namespace `App\Models`. Class extends `Illuminate\Database\Eloquent\Model`. Add `use HasFactory`. Set `protected $fillable = ['visa_type_id', 'name', 'description', 'position', 'is_document_required']`. Set `protected $casts = ['is_document_required' => 'boolean', 'position' => 'integer']`. Add relationships: `public function visaType(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(VisaType::class); }` and `public function applicationTasks(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ApplicationTask::class); }`.

- [X] T007 Create `app/Models/ApplicationTask.php`: namespace `App\Models`. Class extends `Illuminate\Database\Eloquent\Model`. Add `use HasFactory`. Set `protected $fillable = ['application_id', 'workflow_step_template_id', 'position', 'name', 'description', 'status', 'reviewer_note', 'completed_at']`. Set `protected $casts = ['position' => 'integer', 'completed_at' => 'datetime']`. Add relationships: `public function application(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(VisaApplication::class, 'application_id'); }` and `public function template(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(WorkflowStepTemplate::class, 'workflow_step_template_id'); }`. Add all required `use` statements.

- [X] T008 Create `app/Services/Tasks/WorkflowService.php`: namespace `App\Services\Tasks`. Constructor-inject `\App\Services\Auth\AuditLogService $auditLog`. Implement three public methods:

  **Method 1 — `seedTasksForApplication(\App\Models\VisaApplication $application): void`**: (1) Load templates: `$templates = \App\Models\WorkflowStepTemplate::where('visa_type_id', $application->visa_type_id)->orderBy('position')->get()`. (2) If `$templates->isEmpty()`, return immediately (no tasks, status stays `pending_review`). (3) Wrap the following in `\Illuminate\Support\Facades\DB::transaction()`: (a) foreach template, call `\App\Models\ApplicationTask::create(['application_id' => $application->id, 'workflow_step_template_id' => $template->id, 'position' => $template->position, 'name' => $template->name, 'description' => $template->description, 'status' => 'pending'])` — collect created tasks into `$tasks` array. (b) Set first task to `in_progress`: `$tasks[0]->update(['status' => 'in_progress'])`. (c) Update application: `$application->update(['status' => 'in_progress'])`. (4) AFTER the transaction (not inside it), call `$this->auditLog->log('workflow_started', $application->user, ['reference' => $application->reference_number])`.

  **Method 2 — `advanceTask(\App\Models\ApplicationTask $task, ?string $note): void`**: (1) Guard: `if ($task->status !== 'in_progress') { throw new \InvalidArgumentException('Only an in_progress task can be advanced.'); }`. (2) Wrap in `DB::transaction()`: (a) `$task->update(['status' => 'completed', 'completed_at' => now(), 'reviewer_note' => $note])`. (b) Find next: `$nextTask = \App\Models\ApplicationTask::where('application_id', $task->application_id)->where('position', $task->position + 1)->first()`. (c) If `$nextTask` exists: `$nextTask->update(['status' => 'in_progress'])`. (d) If no `$nextTask`: `$task->application->update(['status' => 'approved'])` — then call `$this->auditLog->log('application_approved', auth()->user(), ['reference' => $task->application->reference_number])`. (3) Always call `$this->auditLog->log('task_completed', auth()->user(), ['task' => $task->name, 'reference' => $task->application->reference_number])`.

  **Method 3 — `rejectTask(\App\Models\ApplicationTask $task, ?string $note): void`**: (1) Guard: `if ($task->status !== 'in_progress') { throw new \InvalidArgumentException('Only an in_progress task can be rejected.'); }`. (2) Wrap in `DB::transaction()`: (a) `$task->update(['status' => 'rejected', 'completed_at' => now(), 'reviewer_note' => $note])`. (b) `$task->application->update(['status' => 'rejected'])`. (3) Call `$this->auditLog->log('task_rejected', auth()->user(), ['task' => $task->name, 'reference' => $task->application->reference_number])`. (4) Call `$this->auditLog->log('application_rejected', auth()->user(), ['reference' => $task->application->reference_number])`.

  Add all required `use` statements at the top.

- [X] T009 [P] Create `app/Policies/ApplicationTaskPolicy.php`: namespace `App\Policies`. Add `use App\Models\User; use App\Models\ApplicationTask`. Implement: `public function advance(User $user, ApplicationTask $task): bool { return $user->can('tasks.advance'); }` and `public function reject(User $user, ApplicationTask $task): bool { return $user->can('tasks.reject'); }`.

- [X] T010 Register `ApplicationTaskPolicy` in `app/Providers/AppServiceProvider.php`: add `use App\Models\ApplicationTask; use App\Policies\ApplicationTaskPolicy;` imports, then in `boot()` add `Gate::policy(ApplicationTask::class, ApplicationTaskPolicy::class)` alongside the existing policy registrations.

- [X] T011 [P] Create `app/Http/Requests/Reviewer/AdvanceTaskRequest.php`: namespace `App\Http\Requests\Reviewer`. Extends `Illuminate\Foundation\Http\FormRequest`. `authorize()` returns `true`. `rules()` returns: `['note' => ['nullable', 'string', 'max:2000']]`.

- [X] T012 [P] Create `app/Http/Requests/Reviewer/RejectTaskRequest.php`: namespace `App\Http\Requests\Reviewer`. Extends `Illuminate\Foundation\Http\FormRequest`. `authorize()` returns `true`. `rules()` returns: `['note' => ['nullable', 'string', 'max:2000']]`.

- [X] T013 Modify `app/Services/Client/OnboardingService.php`: (1) Add `use App\Services\Tasks\WorkflowService;` import. (2) Add `WorkflowService $workflowService` as a second constructor parameter: `public function __construct(private AuditLogService $auditLog, private WorkflowService $workflowService) {}`. (3) At the END of the `handle()` method, AFTER the `DB::transaction()` closure has returned `$application` and BEFORE the final `return`, add: `$this->workflowService->seedTasksForApplication($application);`. The final method body should be: `$application = DB::transaction(function () use ($request) { ... }); $this->workflowService->seedTasksForApplication($application); return $application;`. This ensures task seeding is outside the main transaction (if no templates exist, application creation still succeeds).

- [X] T014 [P] Create `resources/lang/en/tasks.php`: return an array with exactly these keys: `'status_pending' => 'Pending'`, `'status_in_progress' => 'In Progress'`, `'status_completed' => 'Completed'`, `'status_rejected' => 'Rejected'`, `'no_tasks' => 'No workflow tasks have been assigned to your application yet.'`, `'current_step' => 'Current Step'`, `'completed_on' => 'Completed on :date'`, `'step_number' => 'Step :number'`.

- [X] T015 [P] Create `resources/lang/ar/tasks.php`: return the same keys as T014 with Arabic translations: `'status_pending' => 'في الانتظار'`, `'status_in_progress' => 'قيد التنفيذ'`, `'status_completed' => 'مكتمل'`, `'status_rejected' => 'مرفوض'`, `'no_tasks' => 'لم يتم تعيين مهام سير العمل لطلبك بعد.'`, `'current_step' => 'الخطوة الحالية'`, `'completed_on' => 'اكتمل في :date'`, `'step_number' => 'الخطوة :number'`.

- [X] T016 [P] Create `lang/en/tasks.php`: the file must contain exactly one line of PHP: `return require resource_path('lang/en/tasks.php');`. This is the Laravel 11 lang path proxy pattern already used in this project.

- [X] T017 [P] Create `lang/ar/tasks.php`: the file must contain exactly one line of PHP: `return require resource_path('lang/ar/tasks.php');`.

- [X] T018 [P] Create `resources/lang/en/reviewer.php`: return an array with exactly these keys: `'dashboard_title' => 'Reviewer Dashboard'`, `'tab_applications' => 'Applications'`, `'active_applications' => 'Active Applications'`, `'no_active_applications' => 'No active applications in the queue.'`, `'reference' => 'Reference'`, `'client_name' => 'Client'`, `'visa_type' => 'Visa Type'`, `'current_step' => 'Current Step'`, `'submitted' => 'Submitted'`, `'view' => 'View'`, `'application_detail' => 'Application Detail'`, `'workflow_progress' => 'Workflow Progress'`, `'advance_task' => 'Advance Task'`, `'reject_task' => 'Reject Application'`, `'mark_complete' => 'Mark as Complete'`, `'reject' => 'Reject'`, `'note_label' => 'Note (optional)'`, `'note_placeholder' => 'Add a note for the client record…'`, `'task_advanced' => 'Task marked as complete. Application has been advanced.'`, `'task_rejected' => 'Task rejected. Application has been marked as rejected.'`, `'application_approved' => 'All steps complete. Application has been approved.'`, `'client_label' => 'Client'`, `'status_label' => 'Status'`, `'visa_type_label' => 'Visa Type'`.

- [X] T019 [P] Create `resources/lang/ar/reviewer.php`: return the same keys as T018 with Arabic translations: `'dashboard_title' => 'لوحة تحكم المراجع'`, `'tab_applications' => 'الطلبات'`, `'active_applications' => 'الطلبات النشطة'`, `'no_active_applications' => 'لا توجد طلبات نشطة في قائمة الانتظار.'`, `'reference' => 'المرجع'`, `'client_name' => 'العميل'`, `'visa_type' => 'نوع التأشيرة'`, `'current_step' => 'الخطوة الحالية'`, `'submitted' => 'تاريخ التقديم'`, `'view' => 'عرض'`, `'application_detail' => 'تفاصيل الطلب'`, `'workflow_progress' => 'تقدم سير العمل'`, `'advance_task' => 'تقديم المهمة'`, `'reject_task' => 'رفض الطلب'`, `'mark_complete' => 'تعليم كمكتمل'`, `'reject' => 'رفض'`, `'note_label' => 'ملاحظة (اختياري)'`, `'note_placeholder' => 'أضف ملاحظة لسجل العميل…'`, `'task_advanced' => 'تم تعليم المهمة كمكتملة. تم تقديم الطلب.'`, `'task_rejected' => 'تم رفض المهمة. تم تعليم الطلب كمرفوض.'`, `'application_approved' => 'اكتملت جميع الخطوات. تمت الموافقة على الطلب.'`, `'client_label' => 'العميل'`, `'status_label' => 'الحالة'`, `'visa_type_label' => 'نوع التأشيرة'`.

- [X] T020 [P] Create `lang/en/reviewer.php`: one line — `return require resource_path('lang/en/reviewer.php');`

- [X] T021 [P] Create `lang/ar/reviewer.php`: one line — `return require resource_path('lang/ar/reviewer.php');`

**Checkpoint**: Run `php artisan migrate:fresh --seed`. Confirm: `workflow_step_templates` has 18 rows (6 steps × 3 visa types); `application_tasks` table exists; `roles` has 3 rows; reviewer role has `tasks.view`, `tasks.advance`, `tasks.reject` permissions.

---

## Phase 3: User Story 1 — Reviewer Advances Application Through Workflow Steps (Priority: P1) 🎯 MVP

**Goal**: A reviewer visits `/reviewer/dashboard`, sees all active applications, opens one, and marks its current task complete step-by-step through to approval. They can also reject any in-progress task.

**Independent Test**: Seed one application in `in_progress` with 6 tasks (first = `in_progress`). Log in as reviewer. Visit `/reviewer/dashboard` → see the application. Open it. Mark step 1 complete → step 2 becomes In Progress. Advance through all 6 → application status becomes `approved`. Verify audit log.

### Implementation for User Story 1

- [X] T022 [US1] Create `app/Http/Controllers/Reviewer/DashboardController.php`: namespace `App\Http\Controllers\Reviewer`. Extends `\App\Http\Controllers\Controller`. One method: `public function show(string $tab = 'applications'): \Illuminate\View\View`. Inside: (1) Validate tab: `$validTabs = ['applications']`. If not in list, `$tab = 'applications'`. (2) Return `view('reviewer.dashboard.index', compact('tab'))`.

- [X] T023 [US1] Create `resources/views/reviewer/dashboard/index.blade.php`: use `<x-app-layout>`. In `<x-slot name="header">`, display `<h2>{{ __('reviewer.dashboard_title') }}</h2>`. Below the header slot, render tab navigation: a `<nav>` with one link for now: `<a href="{{ route('reviewer.dashboard', ['tab' => 'applications']) }}" class="rounded-md px-4 py-2 text-sm font-medium {{ $tab === 'applications' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">{{ __('reviewer.tab_applications') }}</a>`. Below the nav, include the active tab partial: `@include('reviewer.dashboard.tabs.' . $tab)`. Use the same outer layout structure as `resources/views/client/dashboard/index.blade.php` for consistency (max-w-7xl container, shadow-sm nav card, py-12 main container).

- [X] T024 [US1] Create `resources/views/reviewer/dashboard/tabs/applications.blade.php`: this partial receives no explicit variables (it queries inside the view — NO! — follow constitution Principle II: no DB queries in Blade). Instead, **update `DashboardController@show` (the reviewer one) to pass `$applications`**: modify `Reviewer\DashboardController@show` to load applications when `$tab === 'applications'`: `$applications = \App\Models\VisaApplication::with(['visaType', 'user', 'tasks' => fn($q) => $q->where('status', 'in_progress')])->whereIn('status', ['pending_review', 'in_progress'])->orderBy('created_at')->get()`. Pass it to the view: `view('reviewer.dashboard.index', compact('tab', 'applications'))`. Then in the partial, render: (1) `<h3>{{ __('reviewer.active_applications') }}</h3>`. (2) `@if($applications->isEmpty())` show `<p>{{ __('reviewer.no_active_applications') }}</p>`. (3) `@else` render a table with columns: Reference, Client, Visa Type, Current Step, Submitted, Action. For each application: `$currentStep = $application->tasks->first()` (the eager-loaded in_progress task). Display `$application->reference_number`, `$application->user->name`, `$application->visaType->name`, `$currentStep?->name ?? '—'`, `$application->created_at->format('d M Y')`, and a link `<a href="{{ route('reviewer.applications.show', $application) }}">{{ __('reviewer.view') }}</a>`. Use `__('reviewer.*')` keys for all column headers.

- [X] T025 [US1] Create `app/Http/Controllers/Reviewer/ApplicationController.php`: namespace `App\Http\Controllers\Reviewer`. Extends `\App\Http\Controllers\Controller`. Constructor-inject `\App\Services\Tasks\WorkflowService $workflowService`. Implement three methods:

  **`show(\App\Models\VisaApplication $application): \Illuminate\View\View`**: (1) `abort_if(!auth()->user()->can('tasks.view'), 403)`. (2) `$application->loadMissing(['visaType', 'user', 'tasks' => fn($q) => $q->orderBy('position')])`. (3) `$activeTask = $application->tasks->firstWhere('status', 'in_progress')`. (4) Return `view('reviewer.applications.show', compact('application', 'activeTask'))`.

  **`advance(\App\Http\Requests\Reviewer\AdvanceTaskRequest $request, \App\Models\VisaApplication $application, \App\Models\ApplicationTask $task): \Illuminate\Http\RedirectResponse`**: (1) `abort_if($task->application_id !== $application->id, 404)`. (2) `$this->authorize('advance', $task)`. (3) `$this->workflowService->advanceTask($task, $request->input('note'))`. (4) `$successMsg = $application->fresh()->status === 'approved' ? __('reviewer.application_approved') : __('reviewer.task_advanced')`. (5) Return `redirect()->route('reviewer.applications.show', $application)->with('success', $successMsg)`.

  **`reject(\App\Http\Requests\Reviewer\RejectTaskRequest $request, \App\Models\VisaApplication $application, \App\Models\ApplicationTask $task): \Illuminate\Http\RedirectResponse`**: (1) `abort_if($task->application_id !== $application->id, 404)`. (2) `$this->authorize('reject', $task)`. (3) `$this->workflowService->rejectTask($task, $request->input('note'))`. (4) Return `redirect()->route('reviewer.applications.show', $application)->with('success', __('reviewer.task_rejected'))`.

  Add all `use` imports at the top.

- [X] T026 [US1] Create `resources/views/reviewer/applications/show.blade.php`: use `<x-app-layout>`. In `<x-slot name="header">`, show `<h2>{{ __('reviewer.application_detail') }}: {{ $application->reference_number }}</h2>`. In the main content (py-12 container, max-w-4xl):

  (1) **Info card**: white rounded-lg p-6 shadow-sm. Show: `{{ __('reviewer.client_label') }}: {{ $application->user->name }}`, `{{ __('reviewer.visa_type_label') }}: {{ $application->visaType->name }}`, `{{ __('reviewer.status_label') }}: {{ __('client.status_' . $application->status) }}` (reuses the Phase 2 client lang key).

  (2) **Success flash**: `@if(session('success')) <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div> @endif`.

  (3) **Task list**: heading `<h3>{{ __('reviewer.workflow_progress') }}</h3>`. Loop: `@foreach($application->tasks->sortBy('position') as $task)`. For each task render a card with:
  - **Completed** (`$task->status === 'completed'`): green left border, task name (bold), `{{ __('tasks.status_completed') }}`, `{{ __('tasks.completed_on', ['date' => $task->completed_at->format('d M Y')]) }}`, reviewer note if set.
  - **In Progress** (`$task->status === 'in_progress'`): blue/indigo highlighted card, task name + description. Show the **Advance form**: `<form method="POST" action="{{ route('reviewer.applications.tasks.advance', [$application, $task]) }}">@csrf<textarea name="note" placeholder="{{ __('reviewer.note_placeholder') }}" class="..."></textarea><button type="submit" class="...">{{ __('reviewer.mark_complete') }}</button></form>`. Show the **Reject form**: `<form method="POST" action="{{ route('reviewer.applications.tasks.reject', [$application, $task]) }}">@csrf<textarea name="note" placeholder="{{ __('reviewer.note_placeholder') }}" class="..."></textarea><button type="submit" class="... bg-red-600 ...">{{ __('reviewer.reject') }}</button></form>`. Only show these forms when `$activeTask` is not null and `$task->id === $activeTask->id`.
  - **Pending** (`$task->status === 'pending'`): grey card, task name + description, `{{ __('tasks.status_pending') }}` badge.
  - **Rejected** (`$task->status === 'rejected'`): red left border, task name, rejected badge, reviewer note if set.

  Use `__('tasks.*')` for status labels and `__('reviewer.*')` for action labels.

- [X] T027 [US1] Update `routes/web.php`: (1) **Remove** the Phase 1 stub line `Route::get('/reviewer/dashboard', fn () => view('dashboard.reviewer'))->middleware('can:dashboard.reviewer')->name('reviewer.dashboard')` from the `['auth', 'verified']` middleware group. (2) Add the reviewer route group (can be added at the bottom, inside or outside the existing `['auth', 'verified']` group — add it as a new group): `Route::middleware(['auth', 'verified'])->prefix('reviewer')->name('reviewer.')->group(function () { Route::get('/dashboard/{tab?}', [\App\Http\Controllers\Reviewer\DashboardController::class, 'show'])->middleware('can:tasks.view')->name('dashboard'); Route::get('/applications/{application}', [\App\Http\Controllers\Reviewer\ApplicationController::class, 'show'])->middleware('can:tasks.view')->name('applications.show'); Route::post('/applications/{application}/tasks/{task}/advance', [\App\Http\Controllers\Reviewer\ApplicationController::class, 'advance'])->name('applications.tasks.advance'); Route::post('/applications/{application}/tasks/{task}/reject', [\App\Http\Controllers\Reviewer\ApplicationController::class, 'reject'])->name('applications.tasks.reject'); });`. Add `use App\Http\Controllers\Reviewer\DashboardController as ReviewerDashboardController; use App\Http\Controllers\Reviewer\ApplicationController as ReviewerApplicationController;` to the imports at the top, OR use the fully-qualified class names inline as shown.

- [X] T028 [P] [US1] Write `tests/Feature/Reviewer/WorkflowTest.php`: namespace `Tests\Feature\Reviewer`. Use `RefreshDatabase`. In `setUp()`, call `$this->seed(\Database\Seeders\RolePermissionSeeder::class)` and `$this->seed(\Database\Seeders\VisaTypeSeeder::class)` and `$this->seed(\Database\Seeders\WorkflowStepTemplateSeeder::class)`. Define a helper `makeReviewer(): \App\Models\User` that creates a User and assigns role 'reviewer'. Define a helper `makeOnboardedApplication(): \App\Models\VisaApplication` that creates a User with role 'client' and a VisaApplication with all required fields (copy pattern from Phase 2 DashboardTest helper), then calls `app(\App\Services\Tasks\WorkflowService::class)->seedTasksForApplication($application)` and returns `$application->fresh()`. Write tests:
  (1) `test_reviewer_sees_active_applications_queue()` — actingAs reviewer, get `/reviewer/dashboard`, assertOk, assertSee reference number.
  (2) `test_reviewer_can_view_application_detail()` — actingAs reviewer, get `/reviewer/applications/{id}`, assertOk, assertSee task name.
  (3) `test_reviewer_can_advance_task()` — actingAs reviewer, post to advance first task with note, assertRedirect, assert first task is 'completed', assert second task is 'in_progress'.
  (4) `test_all_tasks_completed_sets_application_approved()` — advance through all 6 tasks, assert application status is 'approved'.
  (5) `test_reviewer_can_reject_task()` — actingAs reviewer, reject in-progress task, assert task status 'rejected', assert application status 'rejected'.
  (6) `test_client_cannot_advance_tasks()` — actingAs a client user, post to advance route, assert 403.
  (7) `test_unauthenticated_redirected_from_reviewer_dashboard()` — get '/reviewer/dashboard', assertRedirect(route('login')).

**Checkpoint**: `php artisan serve`, submit a new application via `/apply`. Visit `/reviewer/dashboard` as reviewer → see the application. Open it → 6 steps visible. Advance through all 6 → application status `approved`. Check `audit_logs` for `task_completed` and `application_approved` events.

---

## Phase 4: User Story 2 — Client Tracks Their Application Progress (Priority: P2)

**Goal**: A client logs in and visits the Tasks tab to see all 6 workflow steps with their statuses.

**Independent Test**: Submit an application (auto-seeds 6 tasks). Log in as the client. Visit `/client/dashboard/tasks` → see 6 ordered steps. The first is "In Progress". No advance/reject buttons visible.

### Implementation for User Story 2

- [X] T029 [US2] Modify `app/Http/Controllers/Client/DashboardController.php`: find the line `$application = VisaApplication::with('visaType')->where('user_id', auth()->id())->first()` and change it to `$application = VisaApplication::with(['visaType', 'tasks' => fn($q) => $q->orderBy('position')])->where('user_id', auth()->id())->first()`. This is a one-line change that eager-loads the ordered task list onto the application passed to all dashboard tab views.

- [X] T030 [US2] Replace the content of `resources/views/client/dashboard/tabs/tasks.blade.php` (currently an empty-state placeholder from Phase 2) with a real task list. The new content: `@if($application->tasks->isEmpty()) <div class="rounded-lg bg-white p-10 text-center shadow-sm"><p class="text-gray-500">{{ __('tasks.no_tasks') }}</p></div> @else <div class="space-y-3">@foreach($application->tasks->sortBy('position') as $task)<div class="rounded-lg bg-white p-6 shadow-sm {{ $task->status === 'in_progress' ? 'border-l-4 border-indigo-500' : '' }} {{ $task->status === 'completed' ? 'border-l-4 border-green-500' : '' }} {{ $task->status === 'rejected' ? 'border-l-4 border-red-500' : '' }}"><div class="flex items-start justify-between gap-4"><div><p class="text-xs text-gray-400 mb-1">{{ __('tasks.step_number', ['number' => $task->position]) }}</p><h4 class="font-semibold text-gray-900">{{ $task->name }}</h4>@if($task->description)<p class="mt-1 text-sm text-gray-500">{{ $task->description }}</p>@endif@if($task->status === 'completed' && $task->completed_at)<p class="mt-2 text-xs text-green-600">{{ __('tasks.completed_on', ['date' => $task->completed_at->format('d M Y')]) }}</p>@endif</div><span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium {{ $task->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}{{ $task->status === 'in_progress' ? 'bg-indigo-100 text-indigo-700' : '' }}{{ $task->status === 'pending' ? 'bg-gray-100 text-gray-600' : '' }}{{ $task->status === 'rejected' ? 'bg-red-100 text-red-700' : '' }}">{{ __('tasks.status_' . $task->status) }}</span></div></div>@endforeach</div>@endif`.

**Checkpoint**: Log in as a client who has an application with 2 completed steps and 1 in-progress step. Visit `/client/dashboard/tasks` → see all 6 steps with correct status badges. No action buttons.

---

## Phase 5: User Story 3 — Database-Driven Templates (Priority: P3)

**Goal**: Workflow steps are database-driven per visa type. Existing applications can be backfilled via artisan command.

**Independent Test**: `php artisan workflow:seed-tasks` seeds tasks on existing taskless applications. Adding a DB row to `workflow_step_templates` without code deploy gives new applications more tasks.

### Implementation for User Story 3

- [X] T031 [US3] Create `app/Console/Commands/SeedApplicationWorkflowTasks.php`: namespace `App\Console\Commands`. Class extends `\Illuminate\Console\Command`. Properties: `protected $signature = 'workflow:seed-tasks {--application= : Seed tasks for a specific application ID only}'`; `protected $description = 'Seed workflow tasks onto existing applications that have no tasks yet'`. Constructor-inject `\App\Services\Tasks\WorkflowService $workflowService`. Implement `handle(): int`: (1) If `--application` option is set: load `\App\Models\VisaApplication::find($this->option('application'))`. If null, `$this->error('Application not found.')` and return `1`. Else call `$this->workflowService->seedTasksForApplication($application)`, output `$this->info("Seeded tasks for {$application->reference_number}.")`, return `0`. (2) If no option: query `\App\Models\VisaApplication::whereDoesntHave('tasks')->get()`. For each, call `$this->workflowService->seedTasksForApplication($app)` inside a try/catch — on success `$this->info("Seeded: {$app->reference_number}")`, on exception `$this->warn("Skipped {$app->reference_number}: " . $e->getMessage())`. After loop: `$this->info("Done. {$count} applications processed.")`. Return `0`. The command is auto-discovered by Laravel via `app/Console/Commands/` directory — no registration needed in `Kernel.php` (Laravel 11 uses attribute-based or auto-discovery). Add all required `use` imports. **Also add the `tasks` relationship to `VisaApplication` model**: in `app/Models/VisaApplication.php`, add `public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ApplicationTask::class, 'application_id'); }` — this relationship is needed by `whereDoesntHave('tasks')` and by both controllers.

- [X] T032 [P] [US3] Write `tests/Feature/Reviewer/WorkflowTemplateTest.php`: namespace `Tests\Feature\Reviewer`. Use `RefreshDatabase`. In `setUp()`, seed `RolePermissionSeeder`, `VisaTypeSeeder`, `WorkflowStepTemplateSeeder`. Tests:
  (1) `test_new_application_gets_correct_task_count()` — submit via `OnboardingService` (or directly create a VisaApplication then call `workflowService->seedTasksForApplication()`), assert `application_tasks` count equals 6.
  (2) `test_different_visa_types_get_different_steps()` — seed two visa types with different step counts (temporarily insert extra steps for one), create applications for each, assert each has the correct count.
  (3) `test_application_with_no_template_stays_pending_review()` — create a new VisaType with no templates, create an application for it, call `seedTasksForApplication`, assert application status is still `pending_review` and task count is 0.
  (4) `test_artisan_command_seeds_existing_applications()` — create an application manually (bypassing OnboardingService), verify 0 tasks. Run `$this->artisan('workflow:seed-tasks')` — assert it exits with 0 and the application now has 6 tasks.
  (5) `test_artisan_command_is_idempotent()` — run the command twice on same application, assert task count is still 6 (not 12).

**Checkpoint**: Run `php artisan workflow:seed-tasks`. Confirm existing applications from before Phase 3 now have 6 tasks. Run `php artisan workflow:seed-tasks` again — confirm no duplicate tasks.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Final integration verification and test run.

- [X] T033 [P] Run `php artisan migrate:fresh --seed` and verify the complete setup: confirm `workflow_step_templates` has 18 rows, `roles` has 3 rows, `permissions` has 11 rows (8 from Phase 1 + 3 new). Confirm reviewer role has `tasks.view`, `tasks.advance`, `tasks.reject` permissions via `php artisan tinker --execute="echo \Spatie\Permission\Models\Role::findByName('reviewer')->permissions->pluck('name')->implode(', ');"`.

- [X] T034 [P] Run `php artisan test --filter=Workflow` to execute all feature tests written in T028 and T032. All tests must pass. If any fail, fix the underlying issue (do not skip or comment out tests).

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 (T001–T005) completion — BLOCKS all user stories
- **US1 Reviewer (Phase 3)**: Depends on Phase 2; specifically T007, T008, T009, T011, T012 must be complete
- **US2 Client Tasks (Phase 4)**: Depends on Phase 2; specifically T007 (ApplicationTask model) and the `tasks` relationship added in T031
- **US3 Backfill (Phase 5)**: Depends on Phase 2; T031 depends on `tasks` relationship which is added in T031 itself
- **Polish (Phase 6)**: Depends on all previous phases

### User Story Dependencies

- **US1 (P1)**: Requires Foundational (T006–T021) complete
- **US2 (P2)**: Requires T007 + `tasks` relationship on VisaApplication (added in T031) + DashboardController eager-load (T029). Note: the `tasks` relationship in T031 can be extracted earlier if needed
- **US3 (P3)**: Requires WorkflowService (T008) complete; the artisan command wraps around it

### ⚠️ Note on `tasks` relationship

T031 adds `public function tasks()` to `app/Models/VisaApplication.php`. This relationship is also needed by T029 (DashboardController eager-load) and T028 (tests). If implementing sequentially, complete T031's VisaApplication model change first, even if the artisan command body comes later.

### Parallel Opportunities Within Phase 2

```
# These can all run in parallel after T007 (ApplicationTask model):
T009  ApplicationTaskPolicy
T011  AdvanceTaskRequest
T012  RejectTaskRequest
T014  resources/lang/en/tasks.php
T015  resources/lang/ar/tasks.php
T016  lang/en/tasks.php (proxy)
T017  lang/ar/tasks.php (proxy)
T018  resources/lang/en/reviewer.php
T019  resources/lang/ar/reviewer.php
T020  lang/en/reviewer.php (proxy)
T021  lang/ar/reviewer.php (proxy)

# Sequential chain:
T006 → T007 → T008 → T010 (policy registration) → T013 (OnboardingService mod)
```

### Parallel Opportunities Within Phase 5

```
# T032 test can be written in parallel with T031 command implementation
T031  SeedApplicationWorkflowTasks command + tasks relationship
T032  WorkflowTemplateTest (can write test structure while T031 is in progress)
```

---

## Implementation Strategy

### MVP First (US1 Only)

1. Complete Phase 1: Setup (T001–T005)
2. Complete Phase 2: Foundational (T006–T021)
3. Complete Phase 3: US1 Reviewer workflow (T022–T028)
4. **STOP and VALIDATE**: Full reviewer workflow works; applications can be advanced through all 6 steps; audit log correct
5. Then add US2: Client task view (T029–T030)
6. Then add US3: Backfill command (T031–T032)

### Notes

- Every new PHP class must have the correct namespace matching its file path under `app/`
- `DB::` facade requires `use Illuminate\Support\Facades\DB`
- `Auth::` facade requires `use Illuminate\Support\Facades\Auth`
- The `<x-app-layout>` Blade component is from Breeze — do not recreate it
- `$task->application` lazy-loads `VisaApplication` via the `application()` relationship on `ApplicationTask` — works inside `DB::transaction()` since it's the same connection
- Run `php artisan config:clear && php artisan cache:clear` if route or middleware changes don't take effect
- The Phase 1 stub `Route::get('/reviewer/dashboard', fn () => view('dashboard.reviewer'))` **MUST** be removed in T027 — leaving it will cause a route name conflict with the new `reviewer.dashboard` route
- The `tasks` relationship on `VisaApplication` (added in T031) is required by both T029 (eager-load) and `whereDoesntHave('tasks')` in the artisan command — add it early if working sequentially
