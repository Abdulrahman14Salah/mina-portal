# Quickstart: Phase 5 — Payment System

**Purpose**: Manual validation scenarios to run after implementation.

---

## Prerequisites

1. Phases 1–4 complete — a client account with an application exists and workflow tasks are seeded.
2. Run: `php artisan db:seed --class=RolePermissionSeeder` (adds `payments.pay`, `payments.manage`)
3. Run: `php artisan db:seed --class=PaymentStageConfigSeeder` (seeds stage configs per visa type)
4. Re-run onboarding or seed payments manually: `App\Models\Payment` records exist for the test application.
5. Stripe test mode keys set in `.env`: `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`
6. Dev server running: `php artisan serve`
7. Queue worker running: `php artisan queue:work` (required for confirmation emails)
8. Stripe CLI installed for webhook forwarding: `stripe listen --forward-to localhost:8000/payments/webhook`

---

## Scenario 1 — Stage 1 is Immediately Due on Application Creation

**Steps**:
1. Create a fresh application via `/apply`
2. Log in as that client → navigate to `/client/dashboard/payments`

**Expected**:
- Stage 1 ("Application Fee") shows "Due" with a Pay Now button
- Stage 2 and Stage 3 show "Pending" with no Pay Now button
- `payments` table has 3 rows for this application (stage 1: `due`, stages 2 & 3: `pending`)

---

## Scenario 2 — Client Pays Stage 1

**Steps**:
1. Log in as the client, navigate to Payments tab
2. Click "Pay Now" for Stage 1
3. On Stripe's hosted page, use test card `4242 4242 4242 4242`, any future date, any CVC
4. Complete payment — observe redirect back to dashboard

**Expected**:
- Redirected to Payments tab with a processing/success message
- `payments` table row for Stage 1: `status = 'paid'`, `paid_at` set, `stripe_payment_intent_id` set
- `audit_logs` has a row with `event = 'payment_confirmed'`
- Queue worker sends `PaymentConfirmedMail` (check `storage/logs/laravel.log` or mail trap)
- Pay Now button gone for Stage 1; shows payment date instead

---

## Scenario 3 — Payment Validation (Type Rejection via Webhook Signature)

**Steps**:
1. Send a POST to `/payments/webhook` with a valid JSON body but no `Stripe-Signature` header

**Expected**:
- HTTP 400 response
- No state change in `payments` table
- `audit_logs` has no spurious entry

---

## Scenario 4 — Duplicate Webhook Idempotency

**Steps**:
1. After Scenario 2, use Stripe CLI to resend the same `checkout.session.completed` event:
   `stripe events resend evt_xxxxxx`

**Expected**:
- HTTP 200 response
- `payments` table Stage 1 remains `paid` (no duplicate update)
- No second `payment_confirmed` audit log entry for the same payment

---

## Scenario 5 — Client Abandons Stripe and Retries

**Steps**:
1. Log in as client, click Pay Now for Stage 2 (after admin marks it due — see Scenario 7)
2. On Stripe's page, click "Back" or close the tab
3. Return to dashboard Payments tab
4. Click Pay Now again

**Expected**:
- A new Stripe Checkout session is created (new URL)
- The old `stripe_session_id` on the Stage 2 payment record is overwritten with the new session ID
- Stage 2 status remains `due` (not failed)

---

## Scenario 6 — Card Declined

**Steps**:
1. Click Pay Now for a due stage
2. On Stripe's page, use the decline test card `4000 0000 0000 0002`

**Expected**:
- Stripe shows a card declined error on their page (client can retry within the same session)
- If ultimately abandoned: stage remains `due` on return to portal
- `audit_logs` has a row with `event = 'payment_failed'` (if `payment_intent.payment_failed` webhook fires)

---

## Scenario 7 — Admin Marks Stage 2 as Due

**Steps**:
1. Log in as admin
2. Navigate to `/admin/applications`
3. Find the test application, click into its Payments page (`/admin/applications/{id}/payments`)
4. Click "Mark as Due" for Stage 2

**Expected**:
- Stage 2 status changes to `due` in the `payments` table
- Client logs in → Payments tab now shows Stage 2 with a Pay Now button
- `audit_logs` has a row with `event = 'payment_stage_marked_due'`

---

## Scenario 8 — Admin Idempotent Mark-as-Due

**Steps**:
1. After Scenario 7 (Stage 2 is already `due`), click "Mark as Due" for Stage 2 again in the admin panel

**Expected**:
- No state change (idempotent)
- No duplicate audit log entry
- Redirect back to payments page (no error)

---

## Scenario 9 — Cross-Client Access Denied

**Steps**:
1. Note the checkout URL for Client A's Stage 1 payment (e.g., `/client/payments/5/checkout`)
2. Log out and log in as Client B (different application)
3. Visit that URL directly

**Expected**:
- HTTP 403 Forbidden
- Client B cannot initiate a payment for Client A's payment record

---

## Scenario 10 — Unauthenticated Access Denied

**Steps**:
1. Log out
2. Visit `/client/payments/1/checkout` directly

**Expected**:
- Redirect to `/login`

---

## Scenario 11 — Reviewer Cannot Mark Payment as Due

**Steps**:
1. Log in as reviewer
2. POST to `/admin/applications/{id}/payments/{payment}/mark-due`

**Expected**:
- HTTP 403 Forbidden

---

## Database Spot-Checks (via `php artisan tinker`)

```php
// Confirm 3 payment records created per application
App\Models\Payment::where('application_id', 1)->count(); // → 3

// Confirm Stage 1 is immediately due
App\Models\Payment::where('application_id', 1)->where('stage', 1)->first()->status; // → 'due'

// After payment — confirm paid state
App\Models\Payment::where('application_id', 1)->where('stage', 1)->first()->toArray();
// → Check: status='paid', paid_at set, stripe_payment_intent_id set

// Confirm audit log
DB::table('audit_logs')->where('event', 'payment_confirmed')->count(); // → 1 after 1 payment

// Confirm payment stage configs seeded
App\Models\PaymentStageConfig::where('visa_type_id', 1)->get(['stage', 'name', 'amount', 'currency']);
// → 3 rows: stage 1, 2, 3 with name + amount
```
