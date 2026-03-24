# Research: Authentication & Application Entry (Phase 1)

**Date**: 2026-03-20
**Feature**: 008-auth-application-entry

## Summary

Research confirmed that nearly all Phase 1 requirements are already satisfied by the existing codebase. This document records the findings and decisions for the three remaining implementation gaps.

---

## Finding 1: Root Route Strategy

**Question**: Should `GET /` redirect to `/apply` (302) or render the apply form directly via the controller?

**Decision**: Redirect `GET /` → `/apply` using `redirect()->route('onboarding.show')`.

**Rationale**:
- Keeps a single canonical URL for the apply form (`/apply`). Avoids duplicating controller logic in the route file.
- Consistent with the existing pattern: `/` and `/apply` are specified to show "identical content" — a redirect is the cleanest way to enforce this without maintaining two route-controller bindings.
- The `guest` middleware on the redirect prevents authenticated users from ever reaching the apply form.

**Alternatives considered**:
- Direct render via `OnboardingController@show` on `/` — rejected because it creates two URLs serving the same content, complicating canonical URL management and SEO.
- Named redirect (`redirect()->route('onboarding.show')`) — chosen for resilience against future URL changes.

---

## Finding 2: Guest Middleware on Root Route

**Question**: Does the existing `guest` middleware redirect authenticated users to the dashboard?

**Decision**: Yes — Laravel Breeze's `guest` middleware (the `RedirectIfAuthenticated` middleware) already redirects authenticated users to their intended home route. No custom logic needed.

**Rationale**: The `guest` middleware group used on `/apply` already handles this. Wrapping `GET /` in the same group achieves FR-006 (authenticated users redirected to dashboard) at zero additional cost.

---

## Finding 3: Duplicate Email Validation Message

**Question**: How should the duplicate-email validation message be customised in `OnboardingRequest`?

**Decision**: Add a `messages()` method to `OnboardingRequest` returning a custom message for the `email.unique` rule key. Add a conditional highlight on the login link in the apply form view using `$errors->has('email')`.

**Rationale**:
- Laravel Form Requests support per-rule message overrides via `messages()` — this is the idiomatic, constitution-compliant approach (all validation stays in Form Request classes).
- The view-level highlight is purely presentational and carries no business logic, so it is appropriate in Blade.
- The highlight is triggered by `$errors->has('email')` rather than inspecting the message content, keeping the view logic simple.

**Alternatives considered**:
- Custom validation rule class — rejected as over-engineering for a single message override.
- JavaScript-driven highlight — rejected; server-side validation response is sufficient.

---

## Finding 4: Lang Key Strategy for Toggle Links

**Question**: Should new language keys be added to `lang/en/client.php` or `lang/en/auth.php`?

**Decision**: Add to `lang/en/client.php` (and `lang/ar/client.php`) since the toggle links appear on the apply/onboarding view, which belongs to the Client module. The login view toggle link belongs to the Auth module and should be added to `lang/en/auth.php` (and `lang/ar/auth.php`).

**Rationale**: Follows the existing convention — `client.php` holds onboarding/client-facing strings; `auth.php` holds login/registration strings.

---

## Pre-existing Implementation Inventory

The following were researched and confirmed as fully implemented — **no changes required**:

| Item | Location | Status |
|---|---|---|
| Auto-login after apply form submission | `OnboardingService::handle()` — `Auth::login($user)` | ✅ Done |
| Client role assignment | `OnboardingService::handle()` — `$user->assignRole('client')` | ✅ Done |
| Full apply form (all visa fields, 3-step wizard) | `OnboardingRequest` + `form.blade.php` | ✅ Done |
| Brute-force throttle (5 attempts) | `LoginRequest` — built-in Laravel throttle | ✅ Done |
| Password strength (8 chars, uppercase, number) | `OnboardingRequest` — `Password::min(8)->mixedCase()->numbers()` | ✅ Done |
| Non-specific login error | `LoginRequest::authenticate()` throws `ValidationException` | ✅ Done |
| Admin seeder from .env | `AdminUserSeeder` — `updateOrCreate` | ✅ Done |
| Audit logging for auth events | `AuditLogService` — `user_created`, `login_success`, `login_failed` | ✅ Done |
| Multi-locale support | `SetLocale` middleware + `__()` throughout | ✅ Done |
