# Tasks: Phase 5 — Payment System

**Input**: Design documents from `/specs/005-payment-system/`
**Prerequisites**: plan.md ✅, spec.md ✅, research.md ✅, data-model.md ✅, contracts/routes.md ✅, quickstart.md ✅

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on sibling [P] tasks)
- **[Story]**: Which user story this task belongs to (US1–US4)
- Exact file paths are included in every description

---

## Phase 1: Setup (Stripe SDK & Credentials)

**Purpose**: Install the Stripe SDK and wire up credentials. Must complete before any payment code is written.

- [ ] T001 Install `stripe/stripe-php` via `composer require stripe/stripe-php` in the project root
- [ ] T002 [P] Add Stripe env vars to `.env.example`: `STRIPE_KEY=`, `STRIPE_SECRET=`, `STRIPE_WEBHOOK_SECRET=`, `PAYMENT_CURRENCY=usd` (copy to your own `.env` and fill in test-mode values)
- [ ] T003 [P] Add a `'stripe'` block to `config/services.php`: sub-keys `key` (`env('STRIPE_KEY')`), `secret` (`env('STRIPE_SECRET')`), `webhook_secret` (`env('STRIPE_WEBHOOK_SECRET')`), `currency` (`env('PAYMENT_CURRENCY', 'usd')`)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Database schema, models, policy, seeders, lang files, CSRF exclusion, service scaffold, and routes. Every user story depends on this phase being complete.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T004 Create migration `database/migrations/xxxx_create_payment_stage_configs_table.php`: columns — `id` (bigIncrements), `visa_type_id` (foreignId → `visa_types`, onDelete CASCADE), `stage` (unsignedTinyInteger, NOT NULL), `name` (string 100, NOT NULL), `amount` (unsignedInteger, NOT NULL — smallest currency unit: fils/cents), `currency` (string 3, NOT NULL), `timestamps()`. Add unique index on `['visa_type_id', 'stage']`.
- [ ] T005 Create migration `database/migrations/xxxx_create_payments_table.php` (must be created AFTER T004 so it runs later): columns — `id` (bigIncrements), `application_id` (foreignId → `visa_applications`, onDelete CASCADE), `stage` (unsignedTinyInteger, NOT NULL), `name` (string 100, NOT NULL), `amount` (unsignedInteger, NOT NULL), `currency` (string 3, NOT NULL), `status` (enum `['pending','due','paid','failed']`, default `'pending'`, NOT NULL), `stripe_session_id` (string 255, nullable), `stripe_payment_intent_id` (string 255, nullable), `paid_at` (timestamp, nullable), `timestamps()`. Add unique index on `['application_id', 'stage']`.
- [ ] T006 [P] Create `app/Models/PaymentStageConfig.php`: extends `Model`; `$fillable = ['visa_type_id','stage','name','amount','currency']`; `visaType()` BelongsTo → `VisaType::class`.
- [ ] T007 [P] Create `app/Models/Payment.php`: extends `Model`; `$fillable = ['application_id','stage','name','amount','currency','status','stripe_session_id','stripe_payment_intent_id','paid_at']`; `$casts = ['paid_at' => 'datetime']`; `application()` BelongsTo → `VisaApplication::class` with FK `'application_id'`.
- [ ] T008 [P] Add `payments()` HasMany to `app/Models/VisaApplication.php`: `return $this->hasMany(Payment::class, 'application_id');` (add `use App\Models\Payment;`).
- [ ] T009 [P] Add `paymentStageConfigs()` HasMany to `app/Models/VisaType.php`: `return $this->hasMany(PaymentStageConfig::class, 'visa_type_id')->orderBy('stage');` (add `use App\Models\PaymentStageConfig;`).
- [ ] T010 [P] Create `app/Policies/PaymentPolicy.php`: method `pay(User $user, Payment $payment): bool` returns `$user->can('payments.pay') && $payment->application->user_id === $user->id && in_array($payment->status, ['due','failed'], true)`; method `manage(User $user, ?Payment $payment = null): bool` returns `$user->can('payments.manage')`.
- [ ] T011 Register `PaymentPolicy` in `app/Providers/AppServiceProvider.php`: inside `boot()` add `Gate::policy(Payment::class, PaymentPolicy::class);` (add `use App\Models\Payment; use App\Policies\PaymentPolicy; use Illuminate\Support\Facades\Gate;`).
- [ ] T012 [P] Add two new permissions to `database/seeders/RolePermissionSeeder.php`: create permission `'payments.pay'` and assign it to the `client` role; create permission `'payments.manage'` and assign it to the `admin` role. Follow the exact pattern already used in this seeder for other permissions.
- [ ] T013 [P] Create `database/seeders/PaymentStageConfigSeeder.php`: iterate over all `VisaType::all()` records; for each type call `PaymentStageConfig::firstOrCreate(['visa_type_id'=>$type->id,'stage'=>1], ['name'=>'Application Fee','amount'=>50000,'currency'=>'usd'])`, same for stage 2 (`'Processing Fee'`, 100000) and stage 3 (`'Visa Fee'`, 150000). Register this seeder in `database/seeders/DatabaseSeeder.php` by adding `$this->call(PaymentStageConfigSeeder::class);`.
- [ ] T014 [P] Create `resources/lang/en/payments.php`: return array with keys — `payment_tab_title`, `stage_name`, `amount`, `currency`, `status_pending`, `status_due`, `status_paid`, `status_failed`, `pay_now`, `paid_on`, `payment_processing`, `payment_cancelled`, `stage_marked_due`, `payment_history`, `stripe_reference`, `mark_as_due`, `all_stages`, `payment_confirmed_subject`, `admin_payments_title`. Fill with readable English values (e.g., `'pay_now' => 'Pay Now'`, `'status_paid' => 'Paid'`).
- [ ] T015 [P] Create `resources/lang/ar/payments.php`: return Arabic translations for every key defined in T014.
- [ ] T016 [P] Create `lang/en/payments.php` proxy file: single line `<?php return require resource_path('lang/en/payments.php');`
- [ ] T017 [P] Create `lang/ar/payments.php` proxy file: single line `<?php return require resource_path('lang/ar/payments.php');`
- [ ] T018 Add CSRF exclusion for the webhook endpoint in `bootstrap/app.php`: inside the existing `->withMiddleware(function (Middleware $middleware) {` callback, add `$middleware->validateCsrfTokens(except: ['payments/webhook']);`
- [ ] T019 [P] Create `app/Services/Payments/PaymentService.php` scaffold: namespace `App\Services\Payments`; constructor receives `AuditLogService $auditLog` (inject via type-hint — follow the same injection pattern used in other services in this project); call `\Stripe\Stripe::setApiKey(config('services.stripe.secret'))` in constructor; declare four empty public methods with correct signatures: `seedPaymentsForApplication(VisaApplication $application): void`, `createCheckoutSession(Payment $payment, User $user): string`, `markStageAsDue(Payment $payment): void`, `handleWebhookEvent(\Stripe\Event $event): void`. Add all necessary `use` imports. **Do not implement the method bodies yet** — those come in US1–US3 phases.
- [ ] T020 Add all payment routes to `routes/web.php`: (a) **webhook** — register OUTSIDE any auth/middleware group, before other groups: `Route::post('/payments/webhook', [\App\Http\Controllers\PaymentWebhookController::class, 'handle'])->name('payments.webhook');` (b) **client routes** — inside the existing `['auth','verified','active']` + `prefix('client')` + `name('client.')` group: `Route::get('/payments/{payment}/checkout', [\App\Http\Controllers\Client\PaymentController::class, 'checkout'])->name('payments.checkout');`, `Route::get('/payments/success', [\App\Http\Controllers\Client\PaymentController::class, 'success'])->name('payments.success');`, `Route::get('/payments/cancel', [\App\Http\Controllers\Client\PaymentController::class, 'cancel'])->name('payments.cancel');` — **NOTE**: `/payments/success` and `/payments/cancel` must be defined BEFORE `/payments/{payment}/checkout` to avoid `success`/`cancel` being captured as `{payment}` — OR use route model binding only on the checkout route (which is the case here since they are different paths). (c) **admin routes** — inside the existing `['auth','verified']` + `prefix('admin')` + `name('admin.')` group: `Route::get('/applications/{application}/payments', [\App\Http\Controllers\Admin\PaymentController::class, 'index'])->name('applications.payments.index');`, `Route::patch('/applications/{application}/payments/{payment}/mark-due', [\App\Http\Controllers\Admin\PaymentController::class, 'markDue'])->name('applications.payments.mark-due');`

