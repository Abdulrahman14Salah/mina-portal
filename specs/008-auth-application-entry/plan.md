# Implementation Plan: Authentication & Application Entry

**Branch**: `008-auth-application-entry` | **Date**: 2026-03-20 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/008-auth-application-entry/spec.md`

## Summary

Phase 1 wires the application form as the portal's default entry point, adds the Login ↔ Apply navigation toggle to both auth views, and customises the duplicate-email validation message on the apply form. The vast majority of the underlying infrastructure (auth controllers, services, seeders, rate-limiting, role assignment, auto-login) is already fully implemented. This plan addresses only the three remaining gaps.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 11
**Primary Dependencies**: Laravel Breeze (Blade SSR), spatie/laravel-permission v6+, Alpine.js v3
**Storage**: MySQL (MAMP local, port 8889) for dev; SQLite in-memory for tests
**Testing**: PHPUnit via `php artisan test`
**Target Platform**: MAMP local dev → production web server
**Project Type**: Web application (Blade SSR, no SPA)
**Performance Goals**: Standard web page load; no special targets for this phase
**Constraints**: All strings via `__()` localisation; no hardcoded English; no business logic in Blade; all validation via Form Request classes
**Scale/Scope**: Single-tenant portal; user volume not a constraint for this phase

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| Principle | Status | Notes |
|---|---|---|
| I. Modular Architecture | ✅ Pass | Auth module (`Auth/`), Client module (`Client/`) already established |
| II. Separation of Concerns | ✅ Pass | OnboardingController delegates to OnboardingService; route change is trivial |
| III. Database-Driven Workflows | ✅ Pass | No workflow or form schema is hardcoded; VisaType is DB-driven |
| IV. API-Ready Design | ✅ Pass | Service layer already in place; no direct Eloquent in controllers |
| V. Roles & Permissions | ✅ Pass | spatie roles assigned via AuthService and OnboardingService; no hardcoded role checks |
| VI. Payment Integrity | N/A | Not in scope for Phase 1 |
| VII. Secure Document Handling | N/A | Not in scope for Phase 1 |
| VIII. Dynamic Workflow Engine | N/A | Not in scope for Phase 1 |
| IX. Security by Default | ✅ Pass | Form Requests used; CSRF active; rate-limiting in LoginRequest (5 attempts); `$fillable` on all models |
| X. Multi-Language Support | ✅ Pass | All strings use `__()` with lang keys; toggle links must also use lang keys |
| XI. Observability | ✅ Pass | AuditLogService already logs auth events; no new event types needed |
| XII. Testing Standards | ✅ Pass | Feature tests required for all three gaps; existing auth tests must remain green |

**No constitution violations detected. No Complexity Tracking entries required.**

## Project Structure

### Documentation (this feature)

```text
specs/008-auth-application-entry/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
└── tasks.md             # Phase 2 output (/speckit.tasks command)
```

### Source Code (files touched by this feature)

```text
routes/
└── web.php                                         # Change GET / route

resources/views/
├── auth/
│   └── login.blade.php                             # Add "Apply now" toggle link
└── client/
    └── onboarding/
        └── form.blade.php                          # Add "Login" toggle link + login-link highlight on duplicate email

app/Http/Requests/
└── Client/
    └── OnboardingRequest.php                       # Customise duplicate-email validation message

lang/
├── en/
│   └── client.php                                  # Add toggle + error lang keys
└── ar/
    └── client.php                                  # Arabic translations for same keys

tests/Feature/
└── Auth/
    └── ApplicationEntryTest.php                    # New: covers all 3 gaps
```

**Structure Decision**: Single Laravel project; no new directories required. All changes are incremental modifications to existing files within the established Auth and Client modules.

## What Is Already Implemented

The following Phase 1 requirements are **fully satisfied by existing code** and require no changes:

| Requirement | Implemented In |
|---|---|
| FR-002: /apply route renders apply form | `routes/web.php` + `OnboardingController@show` |
| FR-003: Auto-login + Client role assignment after submission | `OnboardingService::handle()` — calls `Auth::login()`, `assignRole('client')` |
| FR-007 / FR-008: Admin seeder from .env with Admin role | `AdminUserSeeder` + `RolePermissionSeeder` |
| FR-009: Full apply form (all visa fields, validation, no data loss) | `OnboardingRequest` + `form.blade.php` (3-step wizard) |
| FR-010: Non-specific login error message | `LoginRequest::authenticate()` + AuthService |
| FR-012: Brute-force throttle after 5 failed login attempts | `LoginRequest` (built-in Laravel throttle, 5 attempts) |
| FR-013: Password strength (8 chars, uppercase, number) | `OnboardingRequest` — `Password::min(8)->mixedCase()->numbers()` |
| SC-002: Single submission → account + role + auto-login + dashboard redirect | `OnboardingController::store()` + `OnboardingService` |
| SC-005: Idempotent admin seeding | `AdminUserSeeder` uses `updateOrCreate` |

## Remaining Gaps (3 items)

### Gap 1 — Root Route Serves Welcome View, Not Apply Form

**Current state**: `GET /` returns `view('welcome')` with no guest middleware.
**Required state**: `GET /` renders the apply form for guests; authenticated users redirected to dashboard.
**Fix**: Replace the root route closure with a redirect to `onboarding.show` (or direct controller call), wrapped in the existing `guest` middleware group.

### Gap 2 — Login ↔ Apply Toggle Links Missing From Both Views

**Current state**: `login.blade.php` has no link to `/apply`. `form.blade.php` has no link to `/login`.
**Required state**: Each view has a centred text link below the form pointing to the other.
**Fix**: Add a single `<p>` element below each form using localisation keys. On the apply form, the login link must additionally be given an `id` so it can be visually highlighted via CSS when a duplicate-email error is present.

### Gap 3 — Duplicate Email Error Does Not Name Cause or Highlight Login Link

**Current state**: Submitting the apply form with an existing email shows Laravel's generic "The email has already been taken." message. The login link is not highlighted.
**Required state**: Error reads "An account with this email already exists." and the login link below the form receives a visible highlight class.
**Fix**: Add a custom `messages()` method to `OnboardingRequest` overriding the `unique` rule message. In `form.blade.php`, conditionally apply a highlight CSS class to the login link when `$errors->has('email')` and the error contains the duplicate-account message.
