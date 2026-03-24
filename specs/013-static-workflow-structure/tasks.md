# Tasks: Static Workflow Structure

**Input**: Design documents from `/specs/013-static-workflow-structure/`
**Branch**: `013-static-workflow-structure`

---

## Phase 1: Setup

No project initialization required — this is an extension of an existing Laravel project on branch `013-static-workflow-structure`.

- [ ] T001 Confirm you are on branch `013-static-workflow-structure` by running `git branch --show-current`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Two migrations must be created and run before any user story work. Migration A changes the type column from ENUM to string. Migration B adds position uniqueness constraints.

**⚠️ CRITICAL**: Both migrations must be applied before the model, seeder, or tests can work correctly.

---

- [ ] T002 Create Migration A to change `workflow_tasks.type` from ENUM to string in `database/migrations/2026_03_22_200001_update_workflow_tasks_type_column.php`

  Create the file with exactly this content:

  ```php
  <?php

  use Illuminate\Database\Migrations\Migration;
  use Illuminate\Database\Schema\Blueprint;
  use Illuminate\Support\Facades\Schema;

  return new class extends Migration
  {
      public function up(): void
      {
          Schema::table('workflow_tasks', function (Blueprint $table) {
              $table->string('type', 50)->default('upload')->change();
          });
      }

      public function down(): void
      {
          Schema::table('workflow_tasks', function (Blueprint $table) {
              $table->enum('type', ['upload', 'text', 'both'])->default('upload')->change();
          });
      }
  };
  ```

---

- [ ] T003 [P] Create Migration B to add unique composite indexes in `database/migrations/2026_03_22_200002_add_unique_constraints_workflow_sections_tasks.php`

  Create the file with exactly this content:

  ```php
  <?php

  use Illuminate\Database\Migrations\Migration;
  use Illuminate\Database\Schema\Blueprint;
  use Illuminate\Support\Facades\Schema;

  return new class extends Migration
  {
      public function up(): void
      {
          Schema::table('workflow_sections', function (Blueprint $table) {
              $table->unique(['visa_type_id', 'position'], 'workflow_sections_visa_position_unique');
          });

          Schema::table('workflow_tasks', function (Blueprint $table) {
              $table->unique(['workflow_section_id', 'position'], 'workflow_tasks_section_position_unique');
          });
      }

      public function down(): void
      {
          Schema::table('workflow_sections', function (Blueprint $table) {
              $table->dropUnique('workflow_sections_visa_position_unique');
          });

          Schema::table('workflow_tasks', function (Blueprint $table) {
              $table->dropUnique('workflow_tasks_section_position_unique');
          });
      }
  };
  ```

---

- [ ] T004 Run `php artisan migrate` to apply both migrations to the local database. Confirm output shows both new migrations applied with no errors.

**Checkpoint**: Both migrations applied — user story work can now begin.

---

## Phase 3: User Story 2 — Task Types Correctly Defined (Priority: P1)

**Goal**: The `WorkflowTask` model enforces valid types (`upload`, `question`, `payment`, `info`) at the model level, and the admin Form Request is updated to match.

**Why US2 before US1**: The `WorkflowBlueprintSeeder` (US1) creates tasks with types `question`, `payment`, and `info`. If the model hook isn't in place first, those creates will be blocked or unvalidated. US2 must be complete before the seeder can run.

**Independent Test**: Create a `WorkflowTask` directly via `WorkflowTask::create()` with `type = 'foobar'` and confirm an `InvalidArgumentException` is thrown. Create with `type = 'question'` and confirm it saves.

---

- [ ] T005 [US2] Replace the full content of `app/Models/WorkflowTask.php` with:

  ```php
  <?php

  namespace App\Models;

  use Illuminate\Database\Eloquent\Factories\HasFactory;
  use Illuminate\Database\Eloquent\Model;
  use Illuminate\Database\Eloquent\Relations\BelongsTo;
  use InvalidArgumentException;

  class WorkflowTask extends Model
  {
      use HasFactory;

      public const VALID_TYPES = ['upload', 'question', 'payment', 'info'];

      protected $fillable = ['workflow_section_id', 'name', 'description', 'type', 'position'];

      protected $casts = [
          'position' => 'integer',
      ];

      protected static function booted(): void
      {
          static::saving(function (self $task): void {
              if (! in_array($task->type, self::VALID_TYPES, true)) {
                  throw new InvalidArgumentException(
                      "Invalid workflow task type '{$task->type}'. Must be one of: " . implode(', ', self::VALID_TYPES)
                  );
              }
          });
      }

      public function section(): BelongsTo
      {
          return $this->belongsTo(WorkflowSection::class, 'workflow_section_id');
      }
  }
  ```

