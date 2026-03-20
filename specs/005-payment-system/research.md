# Research: Phase 5 — Payment System

**Date**: 2026-03-20
**Status**: Complete

---

## Decision 1: Stripe SDK Installation and Configuration

**Decision**: Use `stripe/stripe-php` official SDK. Store API keys in `config/services.php` under `stripe.*`. Initialize the API key inside `PaymentService::__construct()` via `\Stripe\Stripe::setApiKey(config('services.stripe.key'))` — not globally in a service provider.

**Rationale**: Lazy initialization inside the service prevents the Stripe API key from being loaded on every request, only when payment functionality is actually invoked. Config-driven keys satisfy Constitution Principle IX (no hardcoded credentials).

**Alternatives considered**: `cashier` (Laravel Cashier for Stripe) — rejected because Cashier is designed for subscriptions and recurring billing. Phase 5 needs one-time Stripe Checkout sessions, and Cashier adds unnecessary abstraction for this use case.

**Config entry** (add to `config/services.php`):
```php
'stripe' => [
    'key'             => env('STRIPE_KEY'),
    'secret'          => env('STRIPE_SECRET'),
    'webhook_secret'  => env('STRIPE_WEBHOOK_SECRET'),
    'currency'        => env('PAYMENT_CURRENCY', 'usd'),
],
```

---

## Decision 2: Stripe Checkout Session Creation Pattern

**Decision**: Use `\Stripe\Checkout\Session::create()` with inline `price_data` (not pre-created Stripe Price objects). Always include `metadata['payment_id']` (the portal's `Payment.id` cast to string) to enable deterministic webhook→payment matching.

**Rationale**: Inline `price_data` avoids needing to manage Stripe Product/Price objects in the dashboard for every visa type config change. `metadata['payment_id']` is the canonical lookup key — more reliable than matching on `stripe_session_id` since Stripe copies metadata to the payment intent, enabling matching across multiple event types.

**Session creation pattern**:
```php
\Stripe\Checkout\Session::create([
    'mode'        => 'payment',
    'line_items'  => [[
        'price_data' => [
            'currency'     => $payment->currency,
            'unit_amount'  => $payment->amount,  // smallest unit (fils/cents)
            'product_data' => ['name' => $payment->name],
        ],
        'quantity' => 1,
    ]],
    'metadata'    => ['payment_id' => (string) $payment->id],
    'success_url' => route('client.payments.success') . '?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'  => route('client.payments.cancel'),
]);
```

---

## Decision 3: CSRF Exclusion for Webhook Endpoint

**Decision**: Exclude `payments/webhook` from CSRF verification using `validateCsrfTokens(except: ['payments/webhook'])` inside the `withMiddleware()` callback in `bootstrap/app.php`. Do NOT publish or extend `VerifyCsrfToken` (the Laravel 10 approach — no longer idiomatic in Laravel 11).

**Rationale**: The webhook endpoint receives server-to-server POST requests from Stripe — no browser session, no CSRF token. Stripe's webhook signature provides its own authentication mechanism. Laravel 11's `bootstrap/app.php` approach is the correct method.

**Implementation** in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'payments/webhook',
    ]);
})
```

---

## Decision 4: Raw Request Body for Webhook Signature Verification

**Decision**: Use `$request->getContent()` to read the raw request body. This is Laravel's method for raw body access and is equivalent to `file_get_contents('php://input')` — both work correctly before any JSON parsing.

**Rationale**: Stripe signature verification requires the raw byte-for-byte body. If JSON is re-encoded after parsing, the byte representation may differ, breaking the signature check. `$request->getContent()` ensures no transformation occurs.

**Implementation**:
```php
$payload   = $request->getContent();
$sigHeader = $request->header('Stripe-Signature');
$event     = \Stripe\Webhook::constructEvent($payload, $sigHeader, config('services.stripe.webhook_secret'));
```

On invalid signature: `\Stripe\Exception\SignatureVerificationException` is thrown — catch and return HTTP 400.

---

## Decision 5: Idempotent Webhook Processing

**Decision**: Use an atomic conditional DB update (`Payment::whereKey($id)->where('status', 'due')->update([...])`) as the primary idempotency guard. No separate `stripe_webhook_events` tracking table needed. The `status` field on the `Payment` model serves as the idempotency state.

**Rationale**: Matches the pattern used in Phase 3/4 (`VisaApplication::whereKey()->where('status','in_progress')->update()`). Simple and reliable: if a webhook arrives twice for the same completed session, the second `update()` finds zero rows (status is already `paid`) and is a safe no-op. Avoids the complexity of a secondary events table.

**Pattern**:
```php
// In PaymentService::handleWebhookEvent() for checkout.session.completed:
$affected = Payment::whereKey($paymentId)
    ->where('status', 'due')
    ->update([
        'status'                    => 'paid',
        'stripe_payment_intent_id'  => $session->payment_intent,
        'paid_at'                   => now(),
    ]);