**Checkpoint**: Run `php artisan migrate` → success. Run `php artisan db:seed --class=RolePermissionSeeder` → success. Run `php artisan db:seed --class=PaymentStageConfigSeeder` → success. Run `php artisan route:list | grep payment` → shows 6 payment routes.

---

## Phase 3: User Story 1 — Client Pays a Due Stage (Priority: P1) 🎯 MVP

**Goal**: A client can see due payment stages on the Payments tab and be redirected to Stripe Checkout to complete payment.

**Independent Test**: Use tinker to manually set a Payment record to `status=due`. Log in as that client → Payments tab shows "Pay Now" button for that stage. Click → redirected to a Stripe URL beginning with `https://checkout.stripe.com`. Unauthenticated request to checkout route redirects to login. Client B accessing Client A's payment returns 403. Clicking "Pay Now" on a `pending` stage returns 403.

- [ ] T021 [US1] Implement `PaymentService::createCheckoutSession(Payment $payment, User $user): string` in `app/Services/Payments/PaymentService.php`: (1) if `$payment->status === 'failed'` run `Payment::whereKey($payment->id)->update(['status' => 'due']); $payment->status = 'due';` to reset before retrying; (2) call `\Stripe\Checkout\Session::create(['mode' => 'payment', 'line_items' => [['price_data' => ['currency' => $payment->currency, 'unit_amount' => $payment->amount, 'product_data' => ['name' => $payment->name]], 'quantity' => 1]], 'metadata' => ['payment_id' => (string) $payment->id], 'success_url' => route('client.payments.success') . '?session_id={CHECKOUT_SESSION_ID}', 'cancel_url' => route('client.payments.cancel')])` and store result in `$session`; (3) `Payment::whereKey($payment->id)->update(['stripe_session_id' => $session->id])`; (4) `$this->auditLog->log('payment_initiated', $user, ['payment_id' => $payment->id, 'stage' => $payment->stage, 'reference' => $payment->application->reference_number])`; (5) return `$session->url`.
- [ ] T022 [US1] Create `app/Http/Controllers/Client/PaymentController.php`: constructor injects `PaymentService $paymentService`; method `checkout(Request $request, Payment $payment)` — call `$this->authorize('pay', $payment)`, then `$url = $this->paymentService->createCheckoutSession($payment, $request->user())`, return `redirect($url)`; method `success(Request $request)` — return `redirect()->route('client.dashboard', ['tab' => 'payments'])->with('success', __('payments.payment_processing'))`; method `cancel(Request $request)` — return `redirect()->route('client.dashboard', ['tab' => 'payments'])->with('info', __('payments.payment_cancelled'))`. Add `use App\Services\Payments\PaymentService; use App\Models\Payment;`.
- [ ] T023 [US1] Modify the existing client `DashboardController` (find it in `app/Http/Controllers/Client/`) to eager-load payments on the application query: add `->with('payments')` (or equivalent) so that `$payments` ordered by `stage` is available to the dashboard view. Pass `$payments` to the Blade view.
- [ ] T024 [US1] Replace the stub `resources/views/client/dashboard/tabs/payments.blade.php` with real UI: loop `@foreach($payments as $payment)` — display stage name (`$payment->name`), amount formatted as currency, status badge using `__('payments.status_' . $payment->status)`; for `status === 'due'` or `status === 'failed'` show a "Pay Now" `<a>` button linking to `route('client.payments.checkout', $payment)`; for `status === 'paid'` show `__('payments.paid_on') . ' ' . $payment->paid_at->format('d M Y')`; for `status === 'pending'` show only the pending badge with no action. Use `__('payments.*')` for all visible strings. No hardcoded English.
- [ ] T025 [US1] Create `tests/Feature/Client/PaymentCheckoutTest.php`: use `$this->mock(PaymentService::class)` to stub `createCheckoutSession` returning `'https://checkout.stripe.com/test_session'`; write tests: `test_client_is_redirected_to_stripe_on_checkout()` — acting as owner client, GET checkout route, assert redirect to Stripe URL; `test_unauthenticated_is_redirected_to_login()` — no auth, GET checkout route, assert redirect to `/login`; `test_cross_client_gets_403()` — Client B acts on Client A's payment, assert 403; `test_cannot_pay_pending_stage()` — payment with `status=pending`, assert 403; `test_cannot_pay_paid_stage()` — payment with `status=paid`, assert 403; `test_success_redirects_to_dashboard_with_flash()` — GET success route as client, assert redirect to dashboard; `test_cancel_redirects_to_dashboard_with_flash()` — GET cancel route as client, assert redirect to dashboard.

