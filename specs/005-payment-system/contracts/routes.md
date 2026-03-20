# Route Contracts: Phase 5 — Payment System

**Date**: 2026-03-20

All routes are web routes (Blade, CSRF enabled, session-based auth) except the webhook endpoint which is excluded from CSRF.

---

## Client Payment Routes

### GET `/client/payments/{payment}/checkout`
- **Controller**: `Client\PaymentController@checkout`
- **Purpose**: Create (or refresh) a Stripe Checkout session and redirect the client to Stripe's hosted payment page
- **Middleware**: `['auth', 'verified', 'active']`
- **Authorization**: `$this->authorize('pay', $payment)` → `PaymentPolicy::pay` checks `$user->can('payments.pay')` AND `$payment->application->user_id === $user->id` AND `$payment->status` is `due` or `failed`
- **Route model binding**: `{payment}` → `Payment` by `id`
- **Business logic**:
  1. If `$payment->status === 'failed'`: reset status to `'due'` before creating new session
  2. Call `PaymentService::createCheckoutSession($payment, $user)` → returns Stripe checkout URL, stores new `stripe_session_id` on the payment record
  3. Log `payment_initiated` audit event
  4. Redirect to Stripe checkout URL
- **Named route**: `client.payments.checkout`

---

### GET `/client/payments/success`
- **Controller**: `Client\PaymentController@success`
- **Purpose**: Landing page after Stripe redirects client back on payment completion (success path). Payment status is NOT confirmed here — that is done by webhook. This page shows a "processing" or "success pending" message.
- **Middleware**: `['auth', 'verified', 'active']`
- **Query parameter**: `session_id` (from Stripe `{CHECKOUT_SESSION_ID}` placeholder) — informational only, not used for state change
- **Response**: Redirect to `client.dashboard` with tab `payments` and flash message `__('payments.payment_processing')`
- **Named route**: `client.payments.success`

---

### GET `/client/payments/cancel`
- **Controller**: `Client\PaymentController@cancel`
- **Purpose**: Landing page after client clicks "back" or cancels on the Stripe Checkout page. No state change.
- **Middleware**: `['auth', 'verified', 'active']`
- **Response**: Redirect to `client.dashboard` with tab `payments` and flash message `__('payments.payment_cancelled')`
- **Named route**: `client.payments.cancel`

---

## Webhook Endpoint

### POST `/payments/webhook`
- **Controller**: `PaymentWebhookController@handle`
- **Purpose**: Receive and process Stripe webhook events (the authoritative payment confirmation source)
- **Middleware**: None — excluded from `auth`, `verified`, and CSRF middleware
- **CSRF**: Excluded via `validateCsrfTokens(except: ['payments/webhook'])` in `bootstrap/app.php`
- **No route model binding**
- **Business logic**:
  1. Read raw body: `$payload = $request->getContent()`
  2. Verify signature: `\Stripe\Webhook::constructEvent($payload, $request->header('Stripe-Signature'), config('services.stripe.webhook_secret'))`
  3. On `SignatureVerificationException` → return HTTP 400, no state change
  4. Pass verified event to `PaymentService::handleWebhookEvent($event)`
  5. Return HTTP 200 `{'status': 'ok'}`
- **Handled events**:
  - `checkout.session.completed` → mark payment `paid`, store `stripe_payment_intent_id`, set `paid_at`, queue `PaymentConfirmedMail`, log `payment_confirmed`
  - `payment_intent.payment_failed` → mark payment `failed`, log `payment_failed`
  - All other event types → log warning, return HTTP 200 (stop Stripe retrying)
- **Named route**: `payments.webhook`

---

## Admin Payment Routes

### GET `/admin/applications/{application}/payments`
- **Controller**: `Admin\PaymentController@index`
- **Purpose**: View all three payment stages for an application with option to mark pending stages as due
- **Middleware**: `['auth', 'verified']`
- **Authorization**: `$this->authorize('manage', Payment::class)` → `PaymentPolicy::manage` checks `$user->can('payments.manage')`
- **Route model binding**: `{application}` → `VisaApplication` by `id`
- **Data passed to view**: `$application` (with `user`, `visaType`, `payments` eager-loaded), paginated or all 3 stages ordered by `stage`
- **Named route**: `admin.applications.payments.index`

---

### PATCH `/admin/applications/{application}/payments/{payment}/mark-due`
- **Controller**: `Admin\PaymentController@markDue`
- **Purpose**: Admin marks a pending payment stage as due, making it payable by the client
- **Middleware**: `['auth', 'verified']`
- **Authorization**: `$this->authorize('manage', $payment)` → `PaymentPolicy::manage` checks `$user->can('payments.manage')`
- **Request**: `Admin\MarkPaymentDueRequest` (no body params — action is entirely in the route)
- **Route model binding**: `{application}` → `VisaApplication`, `{payment}` → `Payment`
- **Business logic**: `PaymentService::markStageAsDue($payment)` — idempotent (no-op if already `due` or `paid`); logs `payment_stage_marked_due` if status was `pending`
- **Success response**: `redirect()->route('admin.applications.payments.index', $application)->with('success', __('payments.stage_marked_due'))`
- **Named route**: `admin.applications.payments.mark-due`

---

## Reviewer Routes (no changes)

Reviewers have no payment routes in Phase 5. Payment status visibility for reviewers is deferred to Phase 6/7.

---

## Route Registration Summary

```php
// Webhook — no auth, no CSRF (excluded in bootstrap/app.php)
Route::post('/payments/webhook', [\App\Http\Controllers\PaymentWebhookController::class, 'handle'])
    ->name('payments.webhook');

// Client payment routes (inside existing ['auth', 'verified', 'active'] + client. group)
Route::middleware(['auth', 'verified', 'active'])->prefix('client')->name('client.')->group(function () {
    // existing client routes...
    Route::get('/payments/{payment}/checkout', [\App\Http\Controllers\Client\PaymentController::class, 'checkout'])
        ->name('payments.checkout');
    Route::get('/payments/success', [\App\Http\Controllers\Client\PaymentController::class, 'success'])
        ->name('payments.success');
    Route::get('/payments/cancel', [\App\Http\Controllers\Client\PaymentController::class, 'cancel'])
        ->name('payments.cancel');
});

// Admin payment routes (inside existing ['auth', 'verified'] + admin. group)
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    // existing admin routes...
    Route::get('/applications/{application}/payments', [\App\Http\Controllers\Admin\PaymentController::class, 'index'])
        ->name('applications.payments.index');
    Route::patch('/applications/{application}/payments/{payment}/mark-due', [\App\Http\Controllers\Admin\PaymentController::class, 'markDue'])
        ->name('applications.payments.mark-due');
});
```

---

## Middleware Summary

| Middleware | Where applied | Purpose |
|---|---|---|
| `auth` | All client + admin routes | Requires authentication |
| `verified` | All client + admin routes | Email verification |
| `active` | Client payment routes | Requires active account |
| `PaymentPolicy` (via `authorize`) | All payment actions | Per-action permission and ownership check |
| *(none)* | `POST /payments/webhook` | No auth — Stripe signature is the auth mechanism |