if ($affected === 0) {
    return; // Already paid — idempotent no-op
}
```

---

## Decision 6: Payment Stage Amount Storage

**Decision**: Separate `payment_stage_configs` table with columns `(visa_type_id, stage, name, amount, currency)`. One row per stage per visa type (3 rows per visa type). Amounts stored as `unsignedInteger` in smallest currency unit (fils, cents, etc.). At payment creation time, these values are **copied** into the `Payment` record — the Payment record stores the amount actually charged, not a foreign key to the config.

**Rationale**: Separate table aligns with Constitution Principle III (database-driven, no hardcoded amounts). Copying values to the Payment record at creation time preserves the historical charge amount even if config is later updated — critical for financial record integrity (Constitution Principle VI: "payment records must never be modified retroactively").

**Alternatives considered**:
- 6 columns on `visa_types` (stage_1_name, stage_1_amount, etc.) — rejected because it pollutes the visa_types schema and makes future stage count changes harder.
- Foreign key from `payments` to `payment_stage_configs` — rejected because it creates a mutable reference; if the config amount changes, historical payment records would appear to show the new amount.

---

## Decision 7: Queued Payment Confirmation Email

**Decision**: Use `Mail::to($user->email)->queue(new PaymentConfirmedMail($payment))` dispatched from `PaymentService::handleWebhookEvent()` after a successful `checkout.session.completed` event. The Mailable uses the `Queueable` trait. Queue driver: `database` (minimum per constitution; upgradeable to Redis in production).

**Rationale**: Constitution Principle VI explicitly requires email confirmation on payment success. Queuing prevents the webhook response from being delayed by mail server latency — Stripe requires a 200 response within seconds to avoid retries.

**Mailable location**: `app/Mail/PaymentConfirmedMail.php` — passes the `Payment` model (with eager-loaded `application.user`). Template at `resources/views/mail/payment-confirmed.blade.php`.

---

## Decision 8: Webhook Testing Strategy

**Decision**: For `PaymentWebhookTest`, generate valid Stripe webhook signatures in the test using the HMAC algorithm directly (no Stripe test mode calls). Set a test webhook secret (`whsec_test`) in test config. For controller-level tests that involve `PaymentService::createCheckoutSession()`, mock the service to return a fake URL.

**Rationale**: Using real Stripe API calls in tests creates external dependency, slows tests, and risks hitting rate limits. Generating a valid HMAC signature in tests is deterministic and mirrors how Stripe actually signs payloads.

**Test signature helper**:
```php
private function signWebhookPayload(string $payload, string $secret): string
{
    $timestamp = time();
    $signed    = $timestamp . '.' . $payload;
    $signature = hash_hmac('sha256', $signed, $secret);
    return "t={$timestamp},v1={$signature}";
}
```

**Test config** (`phpunit.xml` or test setUp):
```php
config(['services.stripe.webhook_secret' => 'test_webhook_secret']);
```

---

## Decision 9: `failed` Status Handling

**Decision**: The `failed` status is set when `payment_intent.payment_failed` fires (card declined). A payment in `failed` state can be retried by the client — `PaymentPolicy::pay()` allows both `due` AND `failed` status. Clicking "Pay Now" on a failed payment creates a new Checkout session and resets status to `due` before redirecting.

**Rationale**: Card declines are a normal payment flow; clients must be able to retry. Session abandonment (no card attempted) does NOT trigger `failed` — the stage simply remains `due` with an overwritten `stripe_session_id` on the next attempt. Webhook for `payment_intent.payment_failed` uses `metadata['payment_id']` for lookup (Stripe copies checkout session metadata to the payment intent object).

---

## Decision 10: `PaymentService::seedPaymentsForApplication()` Integration Point

**Decision**: Called from `OnboardingService::handle()` immediately after `WorkflowService::seedTasksForApplication($application)`. Creates 3 `Payment` records per application from the visa type's `PaymentStageConfig` rows. Stage 1 status = `due`, Stages 2 & 3 status = `pending`.

**Rationale**: Application creation is the correct trigger for seeding payment records (FR-014). Placing this in `OnboardingService` keeps all application-creation side effects in one place, consistent with the Phase 3 pattern.
