# Tasks: Application Task Generation (014)

**Input**: Design documents from `/specs/014-app-task-generation/`
**Branch**: `014-app-task-generation`

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: User story this task belongs to ([US1], [US2], [US3])

> **Important**: The core implementation is already complete. Phase 2 work is **one new test file only**: `tests/Feature/Tasks/ApplicationTaskGenerationTest.php`. Phases 1 and 2 are read-only verification steps.

---

## Phase 1: Setup (Verification — No Code Changes)

**Purpose**: Confirm the existing implementation satisfies all requirements before writing tests.

- [ ] T001 Read `app/Services/Tasks/WorkflowService.php` and verify the `seedTasksForApplication` method at lines 19–86 is present and correct. Confirm: (a) early-return idempotency guard at line 21, (b) `DB::transaction()` wrapping all inserts at line 30, (c) first task updated to `status = 'in_progress'` at line 77, (d) audit log call at line 84. **No code changes.**

- [ ] T002 Read `app/Services/Client/OnboardingService.php` and verify that line 61 calls `$this->workflowService->seedTasksForApplication($application)` after the main transaction. **No code changes.**

---

## Phase 2: Foundational (Create Test File Skeleton)

**Purpose**: Create `tests/Feature/Tasks/ApplicationTaskGenerationTest.php` with the class shell, setUp, and shared `makeApplication` helper. All subsequent tasks ADD methods to this file.

**⚠️ CRITICAL**: Create this file before starting any user story phase.

- [ ] T003 Create the new file `tests/Feature/Tasks/ApplicationTaskGenerationTest.php` with the following exact content:

```php
<?php

namespace Tests\Feature\Tasks;

use App\Models\ApplicationTask;
use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use App\Models\WorkflowSection;
use App\Models\WorkflowTask;
use App\Services\Tasks\WorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VisaTypeSeeder;
use Database\Seeders\WorkflowBlueprintSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApplicationTaskGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VisaTypeSeeder::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeApplication(VisaType $visaType): VisaApplication
    {
        $client = User::factory()->create()->assignRole('client');

        return VisaApplication::create([
            'user_id'                => $client->id,
            'visa_type_id'           => $visaType->id,
            'status'                 => 'pending_review',
            'full_name'              => $client->name,
            'email'                  => $client->email,
            'phone'                  => '+1555000123',
            'nationality'            => 'Jordanian',
            'country_of_residence'   => 'UAE',
            'job_title'              => 'Engineer',
            'employment_type'        => 'employed',
            'monthly_income'         => 5000,
            'adults_count'           => 1,
            'children_count'         => 0,
            'application_start_date' => now()->addDays(30)->toDateString(),
            'agreed_to_terms'        => true,
        ]);
    }
}
```

**Checkpoint**: File exists with class shell. Run `php artisan test --filter=ApplicationTaskGenerationTest` — it should report 0 tests, 0 failures.

---

## Phase 3: User Story 1 — Tasks Generated on Application Submission (Priority: P1) 🎯 MVP

**Goal**: Verify that seeding creates the correct task set from the blueprint, that isolation between applications works, and that no tasks are generated for visa types without a blueprint.

**Independent Test**: `php artisan test --filter=ApplicationTaskGenerationTest` should pass after T006.

### Implementation for User Story 1

- [ ] T004 [US1] Add the following test method inside the class body in `tests/Feature/Tasks/ApplicationTaskGenerationTest.php`, after the `makeApplication` helper:

```php
    // ── US1: Task generation from blueprint ───────────────────────────────────

    public function test_tasks_generated_from_blueprint(): void
    {
        $this->seed(WorkflowBlueprintSeeder::class);

        $visaType    = VisaType::where('name', 'Tourist Visa')->firstOrFail();
        $application = $this->makeApplication($visaType);

        app(WorkflowService::class)->seedTasksForApplication($application);

        $tasks = ApplicationTask::where('application_id', $application->id)
            ->orderBy('position')
            ->get();

        // Tourist Visa blueprint has 4 sections × 2 tasks = 8 tasks
        $this->assertCount(8, $tasks);

        // Verify first task name and type match the blueprint
        $this->assertSame('Complete Personal Details', $tasks[0]->name);
        $this->assertSame('question', $tasks[0]->type);
        $this->assertSame(1, $tasks[0]->position);
    }
```

