<?php

namespace Tests\Feature\Reviewer;

use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use App\Services\Tasks\WorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VisaTypeSeeder;
use Database\Seeders\WorkflowStepTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewerApplicationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VisaTypeSeeder::class);
        $this->seed(WorkflowStepTemplateSeeder::class);
    }

    private function makeReviewer(): User
    {
        return User::factory()->create()->assignRole('reviewer');
    }

    private function makeApplication(): VisaApplication
    {
        $client = User::factory()->create()->assignRole('client');

        $application = VisaApplication::create([
            'user_id'                => $client->id,
            'visa_type_id'           => VisaType::first()->id,
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

        app(WorkflowService::class)->seedTasksForApplication($application);

        return $application->fresh(['tasks']);
    }

    public function test_assigned_reviewer_can_view_application(): void
    {
        $reviewer    = $this->makeReviewer();
        $application = $this->makeApplication();
        $application->update(['assigned_reviewer_id' => $reviewer->id]);

        $this->actingAs($reviewer)
            ->get(route('reviewer.applications.show', $application))
            ->assertOk();
    }

    public function test_unassigned_reviewer_cannot_view_application(): void
    {
        $reviewer    = $this->makeReviewer();
        $application = $this->makeApplication();

        $this->actingAs($reviewer)
            ->get(route('reviewer.applications.show', $application))
            ->assertForbidden();
    }

    public function test_reviewer_assigned_to_other_application_cannot_view_this_one(): void
    {
        $reviewerA   = $this->makeReviewer();
        $reviewerB   = $this->makeReviewer();
        $application = $this->makeApplication();
        $application->update(['assigned_reviewer_id' => $reviewerA->id]);

        $this->actingAs($reviewerB)
            ->get(route('reviewer.applications.show', $application))
            ->assertForbidden();
    }

    public function test_client_cannot_access_reviewer_application_show(): void
    {
        $client      = User::factory()->create()->assignRole('client');
        $application = $this->makeApplication();

        $this->actingAs($client)
            ->get(route('reviewer.applications.show', $application))
            ->assertForbidden();
    }
}
