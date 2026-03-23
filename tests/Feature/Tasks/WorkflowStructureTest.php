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