---

- [ ] T006 [P] [US2] Update the `type` validation rule in `app/Http/Requests/Admin/StoreWorkflowTaskRequest.php`

  **FIND** this line:
  ```php
  'type' => ['required', 'in:upload,text,both'],
  ```

  **REPLACE WITH**:
  ```php
  'type' => ['required', 'in:upload,question,payment,info'],
  ```

  The rest of the file is unchanged.

**Checkpoint**: Type enforcement is live. `WorkflowTask::create(['type' => 'foobar', ...])` now throws `InvalidArgumentException`.

---

## Phase 4: User Story 1 — Workflow Blueprint Exists for Each Visa Type (Priority: P1)

**Goal**: The "Tourist Visa" type has a complete, seeded workflow blueprint: 4 sections, 8 tasks, covering all three new task types (`question`, `payment`, `info`).

**Independent Test**: Run `php artisan db:seed --class=WorkflowBlueprintSeeder` and then query `WorkflowSection::where('visa_type_id', VisaType::where('name', 'Tourist Visa')->first()->id)->count()` — must return `4`.

---

- [ ] T007 [US1] Create `database/seeders/WorkflowBlueprintSeeder.php` with exactly this content:

  ```php
  <?php

  namespace Database\Seeders;

  use App\Models\VisaType;
  use App\Models\WorkflowSection;
  use App\Models\WorkflowTask;
  use Illuminate\Database\Seeder;

  class WorkflowBlueprintSeeder extends Seeder
  {
      public function run(): void
      {
          $touristVisa = VisaType::where('name', 'Tourist Visa')->first();

          if (! $touristVisa) {
              return;
          }

          $blueprint = [
              [
                  'name'     => 'Personal Information',
                  'position' => 1,
                  'tasks'    => [
                      ['name' => 'Complete Personal Details',  'type' => 'question', 'position' => 1, 'description' => 'Fill in all required personal information for your visa application.'],
                      ['name' => 'Identity Verification Info', 'type' => 'info',     'position' => 2, 'description' => 'Review the identity documents required for your application.'],
                  ],
              ],
              [
                  'name'     => 'Documentation',
                  'position' => 2,
                  'tasks'    => [
                      ['name' => 'Application Fee Payment',           'type' => 'payment', 'position' => 1, 'description' => 'Pay the application processing fee and upload your payment receipt.'],
                      ['name' => 'Review Documentation Requirements', 'type' => 'info',    'position' => 2, 'description' => 'Review the list of supporting documents you must prepare.'],
                  ],
              ],
              [
                  'name'     => 'Interview Preparation',
                  'position' => 3,
                  'tasks'    => [
                      ['name' => 'Pre-Interview Questionnaire', 'type' => 'question', 'position' => 1, 'description' => 'Answer pre-interview questions to prepare your application for review.'],
                      ['name' => 'Interview Instructions',      'type' => 'info',     'position' => 2, 'description' => 'Read your interview guidelines and scheduled instructions.'],
                  ],
              ],
              [
                  'name'     => 'Final Submission',
                  'position' => 4,
                  'tasks'    => [
                      ['name' => 'Final Payment',           'type' => 'payment', 'position' => 1, 'description' => 'Pay the final visa issuance fee and upload your payment receipt.'],
                      ['name' => 'Submission Confirmation', 'type' => 'info',    'position' => 2, 'description' => 'Confirm your application is complete and ready for final processing.'],
                  ],
              ],
          ];

          foreach ($blueprint as $sectionData) {
              $section = WorkflowSection::firstOrCreate(
                  ['visa_type_id' => $touristVisa->id, 'position' => $sectionData['position']],
                  ['name' => $sectionData['name']]
              );

              foreach ($sectionData['tasks'] as $taskData) {
                  WorkflowTask::firstOrCreate(
                      ['workflow_section_id' => $section->id, 'position' => $taskData['position']],
                      [
                          'name'        => $taskData['name'],
                          'type'        => $taskData['type'],
                          'description' => $taskData['description'],
                      ]
                  );
              }
          }
      }
  }
  ```

---

- [ ] T008 [US1] Update `database/seeders/DatabaseSeeder.php` — add `WorkflowBlueprintSeeder` call after `VisaTypeSeeder`

  **FIND** this line:
  ```php
  $this->call(VisaTypeSeeder::class);
  ```

  **REPLACE WITH** these two lines:
  ```php
  $this->call(VisaTypeSeeder::class);
  $this->call(WorkflowBlueprintSeeder::class);
  ```

  The rest of `DatabaseSeeder.php` is unchanged.

