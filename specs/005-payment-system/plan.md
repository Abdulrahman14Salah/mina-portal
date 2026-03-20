# Implementation Plan: Phase 5 — Payment System

**Branch**: `005-payment-system` | **Date**: 2026-03-20 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/005-payment-system/spec.md`

## Summary

Implement a three-stage Stripe Checkout payment system for the Visa Application Portal: Stage 1 is automatically due when an application is created; Stages 2 and 3 are manually unlocked by an admin. Clients pay via Stripe's hosted Checkout page. Payment confirmation is driven exclusively by Stripe webhooks (not redirect callbacks). `PaymentService` owns all Stripe API calls, status transitions, and audit logging. A queued `PaymentConfirmedMail` is dispatched on each successful payment, satisfying Constitution Principle VI. Built on Laravel 11 / Blade SSR with `PaymentPolicy` enforcing all access control.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 11
**Primary Dependencies**: Laravel Breeze (Blade), Alpine.js v3, `spatie/laravel-permission` v6+, `stripe/stripe-php` (official Stripe PHP SDK)
**Storage**: MySQL (MAMP local dev); SQLite in-memory (tests)
**Testing**: PHPUnit via Laravel feature tests
**Target Platform**: Web server — MAMP (local), Linux (production)
**Performance Goals**: Client completes payment in under 3 minutes (SC-001); webhook confirmation within 30 seconds (SC-002)
**Constraints**: No SPA; Blade SSR; CSRF enabled on web routes (webhook endpoint excluded via `bootstrap/app.php`); all protected routes behind `auth` middleware; no inline `$request->validate()`; no `$guarded = []`; all strings via `__()`; no card data stored — Stripe Checkout only; amounts stored in smallest currency unit (fils/cents as `unsignedInteger`); queue driver minimum: `database`
**Scale/Scope**: Up to hundreds of active applications; exactly 3 payment records per application

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| # | Principle | Status | Notes |
|---|-----------|--------|-------|
| I | Modular Architecture | ✅ PASS | New `Payments` module: `Client\PaymentController`, `Admin\PaymentController`, `PaymentWebhookController`, `PaymentService`, `PaymentPolicy`, `Payment` model, `PaymentStageConfig` model. |
| II | Separation of Concerns | ✅ PASS | `PaymentService` owns all Stripe API calls, session creation, webhook processing, status transitions, audit logging, and email dispatch. Controllers delegate entirely. Zero business logic in Blade. |
| III | Database-Driven Workflows | ✅ PASS | Payment stage names and amounts stored in `payment_stage_configs` table per visa type. Values are NOT hardcoded in PHP. Admin can update amounts in the database without code changes (SC-005). |
| IV | API-Ready Design | ✅ PASS | All payment data accessed via `PaymentService`. Controllers pass models to views. No direct Eloquent in controllers. |
| V | Roles & Permissions | ✅ PASS | Two new permissions: `payments.pay` (client), `payments.manage` (admin). `PaymentPolicy` enforces per-payment authorization. `$user->can()` gates only — no hardcoded role checks. |
| VI | Payment Integrity | ✅ PASS | This IS the implementation of Principle VI. Stripe Checkout only — no card data stored. Each stage is a separate `Payment` record with its own status lifecycle. Stripe reference (`stripe_payment_intent_id`) stored on confirmation. Webhook confirmation required — redirect callback is informational only. Queued `PaymentConfirmedMail` sent on each `checkout.session.completed` event. |
| VII | Secure Document Handling | ✅ N/A | No file uploads in Phase 5. |
| VIII | Dynamic Workflow Engine | ✅ PASS | Stage names and amounts from `payment_stage_configs` database table — not hardcoded. Phase 3 workflow engine unchanged. |
| IX | Security by Default | ✅ PASS | Webhook signature verification via `\Stripe\Webhook::constructEvent()` before any state change. `$fillable` on `Payment` and `PaymentStageConfig` models. CSRF on all web routes; webhook excluded via `bootstrap/app.php`. Auth middleware on all client and admin routes. `PaymentPolicy` registered in `AppServiceProvider`. No inline `$request->validate()`. |
| X | Multi-Language Support | ✅ PASS | New lang files: `resources/lang/en/payments.php` + `resources/lang/ar/payments.php` (content); `lang/en/payments.php` + `lang/ar/payments.php` (proxies). All Blade strings via `__('payments.*')`. |
| XI | Observability & Activity Logging | ✅ PASS | `AuditLogService::log()` called for: `payment_initiated` (checkout session created), `payment_confirmed` (webhook success), `payment_failed` (webhook failure), `payment_stage_marked_due` (admin unlocks stage). |
| XII | Testing Standards | ✅ PASS | Feature tests: client initiates checkout, cross-client 403, unauthenticated redirect, cannot pay non-due stage, webhook confirms payment, invalid signature rejected, duplicate webhook idempotent, admin marks as due, reviewer cannot manage, client cannot access admin payments. |

**Constitution Gate**: PASS — no violations. No Complexity Tracking entries required.

## Project Structure

### Documentation (this feature)

```text
specs/005-payment-system/
├── plan.md              ← This file
├── spec.md              ← Feature specification
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output
├── quickstart.md        ← Phase 1 output
├── contracts/
│   └── routes.md        ← Phase 1 output
└── tasks.md             ← Phase 2 output (/speckit.tasks — not created here)
```

### Source Code Layout

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── PaymentWebhookController.php                   # POST /payments/webhook (no auth, no CSRF)
│   │   ├── Client/
│   │   │   └── PaymentController.php                      # GET checkout, success, cancel
│   │   └── Admin/
│   │       └── PaymentController.php                      # GET index, PATCH mark-due
│   └── Requests/
│       └── Admin/
│           └── MarkPaymentDueRequest.php                  # authorize() only, empty rules()
├── Mail/
│   └── PaymentConfirmedMail.php                           # Queued mailable for payment confirmation
├── Models/
│   ├── Payment.php                                        # NEW
│   ├── PaymentStageConfig.php                             # NEW
│   ├── VisaApplication.php                                # MODIFY: add payments() HasMany
│   └── VisaType.php                                       # MODIFY: add paymentStageConfigs() HasMany
├── Policies/
│   └── PaymentPolicy.php                                  # pay(), manage()
├── Services/
│   └── Payments/
│       └── PaymentService.php                             # seedPaymentsForApplication(), createCheckoutSession(), markStageAsDue(), handleWebhookEvent()
└── Providers/
    └── AppServiceProvider.php                             # MODIFY: register PaymentPolicy

bootstrap/
└── app.php                                                # MODIFY: exclude payments/webhook from CSRF

config/
└── services.php                                           # MODIFY: add stripe.key, stripe.secret, stripe.webhook_secret, stripe.currency

database/
├── migrations/
│   ├── xxxx_create_payment_stage_configs_table.php
│   └── xxxx_create_payments_table.php
└── seeders/
    ├── PaymentStageConfigSeeder.php                       # NEW: seeds 3 stages per visa type
    └── RolePermissionSeeder.php                           # MODIFY: add payments.pay, payments.manage

resources/
├── views/
│   ├── client/
│   │   └── dashboard/
│   │       └── tabs/
│   │           └── payments.blade.php                     # MODIFY: replace stub with real payment UI
│   ├── admin/
│   │   └── applications/
│   │       ├── index.blade.php                            # MODIFY: add Payments link column (Phase 4 file)
│   │       └── payments.blade.php                         # NEW: admin payment view + mark-as-due
│   └── mail/
│       └── payment-confirmed.blade.php                    # NEW: payment confirmation email template
└── lang/
    ├── en/
    │   └── payments.php                                   # EN payment strings (content)
    └── ar/
        └── payments.php                                   # AR payment strings (content)

lang/
├── en/
│   └── payments.php                                       # Proxy → resource_path('lang/en/payments.php')
└── ar/
    └── payments.php                                       # Proxy → resource_path('lang/ar/payments.php')

routes/
└── web.php                                                # MODIFY: add webhook, client payment routes, admin payment routes

app/Services/Client/OnboardingService.php                  # MODIFY: call seedPaymentsForApplication() after seedTasksForApplication()
```

