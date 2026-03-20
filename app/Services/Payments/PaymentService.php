<?php

namespace App\Services\Payments;

use App\Mail\PaymentConfirmedMail;
use App\Models\Payment;
use App\Models\User;
use App\Models\VisaApplication;
use App\Services\Auth\AuditLogService;
use Illuminate\Support\Facades\Mail;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Stripe;

class PaymentService
{
    public function __construct(
        protected AuditLogService $auditLog
    ) {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function seedPaymentsForApplication(VisaApplication $application): void
    {
        $configs = $application->visaType->paymentStageConfigs;

        foreach ($configs as $config) {
            Payment::create([
                'application_id' => $application->id,
                'stage' => $config->stage,
                'name' => $config->name,
                'amount' => $config->amount,
                'currency' => $config->currency,
                'status' => ($config->stage === 1 ? 'due' : 'pending'),
            ]);
        }
    }

    public function createCheckoutSession(Payment $payment, User $user): string
    {
        if ($payment->status === 'failed') {
            Payment::whereKey($payment->id)->update(['status' => 'due']);
            $payment->status = 'due';
        }

        $session = Session::create([
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => $payment->currency,
                    'unit_amount' => $payment->amount,
                    'product_data' => ['name' => $payment->name],
                ],
                'quantity' => 1,
            ]],
            'metadata' => ['payment_id' => (string) $payment->id],
            'success_url' => route('client.payments.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('client.payments.cancel'),
        ]);

        Payment::whereKey($payment->id)->update(['stripe_session_id' => $session->id]);

        $this->auditLog->log('payment_initiated', $user, [
            'payment_id' => $payment->id,
            'stage' => $payment->stage,
            'reference' => $payment->application->reference_number,
        ]);

        return $session->url;
    }

    public function markStageAsDue(Payment $payment): void
    {
        if ($payment->status !== 'pending') {
            return;
        }

        Payment::whereKey($payment->id)->update(['status' => 'due']);

        $this->auditLog->log('payment_stage_marked_due', null, [
            'payment_id' => $payment->id,
            'stage' => $payment->stage,
            'reference' => $payment->application->reference_number,
        ]);
    }

    public function handleWebhookEvent(Event $event): void
    {
        $object = $event->data->object;
        $paymentId = $object->metadata?->payment_id ?? null;

        match ($event->type) {
            'checkout.session.completed' => $this->handleSessionCompleted($object, $paymentId),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($object, $paymentId),
            default => $this->auditLog->log('webhook_unhandled', null, ['type' => $event->type]),
        };
    }

    public function confirmFromSuccessUrl(string $sessionId): void
    {
        $session = Session::retrieve($sessionId);

        if ($session->payment_status !== 'paid') {
            return;
        }

        $paymentId = $session->metadata->payment_id ?? null;

        $this->handleSessionCompleted($session, $paymentId);
    }

    private function handleSessionCompleted(object $session, ?string $paymentId): void
    {
        if (! $paymentId) {
            return;
        }

        $affected = Payment::whereKey($paymentId)
            ->where('status', 'due')
            ->update([
                'status' => 'paid',
                'stripe_payment_intent_id' => $session->payment_intent,
                'paid_at' => now(),
            ]);

        if ($affected === 0) {
            return;
        }

        $payment = Payment::with('application.user')->find($paymentId);

        $this->auditLog->log('payment_confirmed', null, [
            'payment_id' => $payment->id,
            'stage' => $payment->stage,
            'reference' => $payment->application->reference_number,
        ]);

        Mail::to($payment->application->user->email)->queue(new PaymentConfirmedMail($payment));

        // Automatically unlock the next payment stage
        $nextPayment = Payment::where('application_id', $payment->application_id)
            ->where('stage', $payment->stage + 1)
            ->where('status', 'pending')
            ->first();

        if ($nextPayment) {
            $this->markStageAsDue($nextPayment);
        }
    }

    private function handlePaymentFailed(object $intent, ?string $paymentId): void
    {
        if (! $paymentId) {
            return;
        }

        Payment::whereKey($paymentId)
            ->where('status', 'due')
            ->update(['status' => 'failed']);

        $this->auditLog->log('payment_failed', null, ['payment_id' => $paymentId]);
    }
}
