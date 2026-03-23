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

class WorkflowSectionSeedingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VisaTypeSeeder::class);
        // Deliberately NOT seeding WorkflowStepTemplateSeeder here
    }

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

    public function test_tasks_seeded_from_workflow_sections_when_present(): void
    {
        $visaType = VisaType::first();

        $sectionA = WorkflowSection::create(['visa_type_id' => $visaType->id, 'name' => 'Section A', 'position' => 1]);
        $sectionB = WorkflowSection::create(['visa_type_id' => $visaType->id, 'name' => 'Section B', 'position' => 2]);

        WorkflowTask::create(['workflow_section_id' => $sectionA->id, 'name' => 'Task A1', 'type' => 'upload',   'position' => 1]);
        WorkflowTask::create(['workflow_section_id' => $sectionA->id, 'name' => 'Task A2', 'type' => 'question', 'position' => 2]);
        WorkflowTask::create(['workflow_section_id' => $sectionB->id, 'name' => 'Task B1', 'type' => 'payment',  'position' => 1]);

        $application = $this->makeApplication($visaType);
        app(WorkflowService::class)->seedTasksForApplication($application);

        $tasks = ApplicationTask::where('application_id', $application->id)->orderBy('position')->get();

        $this->assertCount(3, $tasks);
        $this->assertSame('Task A1', $tasks[0]->name);
        $this->assertSame('upload', $tasks[0]->type);
        $this->assertSame('Task A2', $tasks[1]->name);
        $this->assertSame('question', $tasks[1]->type);
        $this->assertSame('Task B1', $tasks[2]->name);
        $this->assertSame('payment', $tasks[2]->type);
        $this->assertSame('in_progress', $tasks[0]->status);
    }

    public function test_tasks_seeded_from_flat_templates_when_no_sections(): void
    {
        $visaType = VisaType::first();

        DB::table('workflow_step_templates')->insert([
            'visa_type_id'         => $visaType->id,
            'name'                 => 'Legacy Step 1',
            'description'          => null,
            'position'             => 1,
            'is_document_required' => 1,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $application = $this->makeApplication($visaType);
        app(WorkflowService::class)->seedTasksForApplication($application);

        $tasks = ApplicationTask::where('application_id', $application->id)->get();
        $this->assertCount(1, $tasks);
        $this->assertSame('Legacy Step 1', $tasks[0]->name);
    }
}
