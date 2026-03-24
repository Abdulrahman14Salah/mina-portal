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
        $tasks[0]->update(['status' => 'pending_review']);

        app(WorkflowService::class)->approveTask($tasks[0], null);

        $this->assertSame('approved',    $tasks[0]->fresh()->status);
        $this->assertSame('in_progress', $tasks[1]->fresh()->status, 'Task at position 3 must become in_progress after approve');
    }
}