**Structure Decision**: Laravel monolith consistent with Phases 1–4. `PaymentWebhookController` is at root namespace (not Client/ or Admin/) — webhook is a system-level entry point, not user-facing. `PaymentService` in `app/Services/Payments/` for module isolation. Two separate admin and client controllers keep namespace boundaries clean.

## Key Implementation Notes

### PaymentService::createCheckoutSession()
```php
public function createCheckoutSession(Payment $payment, User $user): string
{
    \Stripe\Stripe::setApiKey(config('services.stripe.key'));

    if ($payment->status === 'failed') {
        Payment::whereKey($payment->id)->update(['status' => 'due']);
        $payment->status = 'due';
    }

    $session = \Stripe\Checkout\Session::create([
        'mode'        => 'payment',
        'line_items'  => [[
            'price_data' => [
                'currency'     => $payment->currency,
                'unit_amount'  => $payment->amount,
                'product_data' => ['name' => $payment->name],
            ],
            'quantity' => 1,
        ]],
        'metadata'    => ['payment_id' => (string) $payment->id],
        'success_url' => route('client.payments.success') . '?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => route('client.payments.cancel'),
    ]);

    Payment::whereKey($payment->id)->update(['stripe_session_id' => $session->id]);

    $this->auditLog->log('payment_initiated', $user, [
        'payment_id'   => $payment->id,
        'stage'        => $payment->stage,
        'reference'    => $payment->application->reference_number,
    ]);

    return $session->url;
}
```

