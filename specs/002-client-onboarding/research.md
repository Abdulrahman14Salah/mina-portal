# Research: Phase 2 — Client Onboarding

**Date**: 2026-03-19
**Status**: Complete — no open NEEDS CLARIFICATION items.

All technology choices are constrained by the project constitution (Laravel 11 / Blade / Alpine.js / Spatie). This document records decisions specific to the multi-step wizard, reference number generation, locale switching, and dashboard routing.

---

## Decision 1 — Multi-Step Wizard Implementation

**Decision**: Alpine.js `x-data` / `x-show` step visibility on a single Blade form — all fields present in the DOM, submitted in one POST.

**Rationale**: Alpine.js v3 is already installed and initialised via Breeze (`resources/js/app.js`). The navigation component, dropdown, and modal all use it, so it is the established pattern. A single-form approach means the server receives all fields in one request, keeping the `OnboardingRequest` Form Request simple and the controller thin. Step advancement is gated by Alpine.js checking HTML5 `required` constraints on the current step's visible inputs before calling `step++`.

**Wizard state on refresh**: Per spec clarification, wizard state is not preserved on page reload — the client returns to Step 1 with a blank form. Alpine.js local state (not localStorage) satisfies this exactly.

**Step validation strategy**: HTML5 `required` attributes on each field + Alpine.js checks `$el.querySelectorAll('[required]')` within the current step container before advancing. Full server-side validation via `OnboardingRequest` is enforced on the final POST regardless.

**Alternatives considered**:
- **Server-side partial validation with session** — POST each step to a `/apply/step/{n}` endpoint, store partial data in session, render next step. Correct but requires 3 routes, session management, and more controller logic. Not worth it for a 13-field form.
- **Livewire** — Would provide real-time server-side validation per step. Adds a dependency and diverges from the existing Breeze Alpine.js pattern. Deferred to a future phase if needed.

---

## Decision 2 — Application Reference Number Generation

**Decision**: Derive the reference number from the `visa_applications.id` auto-increment using `sprintf('APP-%05d', $application->id)` in an Eloquent `created` model event. The `reference_number` column is written immediately after insert in the same transaction.

**Rationale**: MySQL auto-increment IDs are atomic and unique by definition — no race condition is possible. The format `APP-00001` is human-readable and matches the spec (`APP-XXXXX`). Deriving from `id` avoids a separate counter table or sequence.

**Implementation**: In `VisaApplication::boot()`, register a `created` observer that calls `$model->updateQuietly(['reference_number' => sprintf('APP-%05d', $model->id)])`. `updateQuietly` avoids re-firing events.

**Alternatives considered**:
- **Separate `application_counters` table with pessimistic locking** — Correct but over-engineered; adds a table and a lock for no benefit over ID-derived numbering.
- **UUID** — Globally unique but not human-friendly; clients cannot easily quote it in support requests.

---

## Decision 3 — Atomic Account + Application Creation

**Decision**: Wrap user creation and application creation in `DB::transaction()` inside `OnboardingService::handle()`. If either fails, both are rolled back.

**Rationale**: FR-004 requires atomicity. `DB::transaction()` is the standard Laravel mechanism. The service method is the correct layer — the controller simply calls `OnboardingService::handle(OnboardingRequest $request)` and handles the happy path.

**Flow inside the transaction**:
1. `User::create([...])` → assigns Client role
2. `VisaApplication::create(['user_id' => $user->id, ...])` → model `created` event writes `reference_number`
3. `AuditLogService::log('application_created', $user, ['reference' => $application->reference_number])`
4. `Auth::login($user)`

If any step throws, the transaction rolls back and the exception bubbles to Laravel's exception handler, which redirects back with an error.

---

## Decision 4 — Session-Based Locale Switching

**Decision**: A `SetLocale` middleware reads `session('locale', 'en')` and calls `App::setLocale($locale)` on every web request. A `LanguageController` exposes `POST /language/{locale}` to write the chosen locale to the session and redirect back. The guest layout header renders an EN / AR toggle as two form buttons.

**Rationale**: No user account exists at the time the onboarding form is displayed, so locale cannot be stored in a user preference. Session is the correct store for unauthenticated locale. After login, the session locale persists naturally. The middleware runs before any view is rendered, so `__()` calls always use the correct locale.

**Supported locales**: `en`, `ar`. Any other value is rejected by `LanguageController` (validated against an allowlist).

**Alternatives considered**:
- **Browser `Accept-Language` header auto-detection** — Unreliable across VPNs and shared machines. Added as a fallback (read once on first visit, then session overrides) was considered but rejected as premature for Phase 2.
- **Storing locale in User model** — Not available pre-login. Post-login, the Profile tab (Phase 2) will allow locale preference to be persisted.

---

## Decision 5 — 8-Tab Dashboard Routing

**Decision**: Single controller action `DashboardController@show(string $tab = 'overview')` with a route parameter. The view renders a tab navigation component and `@include`s `client/dashboard/tabs/{$tab}.blade.php`. Unknown tab names fall back to `overview`.

**Rationale**: One route, one controller action, one view shell. Each tab is an isolated Blade partial — adding Phase 3+ content to a tab does not touch any other file. The active tab is highlighted by comparing `$tab` to each nav item.

**Route**: `GET /client/dashboard/{tab?}` named `client.dashboard` with `{tab}` defaulting to `overview`.

**Alternatives considered**:
- **One route per tab** — 8 named routes and 8 controller methods. More explicit but verbose; tab partials achieve the same isolation at lower route cost.
- **AJAX tab loading** — Incompatible with SSR-first constraint.
