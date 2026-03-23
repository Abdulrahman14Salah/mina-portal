# Tasks: Workflow Integrity Fixes

**Branch**: `012-workflow-integrity`
**Input**: Design documents from `/specs/012-workflow-integrity/`
**Prerequisites**: plan.md ✓, spec.md ✓, research.md ✓, data-model.md ✓, quickstart.md ✓

**Tech stack**: PHP 8.2+ / Laravel 11, PHPUnit, SQLite in-memory (tests)
**Files changed**: 1 service file updated + 1 new test file

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no blocking dependencies)
- **[Story]**: Which user story this task maps to

---

## Phase 1: Setup

**Purpose**: No new infrastructure needed — this feature modifies one existing service file and adds one test file. Phase 1 confirms the target file exists and the test namespace is correct.

- [ ] T001 Confirm `app/Services/Tasks/WorkflowService.php` exists and contains the three methods to fix: `seedTasksForApplication`, `advanceTask`, `approveTask`. Read the file to verify before proceeding.

---

## Phase 2: Foundational — Test Scaffold

**Purpose**: Create the test file with its class scaffold so both user story phases can add methods to it independently.

**⚠️ CRITICAL**: T002 must complete before T003 and T004 begin.

- [ ] T002 Create `tests/Feature/Tasks/WorkflowIntegrityTest.php` with the following exact content:

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
  use Illuminate\Foundation\Testing\RefreshDatabase;
  use Illuminate\Support\Facades\DB;
  use Tests\TestCase;

  class WorkflowIntegrityTest extends TestCase
  {
      use RefreshDatabase;

      protected function setUp(): void
      {
          parent::setUp();
          $this->seed(RolePermissionSeeder::class);
          $this->seed(VisaTypeSeeder::class);
      }

      // ── Helpers ──────────────────────────────────────────────────────────────

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

      private function auditCount(string $event): int
      {
          return DB::table('audit_logs')->where('event', $event)->count();
      }

      // Tests will be added in T003 and T004
  }
  ```

**Checkpoint**: File exists and compiles. Run `php artisan test --filter WorkflowIntegrityTest` — it should find the class with 0 tests (no errors).

---

## Phase 3: US1 — Accurate Audit Trail for Workflow Start (Priority: P1)

**Goal**: Fix `seedTasksForApplication` so the `workflow_started` audit log entry is only emitted when tasks are actually created.

**Independent Test**: `php artisan test --filter WorkflowIntegrityTest::test_no_audit_log_when_no_tasks_configured`

### Step 1 — Add tests for US1 to `tests/Feature/Tasks/WorkflowIntegrityTest.php`

- [ ] T003 [US1] Add the following three test methods to `tests/Feature/Tasks/WorkflowIntegrityTest.php`, **before** the closing `}` of the class:

  ```php
      // ── US1: Audit log accuracy ───────────────────────────────────────────────

      public function test_no_audit_log_when_no_tasks_configured(): void
      {
          // Visa type exists but has no workflow sections and no templates
          $visaType = VisaType::first();

          $application = $this->makeApplication($visaType);
          app(WorkflowService::class)->seedTasksForApplication($application);

          $this->assertSame(0, $this->auditCount('workflow_started'));
          $this->assertSame(0, ApplicationTask::where('application_id', $application->id)->count());
      }

      public function test_audit_log_created_when_tasks_are_seeded(): void
      {
          $visaType = VisaType::first();
          $section  = WorkflowSection::create(['visa_type_id' => $visaType->id, 'name' => 'Section A', 'position' => 1]);
          WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'Task 1', 'type' => 'upload', 'position' => 1]);

          $application = $this->makeApplication($visaType);
          app(WorkflowService::class)->seedTasksForApplication($application);

          $this->assertSame(1, $this->auditCount('workflow_started'));
          $this->assertSame(1, ApplicationTask::where('application_id', $application->id)->count());
      }

      public function test_no_duplicate_audit_log_on_re_seed(): void
      {
          $visaType = VisaType::first();
          $section  = WorkflowSection::create(['visa_type_id' => $visaType->id, 'name' => 'Section A', 'position' => 1]);
          WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'Task 1', 'type' => 'upload', 'position' => 1]);

          $application = $this->makeApplication($visaType);
          $service     = app(WorkflowService::class);

          $service->seedTasksForApplication($application);
          $service->seedTasksForApplication($application); // second call — should be no-op

          $this->assertSame(1, $this->auditCount('workflow_started'));
          $this->assertSame(1, ApplicationTask::where('application_id', $application->id)->count());
      }
  ```

### Step 2 — Run tests to confirm they FAIL (pre-fix baseline)

- [ ] T004 [US1] Run `php artisan test --filter WorkflowIntegrityTest` and confirm `test_no_audit_log_when_no_tasks_configured` **FAILS** (it will get count 1 because the bug fires the audit log unconditionally). The other two tests may pass. This confirms the bug exists before the fix.

### Step 3 — Apply the fix to `app/Services/Tasks/WorkflowService.php`

- [ ] T005 [US1] In `app/Services/Tasks/WorkflowService.php`, modify **only** the `seedTasksForApplication` method as follows:

  **FIND** this exact block (lines 19–81 of the current file):

  ```php
  public function seedTasksForApplication(VisaApplication $application): void
  {
      if ($application->tasks()->exists()) {
          return;
      }

      // Prefer new section-based structure if present
      $hasSections = WorkflowSection::where('visa_type_id', $application->visa_type_id)->exists();

      DB::transaction(function () use ($application, $hasSections): void {
          $tasks = [];
          $position = 1;

          if ($hasSections) {
              $sections = WorkflowSection::where('visa_type_id', $application->visa_type_id)
                  ->orderBy('position')
                  ->with(['tasks' => fn ($q) => $q->orderBy('position')])
                  ->get();

              foreach ($sections as $section) {
                  foreach ($section->tasks as $workflowTask) {
                      $tasks[] = ApplicationTask::create([
                          'application_id'            => $application->id,
                          'workflow_step_template_id' => null,
                          'position'                  => $position++,
                          'name'                      => $workflowTask->name,
                          'description'               => $workflowTask->description,
                          'type'                      => $workflowTask->type,
                          'status'                    => 'pending',
                      ]);
                  }
              }
          } else {
              // Fallback: legacy flat templates
              $templates = WorkflowStepTemplate::where('visa_type_id', $application->visa_type_id)
                  ->orderBy('position')
                  ->get();

              if ($templates->isEmpty()) {
                  return;
              }

              foreach ($templates as $template) {
                  $tasks[] = ApplicationTask::create([
                      'application_id'            => $application->id,
                      'workflow_step_template_id' => $template->id,
                      'position'                  => $template->position,
                      'name'                      => $template->name,
                      'description'               => $template->description,
                      'type'                      => 'upload',
                      'status'                    => 'pending',
                  ]);
              }
          }

          if (! empty($tasks)) {
              $tasks[0]->update(['status' => 'in_progress']);
              $application->update(['status' => 'in_progress']);
          }
      });

      $this->auditLog->log('workflow_started', $application->user()->first(), ['reference' => $application->reference_number]);
  }
  ```

  **REPLACE WITH** this exact block:

  ```php
  public function seedTasksForApplication(VisaApplication $application): void
  {
      if ($application->tasks()->exists()) {
          return;
      }

      // Prefer new section-based structure if present
      $hasSections = WorkflowSection::where('visa_type_id', $application->visa_type_id)->exists();

      $seeded = false;

      DB::transaction(function () use ($application, $hasSections, &$seeded): void {
          $tasks = [];
          $position = 1;

          if ($hasSections) {
              $sections = WorkflowSection::where('visa_type_id', $application->visa_type_id)
                  ->orderBy('position')
                  ->with(['tasks' => fn ($q) => $q->orderBy('position')])
                  ->get();

              foreach ($sections as $section) {
                  foreach ($section->tasks as $workflowTask) {
                      $tasks[] = ApplicationTask::create([
                          'application_id'            => $application->id,
                          'workflow_step_template_id' => null,
                          'position'                  => $position++,
                          'name'                      => $workflowTask->name,
                          'description'               => $workflowTask->description,
                          'type'                      => $workflowTask->type,
                          'status'                    => 'pending',
                      ]);
                  }
              }
          } else {
              // Fallback: legacy flat templates
              $templates = WorkflowStepTemplate::where('visa_type_id', $application->visa_type_id)
                  ->orderBy('position')
                  ->get();

              if ($templates->isEmpty()) {
                  return;
              }

              foreach ($templates as $template) {
                  $tasks[] = ApplicationTask::create([
                      'application_id'            => $application->id,
                      'workflow_step_template_id' => $template->id,
                      'position'                  => $template->position,
                      'name'                      => $template->name,
                      'description'               => $template->description,
                      'type'                      => 'upload',
                      'status'                    => 'pending',
                  ]);
              }
          }

          if (! empty($tasks)) {
              $tasks[0]->update(['status' => 'in_progress']);
              $application->update(['status' => 'in_progress']);
              $seeded = true;
          }
      });

      if ($seeded) {
          $this->auditLog->log('workflow_started', $application->user()->first(), ['reference' => $application->reference_number]);
      }
  }
  ```

  **What changed** (3 lines only):
  1. Added `$seeded = false;` before `DB::transaction(`.
  2. Added `&$seeded` to the closure's `use` clause: `use ($application, $hasSections, &$seeded)`.
  3. Added `$seeded = true;` inside the `if (! empty($tasks))` block.
  4. Wrapped the `$this->auditLog->log(...)` call in `if ($seeded) { ... }`.

### Step 4 — Verify US1 tests pass

- [ ] T006 [US1] Run `php artisan test --filter WorkflowIntegrityTest` — all three US1 tests must now pass. If any fail, re-read T005 and check that the `&` (ampersand) is present in the `use` clause (pass by reference, not by value).

**Checkpoint**: US1 complete. The audit log only fires when tasks are created.

---

## Phase 4: US2 — Reliable Workflow Task Progression (Priority: P1)

**Goal**: Fix `advanceTask` and `approveTask` so the next task is found by ordered position rather than exact `position + 1`.

**Independent Test**: `php artisan test --filter WorkflowIntegrityTest::test_next_task_activates_with_gap_positions`

### Step 1 — Add tests for US2 to `tests/Feature/Tasks/WorkflowIntegrityTest.php`

- [ ] T007 [P] [US2] Add the following four test methods to `tests/Feature/Tasks/WorkflowIntegrityTest.php`, **before** the closing `}` of the class:

  ```php
      // ── US2: Reliable next-task progression ──────────────────────────────────

      public function test_next_task_activates_with_contiguous_positions(): void
      {
          $visaType = VisaType::first();
          $section  = WorkflowSection::create(['visa_type_id' => $visaType->id, 'name' => 'S', 'position' => 1]);
          WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'T1', 'type' => 'upload', 'position' => 1]);
          WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'T2', 'type' => 'upload', 'position' => 2]);
          WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'T3', 'type' => 'upload', 'position' => 3]);

          $application = $this->makeApplication($visaType);
          app(WorkflowService::class)->seedTasksForApplication($application);

          $tasks = ApplicationTask::where('application_id', $application->id)->orderBy('position')->get();
          $this->assertSame('in_progress', $tasks[0]->status);

          app(WorkflowService::class)->advanceTask($tasks[0], null);

          $this->assertSame('approved',    $tasks[0]->fresh()->status);
          $this->assertSame('in_progress', $tasks[1]->fresh()->status);
          $this->assertSame('pending',     $tasks[2]->fresh()->status);
      }

      public function test_next_task_activates_with_gap_positions(): void
      {
          // Tasks at positions 1, 3, 5 — simulates legacy templates with gaps
          $visaType = VisaType::first();

          DB::table('workflow_step_templates')->insert([
              ['visa_type_id' => $visaType->id, 'name' => 'Step 1', 'description' => null, 'position' => 1, 'is_document_required' => 1, 'created_at' => now(), 'updated_at' => now()],
              ['visa_type_id' => $visaType->id, 'name' => 'Step 3', 'description' => null, 'position' => 3, 'is_document_required' => 1, 'created_at' => now(), 'updated_at' => now()],
              ['visa_type_id' => $visaType->id, 'name' => 'Step 5', 'description' => null, 'position' => 5, 'is_document_required' => 1, 'created_at' => now(), 'updated_at' => now()],
          ]);

          $application = $this->makeApplication($visaType);
          app(WorkflowService::class)->seedTasksForApplication($application);

          $tasks = ApplicationTask::where('application_id', $application->id)->orderBy('position')->get();
          $this->assertCount(3, $tasks);
          $this->assertSame(1, $tasks[0]->position);
          $this->assertSame(3, $tasks[1]->position);
          $this->assertSame(5, $tasks[2]->position);
          $this->assertSame('in_progress', $tasks[0]->status);

          // Advance task at position 1 → task at position 3 should become in_progress
          app(WorkflowService::class)->advanceTask($tasks[0], null);

          $this->assertSame('approved',    $tasks[0]->fresh()->status);
          $this->assertSame('in_progress', $tasks[1]->fresh()->status, 'Task at position 3 must become in_progress');
          $this->assertSame('pending',     $tasks[2]->fresh()->status);
      }

      public function test_advancing_final_task_does_not_crash(): void
      {
          $visaType = VisaType::first();
          $section  = WorkflowSection::create(['visa_type_id' => $visaType->id, 'name' => 'S', 'position' => 1]);
          WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'Only Task', 'type' => 'upload', 'position' => 1]);

          $application = $this->makeApplication($visaType);
          app(WorkflowService::class)->seedTasksForApplication($application);

          $task = ApplicationTask::where('application_id', $application->id)->first();
          $this->assertSame('in_progress', $task->status);

          app(WorkflowService::class)->advanceTask($task, null);

          $this->assertSame('approved', $task->fresh()->status);
          // No exception thrown — test passes if we reach this line
          $this->assertTrue(true);
      }

      public function test_approve_task_also_uses_ordered_next_task_lookup(): void
      {
          // Same as gap test but uses approveTask instead of advanceTask
          $visaType = VisaType::first();

          DB::table('workflow_step_templates')->insert([
              ['visa_type_id' => $visaType->id, 'name' => 'Step 1', 'description' => null, 'position' => 1, 'is_document_required' => 1, 'created_at' => now(), 'updated_at' => now()],
              ['visa_type_id' => $visaType->id, 'name' => 'Step 3', 'description' => null, 'position' => 3, 'is_document_required' => 1, 'created_at' => now(), 'updated_at' => now()],
          ]);

          $application = $this->makeApplication($visaType);
          app(WorkflowService::class)->seedTasksForApplication($application);

          $tasks = ApplicationTask::where('application_id', $application->id)->orderBy('position')->get();

          app(WorkflowService::class)->approveTask($tasks[0], null);

          $this->assertSame('approved',    $tasks[0]->fresh()->status);
          $this->assertSame('in_progress', $tasks[1]->fresh()->status, 'Task at position 3 must become in_progress after approve');
      }
  ```

### Step 2 — Run tests to confirm gap tests FAIL (pre-fix baseline)

- [ ] T008 [US2] Run `php artisan test --filter WorkflowIntegrityTest` and confirm `test_next_task_activates_with_gap_positions` and `test_approve_task_also_uses_ordered_next_task_lookup` **FAIL**. The contiguous and final-task tests may pass already.

### Step 3 — Fix `advanceTask` in `app/Services/Tasks/WorkflowService.php`

- [ ] T009 [US2] In `app/Services/Tasks/WorkflowService.php`, inside the `advanceTask` method, find and replace the next-task query:

  **FIND** (inside the `DB::transaction` closure of `advanceTask`, around line 98):
  ```php
              $nextTask = ApplicationTask::where('application_id', $task->application_id)
                  ->where('position', $task->position + 1)
                  ->first();
  ```

  **REPLACE WITH**:
  ```php
              $nextTask = ApplicationTask::where('application_id', $task->application_id)
                  ->where('position', '>', $task->position)
                  ->orderBy('position')
                  ->first();
  ```

### Step 4 — Fix `approveTask` in `app/Services/Tasks/WorkflowService.php`

- [ ] T010 [US2] In `app/Services/Tasks/WorkflowService.php`, inside the `approveTask` method, find and replace the next-task query:

  **FIND** (inside the `DB::transaction` closure of `approveTask`, around line 125):
  ```php
              $nextTask = ApplicationTask::where('application_id', $task->application_id)
                  ->where('position', $task->position + 1)
                  ->first();
  ```

  **REPLACE WITH**:
  ```php
              $nextTask = ApplicationTask::where('application_id', $task->application_id)
                  ->where('position', '>', $task->position)
                  ->orderBy('position')
                  ->first();
  ```

  > **Note**: T009 and T010 fix the same pattern but in two separate methods. Both must be changed independently.

### Step 5 — Verify all US2 tests pass

- [ ] T011 [US2] Run `php artisan test --filter WorkflowIntegrityTest` — all seven tests (3 from US1 + 4 from US2) must pass.

**Checkpoint**: US2 complete. Workflow progression works correctly for both contiguous and gap positions.

---

## Phase 5: Polish & Regression Check

**Purpose**: Confirm no regressions in the full suite.

- [ ] T012 Run the full test suite to confirm all pre-existing tests still pass:

  ```bash
  php artisan test
  ```

  Expected: All tests green. If any previously passing test now fails, re-read T005, T009, T010 and verify the `old_string` was matched exactly (whitespace matters).

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately
- **Phase 2 (Foundational)**: Depends on Phase 1; T002 must complete before T003/T007
- **Phase 3 (US1)**: Depends on Phase 2; T003 → T004 → T005 → T006
- **Phase 4 (US2)**: Depends on Phase 2; T007 [P] can start alongside T003; T008 → T009 → T010 → T011
- **Phase 5 (Polish)**: Depends on T006 + T011

### User Story Dependencies

- **US1 (P1)**: Needs Phase 2 complete — then sequential: T003 → T005 → T006
- **US2 (P1)**: Needs Phase 2 complete — T007 [P] can start once T002 is done

### Parallel Opportunities

```
Phase 2: T002 (create test file scaffold)
              │