### PaymentService::handleWebhookEvent()
```php
public function handleWebhookEvent(\Stripe\Event $event): void
{
    $object    = $event->data->object;
    $paymentId = $object->metadata?->payment_id ?? null;

    match ($event->type) {
        'checkout.session.completed' => $this->handleSessionCompleted($object, $paymentId),
        'payment_intent.payment_failed' => $this->handlePaymentFailed($object, $paymentId),
        default => $this->auditLog->log('webhook_unhandled', null, ['type' => $event->type]),
    };
}

private function handleSessionCompleted(object $session, ?string $paymentId): void
{
    if (!$paymentId) return;

    $affected = Payment::whereKey($paymentId)
        ->where('status', 'due')
        ->update([
            'status'                   => 'paid',
            'stripe_payment_intent_id' => $session->payment_intent,
            'paid_at'                  => now(),
        ]);

    if ($affected === 0) return; // Already paid — idempotent

    $payment = Payment::with('application.user')->find($paymentId);
    $this->auditLog->log('payment_confirmed', null, [
        'payment_id' => $payment->id,
        'stage'      => $payment->stage,
        'reference'  => $payment->application->reference_number,
    ]);
    Mail::to($payment->application->user->email)->queue(new PaymentConfirmedMail($payment));
}
```

### PaymentPolicy
```php
public function pay(User $user, Payment $payment): bool
{
    return $user->can('payments.pay')
        && $payment->application->user_id === $user->id
        && in_array($payment->status, ['due', 'failed'], true);
}

public function manage(User $user, ?Payment $payment = null): bool
{
    return $user->can('payments.manage');
}
```

### Webhook CSRF Exclusion (bootstrap/app.php)
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'payments/webhook',
    ]);
})
```

### PaymentWebhookController
```php
public function handle(Request $request): JsonResponse
{
    $payload   = $request->getContent();
    $sigHeader = $request->header('Stripe-Signature');

    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload,
            $sigHeader,
            config('services.stripe.webhook_secret')
        );
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        return response()->json(['error' => 'Invalid signature'], 400);
    }

    $this->paymentService->handleWebhookEvent($event);

    return response()->json(['status' => 'ok']);
}
```

### Test Webhook Signature Helper
```php
private function signStripePayload(string $payload): string
{
    $secret    = 'test_webhook_secret';
    $timestamp = time();
    return "t={$timestamp},v1=" . hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
}
```
Set in test setUp: `config(['services.stripe.webhook_secret' => 'test_webhook_secret']);`

## Complexity Tracking

> No constitution violations — table not required.