**Checkpoint**: Run `php artisan test --filter=PaymentCheckoutTest` — all pass.

---

## Phase 4: User Story 2 — Webhook Confirms Payment Automatically (Priority: P1)

**Goal**: Stripe webhook events are verified using HMAC signatures, processed idempotently, and trigger payment status updates and queued confirmation emails.

**Independent Test**: POST to `/payments/webhook` with a correctly signed `checkout.session.completed` payload — DB Payment record transitions to `status=paid`, `paid_at` set, `stripe_payment_intent_id` set, `payment_confirmed` entry in `audit_logs`, `PaymentConfirmedMail` queued. POST with invalid/missing signature returns HTTP 400. Sending the same event twice returns 200 with no second audit log entry.

- [ ] T026 [US2] Implement `PaymentService::handleWebhookEvent(\Stripe\Event $event): void` in `app/Services/Payments/PaymentService.php` plus two private helpers: (a) `handleSessionCompleted(object $session, ?string $paymentId): void` — if `!$paymentId` return; run `$affected = Payment::whereKey($paymentId)->where('status', 'due')->update(['status' => 'paid', 'stripe_payment_intent_id' => $session->payment_intent, 'paid_at' => now()])` — if `$affected === 0` return (idempotent); load `$payment = Payment::with('application.user')->find($paymentId)`; call `$this->auditLog->log('payment_confirmed', null, ['payment_id' => $payment->id, 'stage' => $payment->stage, 'reference' => $payment->application->reference_number])`; dispatch `Mail::to($payment->application->user->email)->queue(new PaymentConfirmedMail($payment))`; (b) `handlePaymentFailed(object $intent, ?string $paymentId): void` — if `!$paymentId` return; `Payment::whereKey($paymentId)->where('status', 'due')->update(['status' => 'failed'])`; call `$this->auditLog->log('payment_failed', null, ['payment_id' => $paymentId])`; (c) `handleWebhookEvent` body: `$object = $event->data->object; $paymentId = $object->metadata?->payment_id ?? null; match($event->type) { 'checkout.session.completed' => $this->handleSessionCompleted($object, $paymentId), 'payment_intent.payment_failed' => $this->handlePaymentFailed($object, $paymentId), default => $this->auditLog->log('webhook_unhandled', null, ['type' => $event->type]) };`
- [ ] T027 [US2] Create `app/Http/Controllers/PaymentWebhookController.php` (root `App\Http\Controllers` namespace — NOT in `Client/` or `Admin/`): constructor injects `PaymentService $paymentService`; method `handle(Request $request): JsonResponse` — (1) `$payload = $request->getContent(); $sigHeader = $request->header('Stripe-Signature');` (2) wrap in try/catch: `$event = \Stripe\Webhook::constructEvent($payload, $sigHeader, config('services.stripe.webhook_secret'))` — on `\Stripe\Exception\SignatureVerificationException` return `response()->json(['error' => 'Invalid signature'], 400)` (3) call `$this->paymentService->handleWebhookEvent($event)` (4) return `response()->json(['status' => 'ok'])`. Add `use Illuminate\Http\JsonResponse;`.
- [ ] T028 [US2] Create `app/Mail/PaymentConfirmedMail.php`: implements `ShouldQueue`; uses traits `Queueable`, `SerializesModels`; constructor accepts `public Payment $payment`; `envelope()` returns `new Envelope(subject: __('payments.payment_confirmed_subject'))`; `content()` returns `new Content(view: 'mail.payment-confirmed')`.
- [ ] T029 [US2] Create `resources/views/mail/payment-confirmed.blade.php`: simple HTML email — greeting with `{{ $payment->application->user->name }}`, body confirming payment of `{{ $payment->name }}` for amount `{{ number_format($payment->amount / 100, 2) }} {{ strtoupper($payment->currency) }}` on `{{ $payment->paid_at->format('d M Y') }}`. Keep it simple and readable.
- [ ] T030 [US2] Create `tests/Feature/PaymentWebhookTest.php`: add private helper `signWebhookPayload(string $payload, string $secret): string` that returns `"t={$timestamp},v1=" . hash_hmac('sha256', "{$timestamp}.{$payload}", $secret)` (where `$timestamp = time()`); in `setUp()` call `config(['services.stripe.webhook_secret' => 'test_webhook_secret'])`; write tests: `test_valid_checkout_completed_webhook_marks_payment_paid()` — build `checkout.session.completed` payload JSON with `metadata.payment_id`, sign with helper, POST to `/payments/webhook`, assert DB Payment `status=paid` and `paid_at` not null; `test_invalid_signature_returns_400()` — POST with wrong `Stripe-Signature` header, assert 400 response; `test_duplicate_webhook_is_idempotent()` — POST same valid event twice, assert exactly one `payment_confirmed` entry in `audit_logs` for that payment; `test_payment_failed_webhook_sets_failed_status()` — build and sign `payment_intent.payment_failed` payload, POST, assert DB Payment `status=failed`; `test_missing_payment_id_in_metadata_is_a_noop()` — POST `checkout.session.completed` with no `payment_id` in metadata, assert 200 and no DB change.