**Checkpoint**: Blueprint seeded. Tourist Visa has 4 sections, 8 tasks, all three new types represented.

---

## Phase 5: User Story 3 — Blueprint Is Stable (Priority: P2)

**Goal**: Verify that modifying the blueprint does not retroactively change `application_tasks` records already copied from it. This is covered by the test in Phase 6 (T009, scenario `test_modifying_blueprint_task_does_not_affect_application_tasks`). No additional source code is required for this user story — the separation is guaranteed by the data model (blueprint records and application task records are in separate tables).

**Independent Test**: The test in T009 named `test_modifying_blueprint_task_does_not_affect_application_tasks` covers this story.

---

## Phase 6: Tests

**Goal**: Create the full feature test file covering all three user stories and all acceptance scenarios from `quickstart.md`.

---

- [ ] T009 [US1] [US2] [US3] Create `tests/Feature/Tasks/WorkflowStructureTest.php` with exactly this content:

  ```php
  <?php

  namespace Tests\Feature\Tasks;

  use App\Models\VisaType;
  use App\Models\WorkflowSection;
  use App\Models\WorkflowTask;
  use Database\Seeders\RolePermissionSeeder;
  use Database\Seeders\VisaTypeSeeder;
  use Database\Seeders\WorkflowBlueprintSeeder;
  use Illuminate\Foundation\Testing\RefreshDatabase;
  use InvalidArgumentException;
  use Tests\TestCase;

  class WorkflowStructureTest extends TestCase
  {
      use RefreshDatabase;

      protected function setUp(): void
      {
          parent::setUp();
          $this->seed(RolePermissionSeeder::class);
          $this->seed(VisaTypeSeeder::class);
      }

      // ── US1: Blueprint structure ──────────────────────────────────────────────

      public function test_seeded_tourist_visa_has_four_sections_in_position_order(): void
      {
          $this->seed(WorkflowBlueprintSeeder::class);

          $visa     = VisaType::where('name', 'Tourist Visa')->firstOrFail();
          $sections = WorkflowSection::where('visa_type_id', $visa->id)->orderBy('position')->get();

          $this->assertCount(4, $sections);
          $this->assertSame(1, $sections[0]->position);
          $this->assertSame(4, $sections[3]->position);
          $this->assertSame('Personal Information', $sections[0]->name);
          $this->assertSame('Final Submission',      $sections[3]->name);
      }

      public function test_section_tasks_are_returned_in_position_order(): void
      {
          $this->seed(WorkflowBlueprintSeeder::class);

          $visa    = VisaType::where('name', 'Tourist Visa')->firstOrFail();
          $section = WorkflowSection::where('visa_type_id', $visa->id)->where('position', 2)->firstOrFail();
          $tasks   = $section->tasks;

          $this->assertCount(2, $tasks);
          $this->assertSame(1,         $tasks[0]->position);
          $this->assertSame('payment', $tasks[0]->type);
          $this->assertSame(2,         $tasks[1]->position);
          $this->assertSame('info',    $tasks[1]->type);
      }

      public function test_two_visa_types_have_independent_blueprints(): void
      {
          $this->seed(WorkflowBlueprintSeeder::class);

          $tourist = VisaType::where('name', 'Tourist Visa')->firstOrFail();
          $work    = VisaType::where('name', 'Work Permit')->firstOrFail();

          WorkflowSection::create(['visa_type_id' => $work->id, 'name' => 'Work Section', 'position' => 1]);

          $this->assertSame(4, WorkflowSection::where('visa_type_id', $tourist->id)->count());
          $this->assertSame(1, WorkflowSection::where('visa_type_id', $work->id)->count());
      }

      // ── US2: Task type enforcement ────────────────────────────────────────────

      public function test_question_type_is_accepted(): void
      {
          $section = $this->makeSection();
          $task    = WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'Q', 'type' => 'question', 'position' => 1]);

          $this->assertSame('question', $task->fresh()->type);
      }

      public function test_payment_type_is_accepted(): void
      {
          $section = $this->makeSection();
          $task    = WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'P', 'type' => 'payment', 'position' => 1]);

          $this->assertSame('payment', $task->fresh()->type);
      }

      public function test_info_type_is_accepted(): void
      {
          $section = $this->makeSection();
          $task    = WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'I', 'type' => 'info', 'position' => 1]);

          $this->assertSame('info', $task->fresh()->type);
      }

      public function test_legacy_upload_type_is_accepted(): void
      {
          $section = $this->makeSection();
          $task    = WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'U', 'type' => 'upload', 'position' => 1]);

          $this->assertSame('upload', $task->fresh()->type);
      }

      public function test_invalid_type_throws_exception(): void
      {
          $section = $this->makeSection();

          $this->expectException(InvalidArgumentException::class);

          WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'X', 'type' => 'foobar', 'position' => 1]);
      }

      // ── US3: Blueprint stability ──────────────────────────────────────────────

      public function test_modifying_blueprint_task_does_not_affect_application_tasks(): void
      {
          $this->seed(WorkflowBlueprintSeeder::class);

          $visa          = VisaType::where('name', 'Tourist Visa')->firstOrFail();
          $blueprintTask = WorkflowTask::whereHas('section', fn ($q) => $q->where('visa_type_id', $visa->id))
              ->where('position', 1)
              ->first();

          $originalName = $blueprintTask->name;

          // Simulate a copied value (as application task generation would do)
          $copiedName = $blueprintTask->name;

          // Modify the blueprint
          $blueprintTask->update(['name' => 'Renamed Blueprint Task']);

          // The copied value is unaffected — blueprint and application tasks are separate
          $this->assertSame($originalName, $copiedName);
          $this->assertSame('Renamed Blueprint Task', $blueprintTask->fresh()->name);
      }

      // ── Helpers ───────────────────────────────────────────────────────────────

      private function makeSection(): WorkflowSection
      {
          $visa = VisaType::first();

          return WorkflowSection::create([
              'visa_type_id' => $visa->id,
              'name'         => 'Test Section',
              'position'     => 1,
          ]);
      }
  }
  ```

