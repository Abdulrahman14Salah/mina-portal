<?php

namespace Tests\Feature\Admin;

use App\Models\Payment;
use App\Models\User;
use App\Models\VisaApplication;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_view_payments_page(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $application = VisaApplication::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.applications.payments.index', $application));

        $response->assertStatus(200);
    }

    public function test_admin_can_mark_stage_due(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $application = VisaApplication::factory()->create();
        $payment = Payment::factory()->create(['application_id' => $application->id, 'status' => 'pending']);

        $response = $this->actingAs($admin)
            ->patch(route('admin.applications.payments.mark-due', [$application, $payment]));

        $response->assertRedirect(route('admin.applications.payments.index', $application));

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'due',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'payment_stage_marked_due',
        ]);
    }

    public function test_mark_due_is_idempotent_on_already_due_stage(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $application = VisaApplication::factory()->create();
        $payment = Payment::factory()->create(['application_id' => $application->id, 'status' => 'due']);

        $this->actingAs($admin)
            ->patch(route('admin.applications.payments.mark-due', [$application, $payment]))
            ->assertRedirect(route('admin.applications.payments.index', $application));

        $auditLogs = \DB::table('audit_logs')
            ->where('event', 'payment_stage_marked_due')
            ->whereJsonContains('metadata->payment_id', $payment->id)
            ->count();

        $this->assertEquals(0, $auditLogs);
    }

    public function test_reviewer_cannot_mark_stage_due(): void
    {
        $reviewer = User::factory()->create()->assignRole('reviewer');
        $application = VisaApplication::factory()->create();
        $payment = Payment::factory()->create(['application_id' => $application->id, 'status' => 'pending']);

        $response = $this->actingAs($reviewer)
            ->patch(route('admin.applications.payments.mark-due', [$application, $payment]));

        $response->assertForbidden();
    }

    public function test_client_cannot_access_admin_payments_index(): void
    {
        $client = User::factory()->create()->assignRole('client');
        $application = VisaApplication::factory()->create();

        $response = $this->actingAs($client)
            ->get(route('admin.applications.payments.index', $application));

        $response->assertForbidden();
    }
}
