# Route Contracts: Phase 2 — Client Onboarding

**Date**: 2026-03-19

All routes are web routes (Blade, CSRF enabled, session-based auth).

---

## Public Routes (guest middleware)

### GET `/apply`
- **Controller**: `Client\OnboardingController@show`
- **Purpose**: Render the 3-step Alpine.js onboarding wizard
- **Middleware**: `guest` (authenticated clients are redirected to `/client/dashboard`)
- **Data passed to view**: `$visaTypes` — collection of active `VisaType` records (id + name)
- **Named route**: `onboarding.show`

### POST `/apply`
- **Controller**: `Client\OnboardingController@store`
- **Purpose**: Validate all fields, create client account + application atomically, log in, redirect to dashboard
- **Middleware**: `guest`
- **Request**: `Client\OnboardingRequest` (13 fields + `agreed_to_terms`)
- **Success response**: `redirect()->route('client.dashboard')` with success flash
- **Failure response**: `redirect()->back()->withErrors()->withInput()` (wizard resets to Step 1)
- **Named route**: `onboarding.store`

---

## Language Routes (public, no auth required)

### POST `/language/{locale}`
- **Controller**: `LanguageController@switch`
- **Purpose**: Set session locale (`en` or `ar`) and redirect back
- **Middleware**: none (public)
- **Validation**: `{locale}` must be in `['en', 'ar']`; invalid values silently fall back to `en`
- **Response**: `redirect()->back()`
- **Named route**: `language.switch`

---

## Authenticated Client Routes (auth + active + verified)

### GET `/client/dashboard/{tab?}`
- **Controller**: `Client\DashboardController@show`
- **Purpose**: Render the 8-tab client dashboard; `{tab}` defaults to `overview`
- **Middleware**: `['auth', 'verified', 'active']`
- **Authorization**: `VisaApplicationPolicy::view` — client may only view their own application; redirects to `/apply` if no application record found
- **Valid tab values**: `overview`, `documents`, `tasks`, `payments`, `timeline`, `messages`, `profile`, `support`
- **Invalid tab value**: silently falls back to `overview`
- **Data passed to view**: `$application` (the client's `VisaApplication` with `visaType` eager-loaded), `$activeTab`
- **Named route**: `client.dashboard`

---

## Middleware Applied

| Middleware | Where applied | Purpose |
|------------|---------------|---------|
| `SetLocale` | Global web group | Reads `session('locale', 'en')`, calls `App::setLocale()` |
| `EnsureAccountIsActive` | Global web group (Phase 1) | Kicks out deactivated users |
| `guest` | `/apply` routes | Blocks already-authenticated users |
| `auth` | `/client/dashboard` | Requires login |
| `verified` | `/client/dashboard` | Requires email verification (currently a no-op — MustVerifyEmail not implemented) |
| `active` | `/client/dashboard` | Alias for `EnsureAccountIsActive` (already in web group; included explicitly for clarity) |

---

## Route Registration in `routes/web.php`

```php
// Language switching (public)
Route::post('/language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');

// Client onboarding (guests only)
Route::middleware('guest')->group(function () {
    Route::get('/apply', [Client\OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/apply', [Client\OnboardingController::class, 'store'])->name('onboarding.store');
});

// Client dashboard (authenticated clients)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/client/dashboard/{tab?}', [Client\DashboardController::class, 'show'])
        ->name('client.dashboard');
});
```

**Note**: The existing stub `Route::get('/client/dashboard', fn() => view('dashboard.client'))->middleware('can:dashboard.client')->name('client.dashboard')` from Phase 1 must be removed and replaced with the route above.