**Checkpoint**: Run `php artisan test --filter=PaymentWebhookTest` — all pass.

---

## Phase 5: User Story 3 — Stage Becomes Due (Priority: P2)

**Goal**: Stage 1 is automatically set to `due` when an application is created (via `OnboardingService`). Admins can call `markStageAsDue()` to transition Stages 2 or 3 from `pending` to `due`. Calling it on a stage that is already `due` or `paid` is a safe no-op.

**Independent Test**: Create a new application through the normal onboarding flow → verify exactly 3 Payment records in DB: Stage 1 `status=due`, Stage 2 `status=pending`, Stage 3 `status=pending`. In tinker call `PaymentService::markStageAsDue($stage2Payment)` → Stage 2 becomes `due`. Call again → no change, no extra audit log.

- [ ] T031 [US3] Implement `PaymentService::seedPaymentsForApplication(VisaApplication $application): void` in `app/Services/Payments/PaymentService.php`: (1) `$configs = $application->visaType->paymentStageConfigs;` — requires `visaType` relationship on `VisaApplication` to be already loaded or eager-loaded; (2) iterate over `$configs` and for each call `Payment::create(['application_id' => $application->id, 'stage' => $config->stage, 'name' => $config->name, 'amount' => $config->amount, 'currency' => $config->currency, 'status' => ($config->stage === 1 ? 'due' : 'pending')])`.
- [ ] T032 [US3] Modify `app/Services/Client/OnboardingService.php` to call `seedPaymentsForApplication` after tasks are seeded: (1) inject `PaymentService $paymentService` in the constructor; (2) find the line that calls `seedTasksForApplication` (or equivalent — look for the existing workflow/task seeding call) and immediately after it add `$this->paymentService->seedPaymentsForApplication($application);`. Make sure `$application` has its `visaType` relationship loaded at that point (add `$application->loadMissing('visaType.paymentStageConfigs')` if needed). Add `use App\Services\Payments\PaymentService;`.
- [ ] T033 [US3] Implement `PaymentService::markStageAsDue(Payment $payment): void` in `app/Services/Payments/PaymentService.php`: (1) if `$payment->status !== 'pending'` return immediately (idempotent — already due, paid, or failed — no action, no audit log); (2) `Payment::whereKey($payment->id)->update(['status' => 'due'])`; (3) `$this->auditLog->log('payment_stage_marked_due', null, ['payment_id' => $payment->id, 'stage' => $payment->stage, 'reference' => $payment->application->reference_number])`.

