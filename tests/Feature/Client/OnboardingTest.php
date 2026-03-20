<?php

namespace Tests\Feature\Client;

use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VisaTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(VisaTypeSeeder::class);
    }

    protected function validPayload(): array
    {
        return [
            'full_name' => 'Client Applicant',
            'email' => 'client@app.test',
            'password' => 'StrongPass1',
            'password_confirmation' => 'StrongPass1',
            'phone' => '+1555000123',
            'nationality' => 'Jordanian',
            'country_of_residence' => 'UAE',
            'visa_type_id' => VisaType::first()->id,
            'adults_count' => 1,
            'children_count' => 0,
            'application_start_date' => now()->addDays(30)->toDateString(),
            'job_title' => 'Engineer',
            'employment_type' => 'employed',
            'monthly_income' => 5000,
            'agreed_to_terms' => 1,
        ];
    }

    public function test_onboarding_form_loads(): void
    {
        $this->get('/apply')->assertOk();
    }

    public function test_valid_submission_creates_client_and_application(): void
    {
        $response = $this->post('/apply', $this->validPayload());

        $user = User::where('email', 'client@app.test')->first();
        $application = VisaApplication::first();

        $response->assertRedirect(route('client.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole('client'));
        $this->assertDatabaseHas('visa_applications', [
            'user_id' => $user->id,
            'status' => 'pending_review',
            'agreed_to_terms' => 1,
        ]);
        $this->assertMatchesRegularExpression('/^APP-\d{5}$/', $application->reference_number);
    }

    public function test_duplicate_email_rejected(): void
    {
        User::factory()->create(['email' => 'client@app.test']);

        $response = $this->from('/apply')->post('/apply', $this->validPayload());

        $response->assertRedirect('/apply');
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('visa_applications', 0);
    }

    public function test_missing_required_field_rejected(): void
    {
        $payload = $this->validPayload();
        unset($payload['full_name']);

        $response = $this->from('/apply')->post('/apply', $payload);

        $response->assertSessionHasErrors('full_name');
    }

    public function test_consent_unchecked_rejected(): void
    {
        $payload = $this->validPayload();
        unset($payload['agreed_to_terms']);

        $response = $this->from('/apply')->post('/apply', $payload);

        $response->assertSessionHasErrors('agreed_to_terms');
    }

    public function test_authenticated_client_redirected_from_form(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)->get('/apply')->assertRedirect(route('dashboard'));
    }

    public function test_audit_log_created_on_submission(): void
    {
        $this->post('/apply', $this->validPayload());

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'application_created',
        ]);
    }
}
