<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Models\VisaApplication;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config(['services.stripe.webhook_secret' => 'test_webhook_secret']);
    }

    private function signWebhookPayload(string $payload, string $secret = 'test_webhook_secret'): string
    {
        $timestamp = time();
        $signed = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signed, $secret);

        return "t={$timestamp},v1={$signature}";
    }

    private function postWebhook(string $payload, string $signature): TestResponse
    {
        return $this->call(
            'POST',
            route('payments.webhook'),
            [],
            [],
            [],
            [
                'HTTP_Stripe-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload
        );
    }

    public function test_valid_checkout_completed_webhook_marks_payment_paid(): void
    {
        $user = User::factory()->create()->assignRole('client');
        $application = VisaApplication::factory()->for($user)->create();
        $payment = Payment::factory()->create(['application_id' => $application->id, 'status' => 'due']);

        $payload = json_encode([
            'id' => 'evt_test',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test',
                    'metadata' => ['payment_id' => (string) $payment->id],
                    'payment_intent' => 'pi_test',
                ],
            ],
        ]);

        $signature = $this->signWebhookPayload($payload);

        $this->postWebhook($payload, $signature)->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
            'stripe_payment_intent_id' => 'pi_test',
        ]);

        $payment->refresh();
        $this->assertNotNull($payment->paid_at);
    }

    public function test_invalid_signature_returns_400(): void
    {
        $payload = json_encode(['type' => 'checkout.session.completed']);
        $invalidSignature = 't=1234567890,v1=invalid_signature';

        $this->postWebhook($payload, $invalidSignature)->assertStatus(400);
    }

    public function test_duplicate_webhook_is_idempotent(): void
    {
        $user = User::factory()->create()->assignRole('client');
        $application = VisaApplication::factory()->for($user)->create();
        $payment = Payment::factory()->create(['application_id' => $application->id, 'status' => 'due']);

        $payload = json_encode([
            'id' => 'evt_test',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test',
                    'metadata' => ['payment_id' => (string) $payment->id],
                    'payment_intent' => 'pi_test',
                ],
            ],
        ]);

        $signature = $this->signWebhookPayload($payload);

        $this->postWebhook($payload, $signature)->assertStatus(200);
        $this->postWebhook($payload, $signature)->assertStatus(200);

        $auditLogs = \DB::table('audit_logs')
            ->where('event', 'payment_confirmed')
            ->whereJsonContains('metadata->payment_id', $payment->id)
            ->count();

        $this->assertEquals(1, $auditLogs);
    }

    public function test_payment_failed_webhook_sets_failed_status(): void
    {
        $user = User::factory()->create()->assignRole('client');
        $application = VisaApplication::factory()->for($user)->create();
        $payment = Payment::factory()->create(['application_id' => $application->id, 'status' => 'due']);

        $payload = json_encode([
            'id' => 'evt_test',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_test',
                    'metadata' => ['payment_id' => (string) $payment->id],
                ],
            ],
        ]);

        $signature = $this->signWebhookPayload($payload);

        $this->postWebhook($payload, $signature)->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);
    }

    public function test_missing_payment_id_in_metadata_is_a_noop(): void
    {
        $payload = json_encode([
            'id' => 'evt_test',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test',
                    'metadata' => [],
                    'payment_intent' => 'pi_test',
                ],
            ],
        ]);

        $signature = $this->signWebhookPayload($payload);

        $this->postWebhook($payload, $signature)->assertStatus(200);
    }
}
