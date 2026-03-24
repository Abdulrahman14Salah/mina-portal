<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\VisaType;
use App\Models\WorkflowSection;
use App\Models\WorkflowTask;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VisaTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowSectionTaskBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VisaTypeSeeder::class);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create()->assignRole('admin');
    }

    public function test_admin_can_view_task_builder(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.task-builder.index'))
            ->assertOk();
    }

    public function test_admin_can_create_workflow_section(): void
    {
        $admin    = $this->makeAdmin();
        $visaType = VisaType::first();

        $this->actingAs($admin)
            ->post(route('admin.task-builder.sections.store'), [
                'visa_type_id' => $visaType->id,
                'name'         => 'Personal Documents',
            ])
            ->assertRedirect(route('admin.task-builder.index'));

        $this->assertDatabaseHas('workflow_sections', [
            'visa_type_id' => $visaType->id,
            'name'         => 'Personal Documents',
        ]);
    }

    public function test_admin_can_add_task_to_section(): void
    {
        $admin   = $this->makeAdmin();
        $section = WorkflowSection::create([
            'visa_type_id' => VisaType::first()->id,
            'name'         => 'Personal Documents',
            'position'     => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.task-builder.tasks.store', $section), [
                'name'        => 'Passport Scan',
                'description' => 'Upload a clear scan of your passport',
                'type'        => 'upload',
            ])
            ->assertRedirect(route('admin.task-builder.index'));

        $this->assertDatabaseHas('workflow_tasks', [
            'workflow_section_id' => $section->id,
            'name'                => 'Passport Scan',
            'type'                => 'upload',
        ]);
    }

    public function test_admin_can_delete_section(): void
    {
        $admin   = $this->makeAdmin();
        $section = WorkflowSection::create([
            'visa_type_id' => VisaType::first()->id,
            'name'         => 'To Delete',
            'position'     => 1,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.task-builder.sections.destroy', $section))
            ->assertRedirect(route('admin.task-builder.index'));

        $this->assertDatabaseMissing('workflow_sections', ['id' => $section->id]);
    }

    public function test_admin_can_delete_task(): void
    {
        $admin   = $this->makeAdmin();
        $section = WorkflowSection::create([
            'visa_type_id' => VisaType::first()->id,
            'name'         => 'Section',
            'position'     => 1,
        ]);
        $task = WorkflowTask::create([
            'workflow_section_id' => $section->id,
            'name'                => 'To Delete',
            'type'                => 'upload',
            'position'            => 1,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.task-builder.tasks.destroy', $task))
            ->assertRedirect(route('admin.task-builder.index'));

        $this->assertDatabaseMissing('workflow_tasks', ['id' => $task->id]);
    }

    public function test_non_admin_cannot_access_task_builder(): void
    {
        $client = User::factory()->create()->assignRole('client');

        $this->actingAs($client)
            ->get(route('admin.task-builder.index'))
            ->assertForbidden();
    }
}
