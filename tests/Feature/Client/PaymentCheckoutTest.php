<?php

namespace Tests\Feature\Client;

use App\Models\Payment;
use App\Models\User;
use App\Models\VisaApplication;
use App\Services\Payments\PaymentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_client_is_redirected_to_stripe_on_checkout(): void
    {
        $user = User::factory()->create()->assignRole('client');
        $application = VisaApplication::factory()->for($user)->create();
        $payment = Payment::factory()->create(['application_id' => $application->id, 'status' => 'due']);

        $this->mock(PaymentService::class, function ($mock) {
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->andReturn('https://checkout.stripe.com/test_session');
        });

        $response = $this->actingAs($user)->get(route('client.payments.checkout', $payment));

        $response->assertRedirect('https://checkout.stripe.com/test_session');
    }

    public function test_unauthenticated_is_redirected_to_login(): void
    {
        $application = VisaApplication::factory()->create();
        $payment = Payment::factory()->create(['application_id' => $application->id, 'status' => 'due']);

        $response = $this->get(route('client.payments.checkout', $payment));

        $response->assertRedirect('/login');
    }

    public function test_cross_client_gets_403(): void
    {
        $userA = User::factory()->create()->assignRole('client');
        $userB = User::factory()->create()->assignRole('client');
        $applicationA = VisaApplication::factory()->for($userA)->create();
        $payment = Payment::factory()->create(['application_id' => $applicationA->id, 'status' => 'due']);

        $response = $this->actingAs($userB)->get(route('client.payments.checkout', $payment));

        $response->assertForbidden();
    }

    public function test_cannot_pay_pending_stage(): void
    {
        $user = User::factory()->create()->assignRole('client');
        $application = VisaApplication::factory()->for($user)->create();
        $payment = Payment::factory()->create(['application_id' => $application->id, 'status' => 'pending']);

        $response = $this->actingAs($user)->get(route('client.payments.checkout', $payment));

        $response->assertForbidden();
    }

    public function test_cannot_pay_paid_stage(): void
    {
        $user = User::factory()->create()->assignRole('client');
        $application = VisaApplication::factory()->for($user)->create();
        $payment = Payment::factory()->create(['application_id' => $application->id, 'status' => 'paid']);

        $response = $this->actingAs($user)->get(route('client.payments.checkout', $payment));

        $response->assertForbidden();
    }

    public function test_success_redirects_to_dashboard_with_flash(): void
    {
        $user = User::factory()->create()->assignRole('client');

        $response = $this->actingAs($user)->get(route('client.payments.success'));

        $response->assertRedirect(route('client.dashboard', ['tab' => 'payments']));
        $response->assertSessionHas('success');
    }

    public function test_cancel_redirects_to_dashboard_with_flash(): void
    {
        $user = User::factory()->create()->assignRole('client');

        $response = $this->actingAs($user)->get(route('client.payments.cancel'));

        $response->assertRedirect(route('client.dashboard', ['tab' => 'payments']));
        $response->assertSessionHas('info');
    }
}