- [ ] T005 [P] [US1] Add the following test method inside the class body in `tests/Feature/Tasks/ApplicationTaskGenerationTest.php`:

```php
    public function test_no_tasks_for_visa_type_without_blueprint(): void
    {
        // No WorkflowBlueprintSeeder called — visa type has no sections or templates
        $visaType    = VisaType::first();
        $application = $this->makeApplication($visaType);

        app(WorkflowService::class)->seedTasksForApplication($application);

        $this->assertSame(0, ApplicationTask::where('application_id', $application->id)->count());
        // Application was still created successfully
        $this->assertDatabaseHas('visa_applications', ['id' => $application->id]);
    }
```

- [ ] T006 [P] [US1] Add the following test method inside the class body in `tests/Feature/Tasks/ApplicationTaskGenerationTest.php`:

```php
    public function test_two_applications_have_independent_task_sets(): void
    {
        $visaType = VisaType::first();
        $section  = WorkflowSection::create(['visa_type_id' => $visaType->id, 'name' => 'Sec', 'position' => 1]);
        WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'Shared Task', 'type' => 'upload', 'position' => 1]);

        $appA = $this->makeApplication($visaType);
        $appB = $this->makeApplication($visaType);

        app(WorkflowService::class)->seedTasksForApplication($appA);
        app(WorkflowService::class)->seedTasksForApplication($appB);

        $this->assertSame(1, ApplicationTask::where('application_id', $appA->id)->count());
        $this->assertSame(1, ApplicationTask::where('application_id', $appB->id)->count());

        // Modifying app A's task name does not affect app B
        ApplicationTask::where('application_id', $appA->id)->first()->update(['name' => 'Modified']);

        $this->assertSame('Shared Task', ApplicationTask::where('application_id', $appB->id)->first()->name);
    }
```

**Checkpoint**: Run `php artisan test --filter=ApplicationTaskGenerationTest` — all 3 tests should pass.

---

## Phase 4: User Story 2 — Initial Task Status Assignment (Priority: P1)

**Goal**: Verify that after seeding, position-1 task has `in_progress` status and all others have `pending`. Verify the single-task edge case.

**Independent Test**: `php artisan test --filter=ApplicationTaskGenerationTest` should pass after T008.

### Implementation for User Story 2

- [ ] T007 [US2] Add the following test method inside the class body in `tests/Feature/Tasks/ApplicationTaskGenerationTest.php`:

```php
    // ── US2: Initial status assignment ────────────────────────────────────────

    public function test_first_task_is_in_progress_rest_are_pending(): void
    {
        $this->seed(WorkflowBlueprintSeeder::class);

        $visaType    = VisaType::where('name', 'Tourist Visa')->firstOrFail();
        $application = $this->makeApplication($visaType);

        app(WorkflowService::class)->seedTasksForApplication($application);

        $tasks = ApplicationTask::where('application_id', $application->id)
            ->orderBy('position')
            ->get();

        $this->assertCount(8, $tasks);
        $this->assertSame('in_progress', $tasks[0]->status);

        foreach ($tasks->slice(1) as $task) {
            $this->assertSame('pending', $task->status, "Task at position {$task->position} should be pending");
        }

        $this->assertSame('in_progress', $application->fresh()->status);
    }
```

- [ ] T008 [US2] Add the following test method inside the class body in `tests/Feature/Tasks/ApplicationTaskGenerationTest.php`:

```php
    public function test_single_task_blueprint_gets_in_progress(): void
    {
        $visaType = VisaType::first();
        $section  = WorkflowSection::create(['visa_type_id' => $visaType->id, 'name' => 'Only Section', 'position' => 1]);
        WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'Only Task', 'type' => 'info', 'position' => 1]);

        $application = $this->makeApplication($visaType);
        app(WorkflowService::class)->seedTasksForApplication($application);

        $task = ApplicationTask::where('application_id', $application->id)->first();

        $this->assertNotNull($task);
        $this->assertSame('in_progress', $task->status);
    }
```

**Checkpoint**: Run `php artisan test --filter=ApplicationTaskGenerationTest` — all 5 tests should pass.

---

## Phase 5: User Story 3 — Blueprint Changes Do Not Affect Existing Application Tasks (Priority: P2)

**Goal**: Verify that renaming or changing type of a blueprint task has no effect on already-generated application tasks.

**Independent Test**: `php artisan test --filter=ApplicationTaskGenerationTest` should pass after T010.

### Implementation for User Story 3

- [ ] T009 [US3] Add the following test method inside the class body in `tests/Feature/Tasks/ApplicationTaskGenerationTest.php`:

```php
    // ── US3: Blueprint independence ───────────────────────────────────────────

    public function test_blueprint_rename_does_not_affect_application_task(): void
    {
        $this->seed(WorkflowBlueprintSeeder::class);

        $visaType    = VisaType::where('name', 'Tourist Visa')->firstOrFail();
        $application = $this->makeApplication($visaType);

        app(WorkflowService::class)->seedTasksForApplication($application);

        // Rename the blueprint task at position 1
        $blueprintTask = WorkflowTask::whereHas('section', fn ($q) => $q->where('visa_type_id', $visaType->id))
            ->where('position', 1)
            ->first();

        $originalName = $blueprintTask->name;
        $blueprintTask->update(['name' => 'Renamed Blueprint Task']);

        // Application task is unaffected
        $appTask = ApplicationTask::where('application_id', $application->id)
            ->where('position', 1)
            ->first();

        $this->assertSame($originalName, $appTask->fresh()->name);
        $this->assertNotSame('Renamed Blueprint Task', $appTask->fresh()->name);
    }
```

- [ ] T010 [US3] Add the following test method inside the class body in `tests/Feature/Tasks/ApplicationTaskGenerationTest.php`:

```php
    public function test_blueprint_type_change_does_not_affect_application_task(): void
    {
        $visaType = VisaType::first();
        $section  = WorkflowSection::create(['visa_type_id' => $visaType->id, 'name' => 'Test Section', 'position' => 1]);
        $wfTask   = WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'Pay Fee', 'type' => 'payment', 'position' => 1]);

        $application = $this->makeApplication($visaType);
        app(WorkflowService::class)->seedTasksForApplication($application);

        // Change blueprint task type after seeding
        $wfTask->update(['type' => 'info']);

        // Application task type is unaffected
        $appTask = ApplicationTask::where('application_id', $application->id)->first();
        $this->assertSame('payment', $appTask->fresh()->type);
    }
```

**Checkpoint**: Run `php artisan test --filter=ApplicationTaskGenerationTest` — all 7 tests should pass.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Idempotency guard and audit log coverage (Constitution XI).

- [ ] T011 Add the following test method inside the class body in `tests/Feature/Tasks/ApplicationTaskGenerationTest.php`:

```php
    // ── Polish: Cross-cutting ──────────────────────────────────────────────────

    public function test_seeding_is_idempotent(): void
    {
        $visaType = VisaType::first();
        $section  = WorkflowSection::create(['visa_type_id' => $visaType->id, 'name' => 'Sec', 'position' => 1]);
        WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'Task', 'type' => 'upload', 'position' => 1]);

        $application = $this->makeApplication($visaType);
        $service     = app(WorkflowService::class);

        $service->seedTasksForApplication($application);
        $service->seedTasksForApplication($application); // second call — must be a no-op

        $this->assertSame(1, ApplicationTask::where('application_id', $application->id)->count());
    }
```

