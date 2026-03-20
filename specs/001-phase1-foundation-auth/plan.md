# Implementation Plan: Phase 1 — Foundation & Architecture

**Branch**: `001-phase1-foundation-auth` | **Date**: 2026-03-19 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/001-phase1-foundation-auth/spec.md`

## Summary

Implement the authentication foundation for the Visa Application Client Portal: user registration, login/logout, password reset, role-based access control (Admin / Client / Reviewer), and an admin user-management interface. Built on Laravel 11 + Breeze (Blade/SSR) with `spatie/laravel-permission` for roles and permissions, and a custom `audit_logs` table for all authentication events.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 11
**Primary Dependencies**: Laravel Breeze (Blade), `spatie/laravel-permission` v6+
**Storage**: MySQL (MAMP local dev); configurable via `.env` for production
**Testing**: PHPUnit via Laravel feature tests; SQLite in-memory test database
**Target Platform**: Web server — MAMP (local), Linux (production)
**Project Type**: Web application (server-side Blade rendering)
**Performance Goals**: Registration < 60 s (SC-001), Login redirect < 10 s (SC-002)
**Constraints**: No SPA framework; CSRF enabled; all protected routes behind `auth` middleware; no inline `$request->validate()`; no `$guarded = []`
**Scale/Scope**: 3 fixed roles, initially hundreds of users

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| # | Principle | Status | Notes |
|---|-----------|--------|-------|
| I | Modular Architecture | ✅ PASS | Auth module: `app/Modules/Auth/` owns Controllers, Services, Form Requests. Admin module: `app/Modules/Admin/` for user management. |
| II | Separation of Concerns | ✅ PASS | Breeze controllers overridden to delegate to `AuthService` / `UserService`. No business logic in controllers or Blade. |
| III | Database-Driven Workflows | ✅ N/A | No visa workflows in Phase 1. |
| IV | API-Ready Design | ✅ PASS | All auth operations go through Service classes. Controllers return structured data consumed by Views. |
| V | Roles & Permissions | ✅ PASS | `spatie/laravel-permission` with granular permissions (`users.view`, `users.create`, `users.edit`, `users.deactivate`, `roles.assign`). No `$user->role === 'admin'` checks. Policies for User model. |
| VI | Payment Integrity | ✅ N/A | Out of scope Phase 1. |
| VII | Secure Document Handling | ✅ N/A | Out of scope Phase 1. |
| VIII | Dynamic Workflow Engine | ✅ N/A | Out of scope Phase 1. |
| IX | Security by Default | ✅ PASS | Form Requests for all input; `$fillable` on User model; CSRF on all state-changing routes; auth middleware on all protected routes; Policies registered in `AuthServiceProvider`. |
| X | Multi-Language Support | ✅ PASS | All Blade strings via `__()`. `resources/lang/en/` and `resources/lang/ar/` for auth strings. RTL applied when locale is `ar`. |
| XI | Observability & Activity Logging | ✅ PASS | `audit_logs` table captures: `login_success`, `login_failed`, `logout`, `account_deactivated`, `role_changed`, `user_created`. |
| XII | Testing Standards | ✅ PASS | Feature tests required for: registration, login, logout, role access gates, admin user management, password reset. |

**Constitution Gate**: PASS — no violations. No Complexity Tracking entries required.

## Project Structure

### Documentation (this feature)

```text
specs/001-phase1-foundation-auth/
├── plan.md              ← This file
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output
├── quickstart.md        ← Phase 1 output
└── contracts/
    └── routes.md        ← Phase 1 output
```

### Source Code Layout

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   ├── RegisteredUserController.php   # Overrides Breeze; delegates to AuthService
│   │   │   ├── AuthenticatedSessionController.php
│   │   │   └── PasswordResetLinkController.php
│   │   ├── Admin/
│   │   │   └── UserController.php             # CRUD + deactivate + role assignment
│   │   └── DashboardController.php            # Role-aware dashboard redirect
│   ├── Middleware/
│   │   └── EnsureAccountIsActive.php          # Denies login to deactivated accounts
│   └── Requests/
│       ├── Auth/
│       │   ├── LoginRequest.php               # Breeze default (kept)
│       │   └── RegisterRequest.php            # Custom: email, password strength
│       └── Admin/
│           ├── StoreUserRequest.php
│           ├── UpdateUserRequest.php
│           └── AssignRoleRequest.php
├── Models/
│   └── User.php                               # + HasRoles, is_active, last_login_at
├── Policies/
│   └── UserPolicy.php                         # view, create, edit, deactivate, assignRole
├── Services/
│   ├── Auth/
│   │   ├── AuthService.php                    # register, login, logout, resetPassword
│   │   └── AuditLogService.php                # log auth events to audit_logs
│   └── Admin/
│       └── UserService.php                    # CRUD, deactivate, assignRole

database/
├── migrations/
│   ├── 0001_01_01_000000_create_users_table.php   # Laravel default + is_active + last_login_at
│   ├── xxxx_create_permission_tables.php            # Spatie (published)
│   └── xxxx_create_audit_logs_table.php
└── seeders/
    ├── RolePermissionSeeder.php               # Seeds roles + granular permissions
    ├── AdminUserSeeder.php                    # Seeds super-admin account
    └── DatabaseSeeder.php

resources/
├── views/
│   ├── auth/                                  # Breeze views (customized for localization)
│   │   ├── login.blade.php
│   │   ├── register.blade.php
│   │   ├── forgot-password.blade.php
│   │   └── reset-password.blade.php
│   ├── admin/
│   │   └── users/
│   │       ├── index.blade.php
│   │       ├── create.blade.php
│   │       └── edit.blade.php
│   └── dashboard/
│       ├── admin.blade.php
│       ├── client.blade.php
│       └── reviewer.blade.php
└── lang/
    ├── en/
    │   └── auth.php                           # All auth-related strings
    └── ar/
        └── auth.php

routes/
├── web.php
└── auth.php                                   # Breeze routes (extended)

tests/
└── Feature/
    └── Auth/
        ├── RegistrationTest.php
        ├── LoginTest.php
        ├── LogoutTest.php
        ├── PasswordResetTest.php
        ├── RoleAccessControlTest.php
        ├── DeactivatedAccountTest.php
        └── Admin/
            └── UserManagementTest.php
```

**Structure Decision**: Single Laravel monolith (Option 1 adapted). Module grouping via directory naming within standard Laravel conventions (`app/Http/Controllers/Auth/`, `app/Services/Auth/`, etc.) — no separate `app/Modules/` top-level namespace to keep Breeze compatibility straightforward.

## Complexity Tracking

> No constitution violations — table not required.
