<?php

namespace Tests\Feature\Reviewer;

use App\Models\ApplicationTask;
use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use App\Models\WorkflowStepTemplate;
use App\Services\Tasks\WorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VisaTypeSeeder;
use Database\Seeders\WorkflowStepTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VisaTypeSeeder::class);
        $this->seed(WorkflowStepTemplateSeeder::class);
    }

    protected function makeApplicationForVisaType(VisaType $visaType): VisaApplication
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        return VisaApplication::create([
            'user_id' => $client->id,
            'visa_type_id' => $visaType->id,
            'status' => 'pending_review',
            'full_name' => $client->name,
            'email' => $client->email,
            'phone' => '+1555000123',
            'nationality' => 'Jordanian',
            'country_of_residence' => 'UAE',
            'job_title' => 'Engineer',
            'employment_type' => 'employed',
            'monthly_income' => 5000,
            'adults_count' => 1,
            'children_count' => 0,
            'application_start_date' => now()->addDays(30)->toDateString(),
            'notes' => 'Review me',
            'agreed_to_terms' => true,
        ]);
    }

    public function test_new_application_gets_correct_task_count(): void
    {
        $application = $this->makeApplicationForVisaType(VisaType::first());

        app(WorkflowService::class)->seedTasksForApplication($application);

        $this->assertSame(6, $application->tasks()->count());
    }

    public function test_different_visa_types_get_different_steps(): void
    {
        $visaTypeA = VisaType::first();
        $visaTypeB = VisaType::skip(1)->first();

        WorkflowStepTemplate::create([
            'visa_type_id' => $visaTypeB->id,
            'position' => 7,
            'name' => 'Extra Step',
            'description' => 'Extra review',
            'is_document_required' => false,
        ]);

        $applicationA = $this->makeApplicationForVisaType($visaTypeA);
        $applicationB = $this->makeApplicationForVisaType($visaTypeB);

        app(WorkflowService::class)->seedTasksForApplication($applicationA);
        app(WorkflowService::class)->seedTasksForApplication($applicationB);

        $this->assertSame(6, $applicationA->tasks()->count());
        $this->assertSame(7, $applicationB->tasks()->count());
    }

    public function test_application_with_no_template_stays_pending_review(): void
    {
        $visaType = VisaType::create(['name' => 'Custom Visa', 'description' => null, 'is_active' => true]);
        $application = $this->makeApplicationForVisaType($visaType);

        app(WorkflowService::class)->seedTasksForApplication($application);

        $this->assertSame('pending_review', $application->fresh()->status);
        $this->assertSame(0, $application->tasks()->count());
    }

    public function test_artisan_command_seeds_existing_applications(): void
    {
        $application = $this->makeApplicationForVisaType(VisaType::first());

        $this->assertSame(0, $application->tasks()->count());

        $this->artisan('workflow:seed-tasks')->assertExitCode(0);

        $this->assertSame(6, $application->fresh()->tasks()->count());
    }

    public function test_artisan_command_is_idempotent(): void
    {
        $application = $this->makeApplicationForVisaType(VisaType::first());

        $this->artisan('workflow:seed-tasks')->assertExitCode(0);
        $this->artisan('workflow:seed-tasks')->assertExitCode(0);

        $this->assertSame(6, $application->fresh()->tasks()->count());
    }
}