- [ ] T012 Add the following test method inside the class body in `tests/Feature/Tasks/ApplicationTaskGenerationTest.php`:

```php
    public function test_audit_log_created_on_seeding(): void
    {
        $visaType = VisaType::first();
        $section  = WorkflowSection::create(['visa_type_id' => $visaType->id, 'name' => 'Sec', 'position' => 1]);
        WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'Task', 'type' => 'upload', 'position' => 1]);

        $application = $this->makeApplication($visaType);
        app(WorkflowService::class)->seedTasksForApplication($application);

        $this->assertSame(
            1,
            DB::table('audit_logs')->where('event', 'workflow_started')->count()
        );
    }
```

- [ ] T013 Run `php artisan test --filter=ApplicationTaskGenerationTest` and confirm output shows **9 tests, all passing, 0 failures**. If any test fails, read the error output and fix the failing assertion or setup.

- [ ] T014 Run `php artisan test` (full suite) and confirm all existing tests still pass alongside the 9 new tests.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately; read-only verification
- **Phase 2 (Foundational)**: Depends on Phase 1 completion; creates the test file skeleton
- **Phase 3 (US1)**: Depends on Phase 2 (file must exist); T005 and T006 can run in parallel with T004
- **Phase 4 (US2)**: Depends on Phase 3 completion; T007 and T008 can run in parallel
- **Phase 5 (US3)**: Depends on Phase 4 completion; T009 and T010 can run in parallel
- **Phase 6 (Polish)**: Depends on Phase 5 completion; T011 and T012 can run in parallel

### Parallel Opportunities

- T005 and T006 are [P] — can be added to the file in any order
- T009 and T010 are [P] — can be added to the file in any order
- T011 and T012 can be added in any order

---

## Final File State

After all tasks are complete, `tests/Feature/Tasks/ApplicationTaskGenerationTest.php` contains **9 test methods**:

| Method | Story | Scenario |
|--------|-------|----------|
| `test_tasks_generated_from_blueprint` | US1 | 8 tasks created from Tourist Visa blueprint |
| `test_no_tasks_for_visa_type_without_blueprint` | US1 | Zero tasks, no error for blueprint-less visa |
| `test_two_applications_have_independent_task_sets` | US1 | Task sets are isolated per application |
| `test_first_task_is_in_progress_rest_are_pending` | US2 | Correct initial status assignment |
| `test_single_task_blueprint_gets_in_progress` | US2 | Single-task edge case |
| `test_blueprint_rename_does_not_affect_application_task` | US3 | Blueprint rename independence |
| `test_blueprint_type_change_does_not_affect_application_task` | US3 | Blueprint type change independence |
| `test_seeding_is_idempotent` | Polish | Double-seeding produces no duplicates |
| `test_audit_log_created_on_seeding` | Polish | Audit log entry confirmed |

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Verify existing implementation
2. Complete Phase 2: Create test file skeleton (T003)
3. Complete Phase 3: US1 tests (T004–T006)
4. **STOP and VALIDATE**: Run `php artisan test --filter=ApplicationTaskGenerationTest` — 3 tests pass
5. Proceed to US2 and US3

### Incremental Delivery

1. T001–T003 → Foundation ready
2. T004–T006 → US1 complete (3 tests pass)
3. T007–T008 → US2 complete (5 tests pass)
4. T009–T010 → US3 complete (7 tests pass)
5. T011–T014 → Polish complete (9 tests pass, full suite green)

---

## Notes

- All tasks are additive — no existing files are modified
- Each test method is self-contained and can be added independently
- The `makeApplication` helper in the skeleton is copied verbatim from `WorkflowSectionSeedingTest` for consistency
- `WorkflowBlueprintSeeder` is only used in T004, T007, T009 — tests that need the full 8-task Tourist Visa blueprint
- Tests that need simpler blueprints create sections/tasks inline for clarity and independence
