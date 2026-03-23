<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VisaTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewerAssignmentTest extends TestCase
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

    private function makeReviewer(): User
    {
        return User::factory()->create()->assignRole('reviewer');
    }

    private function makeApplication(): VisaApplication
    {
        $client = User::factory()->create()->assignRole('client');

        return VisaApplication::create([
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
    }

    public function test_admin_can_view_application_show_page(): void
    {
        $admin = $this->makeAdmin();
        $application = $this->makeApplication();

        $this->actingAs($admin)
            ->get(route('admin.applications.show', $application))
            ->assertOk();
    }

    public function test_admin_can_assign_reviewer_to_application(): void
    {
        $admin    = $this->makeAdmin();
        $reviewer = $this->makeReviewer();
        $application = $this->makeApplication();

        $this->actingAs($admin)
            ->patch(route('admin.applications.assign-reviewer', $application), [
                'reviewer_id' => $reviewer->id,
            ])
            ->assertRedirect(route('admin.applications.show', $application));

        $this->assertSame($reviewer->id, $application->fresh()->assigned_reviewer_id);
    }

    public function test_admin_can_unassign_reviewer(): void
    {
        $admin    = $this->makeAdmin();
        $reviewer = $this->makeReviewer();
        $application = $this->makeApplication();
        $application->update(['assigned_reviewer_id' => $reviewer->id]);

        $this->actingAs($admin)
            ->patch(route('admin.applications.assign-reviewer', $application), [
                'reviewer_id' => null,
            ])
            ->assertRedirect(route('admin.applications.show', $application));

        $this->assertNull($application->fresh()->assigned_reviewer_id);
    }

    public function test_non_admin_cannot_assign_reviewer(): void
    {
        $reviewer    = $this->makeReviewer();
        $application = $this->makeApplication();

        $this->actingAs($reviewer)
            ->patch(route('admin.applications.assign-reviewer', $application), [
                'reviewer_id' => $reviewer->id,
            ])
            ->assertForbidden();
    }
}