Phase 3+4:  T003 ── T004 ── T005 ── T006   (US1 — sequential, same file)
              ║
             T007              ── T008 ── T009 ── T010 ── T011   (US2)
```

> US1 and US2 both append to the same test file — coordinate so T003 completes before T007 begins, or split them into separate files if running in parallel.

---

## Implementation Strategy

### MVP (US1 only — audit log fix)

1. T001 — Confirm file exists
2. T002 — Create test file scaffold
3. T003 — Add US1 tests
4. T004 — Confirm tests fail (pre-fix)
5. T005 — Apply the `$seeded` flag fix
6. T006 — Confirm US1 tests pass
7. **STOP and VALIDATE**: `php artisan test --filter WorkflowIntegrityTest`

### Full Delivery (both fixes)

Continue from MVP:

8. T007 — Add US2 tests
9. T008 — Confirm gap tests fail
10. T009 + T010 — Apply position query fix to both methods
11. T011 — Confirm all tests pass
12. T012 — Full regression check

---

## Notes

- The `&$seeded` ampersand in T005 is **critical** — without it PHP passes by value and `$seeded` stays `false` outside the closure
- T009 and T010 are identical changes to two different methods — both must be applied
- Do NOT modify any existing test files or their assertions (SC-003 from spec)
- The `workflow_step_templates` table insert in T007 uses raw `DB::table()` to create legacy-style templates with non-contiguous positions — this intentionally bypasses the section-based system to test the legacy path