**Checkpoint**: Run `php artisan test` — all tests (including earlier phases) must still pass. Create a test application end-to-end → verify 3 Payment records exist in DB.

---

## Phase 6: User Story 4 — Admin Manages Payment Stages (Priority: P3)

**Goal**: Admins can view all three payment stages for any application and mark pending stages as due. Reviewers and clients cannot access these endpoints.

**Independent Test**: Log in as admin → GET `/admin/applications/{id}/payments` → see 3 stages with correct statuses → PATCH mark-due for Stage 2 → Stage 2 status becomes `due` in DB. PATCH again → 200 redirect, no state change (idempotent). Log in as reviewer → PATCH mark-due → 403. Log in as client → GET admin payments → 403.

- [ ] T034 [US4] Create `app/Http/Requests/Admin/MarkPaymentDueRequest.php`: extends `FormRequest`; `authorize()` returns `true` (policy authorization done in controller via `$this->authorize()`); `rules()` returns `[]` (no request body parameters — action is expressed entirely through the route and policy).
- [ ] T035 [US4] Create `app/Http/Controllers/Admin/PaymentController.php`: namespace `App\Http\Controllers\Admin`; constructor injects `PaymentService $paymentService`; method `index(Request $request, VisaApplication $application)` — `$this->authorize('manage', Payment::class)`, load `$payments = $application->payments()->orderBy('stage')->get()`, return `view('admin.applications.payments', compact('application', 'payments'))`; method `markDue(MarkPaymentDueRequest $request, VisaApplication $application, Payment $payment)` — `$this->authorize('manage', $payment)`, call `$this->paymentService->markStageAsDue($payment)`, return `redirect()->route('admin.applications.payments.index', $application)->with('success', __('payments.stage_marked_due'))`. Add all necessary `use` imports.
- [ ] T036 [US4] Create `resources/views/admin/applications/payments.blade.php`: extend the existing admin layout; show heading with application `reference_number` and applicant name; render a table with columns: Stage, Name, Amount, Currency, Status, Paid At, Stripe Reference; for each `$payment` in `$payments` (already ordered by stage), display a row; for `$payment->status === 'pending'` render a "Mark as Due" form button — `<form method="POST" action="{{ route('admin.applications.payments.mark-due', [$application, $payment]) }}">@csrf @method('PATCH')<button>{{ __('payments.mark_as_due') }}</button></form>`; for `$payment->status === 'paid'` show `$payment->paid_at->format('d M Y')` and `$payment->stripe_payment_intent_id`; for all other statuses show only the status badge. Use `__('payments.*')` for all visible strings.
- [ ] T037 [US4] Add a "Payments" action link to `resources/views/admin/applications/index.blade.php`: in the actions column for each application row, add `<a href="{{ route('admin.applications.payments.index', $application) }}">{{ __('payments.payment_history') }}</a>`.
- [ ] T038 [US4] Create `tests/Feature/Admin/AdminPaymentTest.php`: write tests: `test_admin_can_view_payments_page()` — GET admin payments index as admin, assert 200 and see stage names; `test_admin_can_mark_stage_due()` — PATCH mark-due as admin for a `pending` stage, assert DB payment `status=due` and `payment_stage_marked_due` in audit_logs; `test_mark_due_is_idempotent_on_already_due_stage()` — PATCH mark-due for an already-`due` stage, assert redirect 302 success with no second audit log entry; `test_reviewer_cannot_mark_stage_due()` — PATCH as reviewer, assert 403; `test_client_cannot_access_admin_payments_index()` — GET admin payments index as client, assert 403.