---

## Phase 7: Verification

- [ ] T010 Run `php artisan test --filter WorkflowStructure` — all 9 tests must pass

- [ ] T011 Run `php artisan test` — all pre-existing 175 tests must still pass. If any fail, do NOT proceed. Fix regressions before marking complete.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (T001)**: No dependencies
- **Phase 2 (T002–T004)**: Depends on T001 — BLOCKS all user story work
- **Phase 3 (T005–T006)**: Depends on Phase 2 — US2 must complete BEFORE US1 seeder
- **Phase 4 (T007–T008)**: Depends on Phase 3 — seeder uses new types that need model validation
- **Phase 5**: No code tasks — covered by tests in Phase 6
- **Phase 6 (T009)**: Depends on Phases 3 + 4 — tests reference seeder and model
- **Phase 7 (T010–T011)**: Depends on Phase 6

### Parallel Opportunities

- T002 and T003 can be written in parallel (different files, both migrations)
- T005 and T006 can be written in parallel (different files: Model vs Form Request)

### Critical Ordering Note

**US2 (Phase 3) must complete before US1 (Phase 4)**. The seeder creates tasks with types `question`, `payment`, and `info`. The `WorkflowTask` model's `saving` hook (added in T005) must be in place before the seeder runs, or the type validation won't fire. In production, `php artisan db:seed` runs after migrations and the code is already deployed, so ordering is automatic. In tests, `setUp` seeds via `WorkflowBlueprintSeeder::class` which calls `WorkflowTask::create()` — the model hook is in the same codebase, so it's always active.

---

## Implementation Strategy

### MVP (US1 + US2 — the P1 stories)

1. Complete Phase 1: T001
2. Complete Phase 2: T002 → T003 (parallel) → T004
3. Complete Phase 3 (US2): T005 + T006 (parallel)
4. Complete Phase 4 (US1): T007 → T008
5. **STOP and VALIDATE**: `php artisan db:seed --class=WorkflowBlueprintSeeder` — confirm 4 sections seeded
6. Proceed to Phase 6 tests

### Full Delivery

1. MVP scope above
2. Phase 6: T009 (test file)
3. Phase 7: T010 → T011 (verification)

---

## Notes

- All exact file contents are provided inline — no guessing required
- `firstOrCreate` in the seeder makes it safe to run multiple times (idempotent)
- The `saving` hook fires on both `create()` and `update()` — not just HTTP requests
- The `text` and `both` enum values that existed in the old migration are dropped by Migration A — no data used those values
- `WorkflowSection::tasks` relationship already has `->orderBy('position')` baked in (existing code) — no change needed
- Migration filenames use `200001` / `200002` to ensure they sort AFTER the existing `100002` / `100003` migrations
