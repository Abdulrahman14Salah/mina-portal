# Data Model: Phase 5 — Payment System

**Date**: 2026-03-20
**Status**: Complete

---

## Entity: `payment_stage_configs`

Stores the three payment stage definitions per visa type. Amounts are in the smallest currency unit (e.g., fils for AED, cents for USD). This table is the source of truth for stage configuration; values are copied into `payments` records at creation time to preserve financial history.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `bigIncrements` | PK | |
| `visa_type_id` | `foreignId` | NOT NULL, FK → `visa_types.id`, CASCADE DELETE | |
| `stage` | `unsignedTinyInteger` | NOT NULL | 1, 2, or 3 |
| `name` | `string(100)` | NOT NULL | e.g., "Application Fee", "Processing Fee", "Visa Fee" |
| `amount` | `unsignedInteger` | NOT NULL | In smallest currency unit (fils/cents) |
| `currency` | `string(3)` | NOT NULL | e.g., "AED", "USD" |
| `created_at` | `timestamp` | | |
| `updated_at` | `timestamp` | | |

**Unique constraint**: `(visa_type_id, stage)` — one config per stage per visa type.

**Relationships**:
- Belongs to `VisaType` (as `$config->visaType`)

**Seeder**: `PaymentStageConfigSeeder` — seeds 3 rows per visa type with default amounts.

---

## Entity: `payments`

One row per payment stage per application. Status lifecycle: `pending` → `due` → `paid` (or `failed`). Values for `name`, `amount`, and `currency` are copied from `payment_stage_configs` at record creation — the payment record preserves what was charged, independent of future config changes.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `bigIncrements` | PK | |
| `application_id` | `foreignId` | NOT NULL, FK → `visa_applications.id`, CASCADE DELETE | Owning application |
| `stage` | `unsignedTinyInteger` | NOT NULL | 1, 2, or 3 |
| `name` | `string(100)` | NOT NULL | Copied from config at creation; e.g., "Application Fee" |
| `amount` | `unsignedInteger` | NOT NULL | In smallest currency unit — copied from config at creation |
| `currency` | `string(3)` | NOT NULL | Copied from config at creation |
| `status` | `enum` | NOT NULL, DEFAULT `'pending'` | Values: `pending`, `due`, `paid`, `failed` |
| `stripe_session_id` | `string(255)` | NULLABLE | Set when a Checkout session is created; overwritten on retry |
| `stripe_payment_intent_id` | `string(255)` | NULLABLE | Set when `checkout.session.completed` webhook is received |
| `paid_at` | `timestamp` | NULLABLE | Set when webhook confirms payment |
| `created_at` | `timestamp` | | |
| `updated_at` | `timestamp` | | |

**Unique constraint**: `(application_id, stage)` — one payment record per stage per application.

**Relationships**:
- Belongs to `VisaApplication` (as `$payment->application`)

**No unique constraint** on `stripe_session_id` — it is overwritten on retry and may be null. The canonical webhook matching key is `metadata['payment_id']` (the portal's `Payment.id`).

---

## Status Lifecycle

```
         [application created]
                  │
                  ▼
          ┌───────────────┐
          │  Stage 1: due  │◄── auto on application creation
          └───────────────┘
                  │ client pays → Stripe Checkout session
                  │
           [webhook: checkout.session.completed]
                  │
                  ▼
          ┌───────────────┐
          │ Stage 1: paid  │ (locked)
          └───────────────┘

          ┌──────────────────┐
          │ Stages 2&3:      │
          │   pending        │◄── default on application creation
          └──────────────────┘
                  │ admin marks as due
                  ▼
          ┌──────────────────┐
          │ Stages 2&3: due  │
          └──────────────────┘
                  │ client pays
                  │
      ┌───────────┤ [webhook: payment_intent.payment_failed]
      │           │
      ▼           ▼
 ┌─────────┐  ┌────────────────────┐
 │ failed  │  │ [checkout session  │
 └─────────┘  │    completed]      │
      │        └────────────────────┘
      │ client retries (new session)      │
      └──► back to due ──►  paid (locked)
```

**Transition rules**:
- `pending` → `due`: admin action (Stages 2 & 3); auto on app creation (Stage 1)
- `due` → `paid`: `checkout.session.completed` webhook (atomic conditional update)
- `due` → `failed`: `payment_intent.payment_failed` webhook (card declined)
- `failed` → `due`: client initiates new Checkout session (status reset before redirect)
- `paid` → *: immutable — once paid, a stage can never change status

---

## Entity: `visa_applications` (extended from Phase 2)

No schema changes. Gains one new relationship:

```php
public function payments(): HasMany
{
    return $this->hasMany(Payment::class, 'application_id');
}
```

---

## Entity: `visa_types` (extended from Phase 2)

No schema changes. Gains one new relationship:

```php
public function paymentStageConfigs(): HasMany
{
    return $this->hasMany(PaymentStageConfig::class, 'visa_type_id')->orderBy('stage');
}
```

---

## New Permissions

Two new permissions added to `RolePermissionSeeder`:

| Permission | Assigned to | Description |
|---|---|---|
| `payments.pay` | `client` | Can initiate a Checkout session for a due/failed payment stage |
| `payments.manage` | `admin` | Can view payment details and mark pending stages as due |

**Note**: `reviewer` receives neither permission. Reviewer visibility of payment status (if any) comes through the application detail page at the discretion of future phases — not through a dedicated permission in Phase 5.

---

## Validation Rules

### `MarkPaymentDueRequest` (admin mark-as-due)

No request body parameters — the action is expressed entirely through the route (`PATCH /admin/applications/{application}/payments/{payment}/mark-due`). The Form Request class exists to satisfy the constitution (no inline validation) but has empty `rules()`. Authorization is delegated to `PaymentPolicy::manage()`.

---

## Migration Order

1. `xxxx_create_payment_stage_configs_table.php` (depends on `visa_types`)
2. `xxxx_create_payments_table.php` (depends on `visa_applications`)

Both run after Phase 2 migrations.

---

## `PaymentService` Method Signatures

```php
// Called from OnboardingService::handle() after seedTasksForApplication()
public function seedPaymentsForApplication(VisaApplication $application): void

// Called from Client\PaymentController — returns Stripe Checkout URL
public function createCheckoutSession(Payment $payment, User $user): string

// Called from Admin\PaymentController — idempotent
public function markStageAsDue(Payment $payment): void

// Called from PaymentWebhookController — dispatched synchronously
public function handleWebhookEvent(\Stripe\Event $event): void
```