**Checkpoint**: Run `php artisan test --filter=AdminPaymentTest` — all pass. Run `php artisan test` — full suite must pass.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Final wiring, seed verification, and manual validation.

- [ ] T039 Verify `database/seeders/DatabaseSeeder.php` calls both `RolePermissionSeeder` and `PaymentStageConfigSeeder`. Run `php artisan migrate:fresh --seed` — must complete without errors. Confirm `payment_stage_configs` table has 3 rows per visa type. Confirm `payments.pay` and `payments.manage` permissions exist.
- [ ] T040 Run manual quickstart.md validation: execute Scenarios 1–11 from `specs/005-payment-system/quickstart.md` using Stripe CLI (`stripe listen --forward-to localhost:8000/payments/webhook`) and test card `4242 4242 4242 4242`. Confirm all expected outcomes match.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately
- **Phase 2 (Foundational)**: Depends on T001 (composer install) — **BLOCKS all user stories**
- **Phase 3 (US1)**: Depends on Phase 2 completion
- **Phase 4 (US2)**: Depends on Phase 2 completion; can run in parallel with Phase 3 (US1) since they touch different files
- **Phase 5 (US3)**: Depends on Phase 3 + Phase 4 being complete (needs the Payments tab and webhook handler to be in place for end-to-end validation)
- **Phase 6 (US4)**: Depends on Phase 5 completion (calls `markStageAsDue()` implemented in T033)
- **Phase 7 (Polish)**: Depends on all phases complete

