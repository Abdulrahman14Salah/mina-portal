# Implementation Plan: Phase 2 — Client Onboarding

**Branch**: `002-client-onboarding` | **Date**: 2026-03-19 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/002-client-onboarding/spec.md`

## Summary

Implement the client onboarding foundation for the Visa Application Portal: a 3-step Alpine.js wizard form that collects 13 fields of personal, visa, and employment data, creates a client user account and a `visa_applications` record atomically, and redirects the client to an 8-tab Blade dashboard. Built on Laravel 11 + Breeze (Blade/SSR) with Alpine.js v3 for wizard step control, a `SetLocale` middleware for session-based EN/AR switching, and Spatie's `client` role assigned automatically on submission.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 11
**Primary Dependencies**: Laravel Breeze (Blade), Alpine.js v3, `spatie/laravel-permission` v6+
**Storage**: MySQL (MAMP local dev); configurable via `.env` for production
**Testing**: PHPUnit via Laravel feature tests; SQLite in-memory test database
**Target Platform**: Web server — MAMP (local), Linux (production)
**Project Type**: Web application (server-side Blade rendering)
**Performance Goals**: Form submission + account creation < 60 s (SC-001), Dashboard load < 5 s (SC-003)
**Constraints**: No SPA framework; Alpine.js for step visibility only; CSRF enabled; all protected routes behind `auth` middleware; no inline `$request->validate()`; no `$guarded = []`; all strings via `__()`
**Scale/Scope**: Single active application per client; initially hundreds of clients

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| # | Principle | Status | Notes |
|---|-----------|--------|-------|
| I | Modular Architecture | ✅ PASS | Client module: `app/Http/Controllers/Client/`, `app/Services/Client/`, `app/Http/Requests/Client/`. Language controller at root `Controllers/LanguageController.php` (cross-cutting). |
| II | Separation of Concerns | ✅ PASS | `OnboardingController` delegates entirely to `OnboardingService`. `DashboardController` delegates to a `DashboardService` (or reads via `VisaApplication` model via service). Zero business logic in controllers or Blade. |
| III | Database-Driven Workflows | ✅ PASS | Visa types live in `visa_types` table; form sources them dynamically (FR-012). `visa_applications` table stores all application data. No workflow hardcoded in PHP. |
| IV | API-Ready Design | ✅ PASS | All data retrieval and mutation through Service classes. Controllers return structured arrays/models consumed by Views. |
| V | Roles & Permissions | ✅ PASS | `client` role assigned via `$user->assignRole('client')`. `VisaApplicationPolicy` enforces own-data access. `can:dashboard.client` permission gate on dashboard. No `$user->role === 'client'` checks anywhere. |
| VI | Payment Integrity | ✅ N/A | Phase 5. |
| VII | Secure Document Handling | ✅ N/A | Phase 4. |
| VIII | Dynamic Workflow Engine | ✅ N/A | Phase 3. `visa_types` table introduced here is the foundation. |
| IX | Security by Default | ✅ PASS | `OnboardingRequest` Form Request validates all 13 fields + consent. `$fillable` on all new models. CSRF on all routes. `auth` middleware on dashboard. `VisaApplicationPolicy` registered. `guest` middleware blocks re-registration. |
| X | Multi-Language Support | ✅ PASS | `SetLocale` middleware + `LanguageController` for session-based EN/AR switching on public pages. All Blade strings via `__('client.*')`. RTL layout applied when locale is `ar`. |
| XI | Observability & Activity Logging | ✅ PASS | `AuditLogService::log('application_created', $user, ['reference' => $application->reference_number])` called inside the onboarding transaction. |
| XII | Testing Standards | ✅ PASS | Feature tests required for: onboarding happy path, duplicate email, step validation, consent enforcement, dashboard access, RBAC, locale switching. |

**Constitution Gate**: PASS — no violations. No Complexity Tracking entries required.

## Project Structure

### Documentation (this feature)

```text
specs/002-client-onboarding/
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
│   │   ├── LanguageController.php               # POST /language/{locale} → set session locale
│   │   └── Client/
│   │       ├── OnboardingController.php         # GET/POST /apply — wizard display + submission
│   │       └── DashboardController.php          # GET /client/dashboard/{tab?} — 8-tab dashboard
│   ├── Middleware/
│   │   └── SetLocale.php                        # NEW: reads session('locale'), calls App::setLocale()
│   └── Requests/
│       └── Client/
│           └── OnboardingRequest.php            # 13 fields + agreed_to_terms validation
├── Models/
│   ├── VisaApplication.php                      # belongsTo User, VisaType; reference_number via created event
│   └── VisaType.php                             # hasMany VisaApplications; scope: active()
├── Policies/
│   └── VisaApplicationPolicy.php               # view: client may only see their own application
└── Services/
    └── Client/
        └── OnboardingService.php                # handle(): DB::transaction → create User + VisaApplication

database/
├── migrations/
│   ├── xxxx_create_visa_types_table.php
│   └── xxxx_create_visa_applications_table.php
└── seeders/
    ├── VisaTypeSeeder.php                       # Seeds: Tourist Visa, Work Permit, Family Reunification
    └── DatabaseSeeder.php                       # Add VisaTypeSeeder call (after RolePermissionSeeder)

resources/
├── views/
│   ├── client/
│   │   ├── onboarding/
│   │   │   └── form.blade.php                  # Alpine.js 3-step wizard; x-data={step:1}
│   │   └── dashboard/
│   │       ├── index.blade.php                 # Tab nav + @include active tab partial
│   │       └── tabs/
│   │           ├── overview.blade.php          # Status, reference number, summary
│   │           ├── documents.blade.php         # Empty state (Phase 4)
│   │           ├── tasks.blade.php             # Empty state (Phase 3)
│   │           ├── payments.blade.php          # Empty state (Phase 5)
│   │           ├── timeline.blade.php          # Empty state (Phase 3)
│   │           ├── messages.blade.php          # Empty state (Phase 9)
│   │           ├── profile.blade.php           # Show client details; locale preference toggle
│   │           └── support.blade.php           # Empty state / contact info placeholder
│   └── layouts/
│       └── guest.blade.php                     # MODIFY: add EN/AR language toggle to header
└── lang/
    ├── en/
    │   └── client.php                          # All onboarding + dashboard strings (EN)
    └── ar/
        └── client.php                          # All onboarding + dashboard strings (AR)

routes/
└── web.php                                     # MODIFY: add /apply, /language/{locale}, replace /client/dashboard stub

tests/
└── Feature/
    └── Client/
        ├── OnboardingTest.php                  # Happy path, duplicate email, validation, consent
        └── DashboardTest.php                   # Tab navigation, RBAC, unauthenticated redirect
```

**Structure Decision**: Laravel monolith (consistent with Phase 1). Module grouping via directory naming under standard Laravel conventions (`app/Http/Controllers/Client/`, `app/Services/Client/`, etc.). No separate top-level namespace — keeps Breeze compatibility and IDE support straightforward.

## Complexity Tracking

> No constitution violations — table not required.