### User Story Dependencies

- **US1 (P1)**: After Phase 2 — no inter-story dependencies
- **US2 (P1)**: After Phase 2 — no inter-story dependencies; parallelizable with US1
- **US3 (P2)**: After US1 + US2 complete
- **US4 (P3)**: After US3 complete

### Parallel Opportunities Within Phase 2

```
Group A (model files — all independent):
  T006  app/Models/PaymentStageConfig.php
  T007  app/Models/Payment.php
  T008  app/Models/VisaApplication.php (add relation)
  T009  app/Models/VisaType.php (add relation)

Group B (lang files — all independent):
  T014  resources/lang/en/payments.php
  T015  resources/lang/ar/payments.php
  T016  lang/en/payments.php
  T017  lang/ar/payments.php

Group C (independent infrastructure):
  T010  app/Policies/PaymentPolicy.php
  T012  database/seeders/RolePermissionSeeder.php
  T013  database/seeders/PaymentStageConfigSeeder.php
  T019  app/Services/Payments/PaymentService.php (scaffold)
```

---

## Implementation Strategy

### MVP First (US1 + US2 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational
3. Complete Phase 3: US1 (client checkout)
4. Complete Phase 4: US2 (webhook confirmation)
5. **STOP and VALIDATE**: Use tinker to manually seed a `due` payment and complete an end-to-end Stripe test payment
6. Proceed to US3 + US4 for full automation and admin UI

### Incremental Delivery

1. Phase 1 + Phase 2 → DB schema + models ready
2. Phase 3 (US1) → Client can see stages and pay → test independently
3. Phase 4 (US2) → Webhooks confirm payments → test independently
4. Phase 5 (US3) → Application creation auto-seeds payments; admin can unlock stages
5. Phase 6 (US4) → Admin payments dashboard complete
6. Phase 7 → Full system validation via quickstart.md

---

## Notes

- `PaymentService` owns **all** Stripe API calls — controllers must NEVER call Stripe directly
- Server-side Stripe calls use `config('services.stripe.secret')` (the **secret** key) — NOT `config('services.stripe.key')` (the publishable key which belongs in the frontend)
- `$request->getContent()` — not `$request->input()` or `json_decode()` — **must** be used in the webhook handler to preserve the raw body for HMAC signature verification
- The `payments/webhook` route **must** be registered outside any `auth` middleware group
- Feature tests use SQLite in-memory — mock `PaymentService` in checkout tests; use HMAC helper in webhook tests — never call real Stripe in tests
- All Blade strings **must** use `__('payments.key')` — never hardcode English text in views
- Amounts are stored and passed to Stripe in the **smallest currency unit** (e.g., fils for AED, cents for USD) — `50000` = 500.00 USD
- The unique `metadata.payment_id` on the Stripe session is the canonical webhook→payment matching key; `stripe_session_id` is secondary and gets overwritten on retry
- `paid` is terminal — once a Payment has `status=paid`, no code path should change its status
