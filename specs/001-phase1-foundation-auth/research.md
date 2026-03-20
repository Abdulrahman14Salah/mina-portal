# Research: Phase 1 — Foundation & Architecture

**Date**: 2026-03-19
**Status**: Complete — no open NEEDS CLARIFICATION items.

All technologies were pre-specified by the project constitution and user input. This document records the key decisions and rationale for each.

---

## Decision 1 — Authentication Scaffolding

**Decision**: Laravel Breeze (Blade stack)

**Rationale**: Breeze generates minimal, readable controller and view scaffolding that is easy to override and extend. It ships with login, registration, password reset, and email verification out of the box. The Blade stack matches the constitution's SSR-first, no-SPA constraint.

**Alternatives considered**:
- **Laravel Fortify (headless)** — more flexible but requires wiring all views manually; adds complexity without benefit given the Blade constraint.
- **Laravel Jetstream** — includes teams, 2FA, API tokens; over-engineered for Phase 1 and harder to strip back.

---

## Decision 2 — Roles & Permissions

**Decision**: `spatie/laravel-permission` v6+ with granular named permissions

**Rationale**: Industry-standard Laravel package. Supports `$user->can('permission')` and `$user->hasRole('role')` natively. Integrates with Laravel Policies cleanly. Permissions and roles are seeded from a `RolePermissionSeeder`, ensuring reproducibility across environments.

**Permission set (Phase 1)**:

| Permission | Assigned to |
|---|---|
| `users.view` | admin |
| `users.create` | admin |
| `users.edit` | admin |
| `users.deactivate` | admin |
| `roles.assign` | admin |
| `dashboard.admin` | admin |
| `dashboard.client` | client |
| `dashboard.reviewer` | reviewer |

**Alternatives considered**:
- **Custom role column on `users` table** — rejected by constitution (Principle V); `if ($user->role === 'admin')` is explicitly forbidden.
- **Laravel's built-in Gates only** — lacks a seeding/management model; doesn't scale to future granular permissions.

---

## Decision 3 — Audit Logging

**Decision**: Custom `audit_logs` table managed by `AuditLogService`

**Rationale**: `spatie/laravel-activitylog` is an option but adds a dependency and schema that may not fit audit-specific needs (IP address, user agent, event taxonomy). A custom table gives full control over the schema and query patterns for compliance queries. The table is append-only with no updates or deletes permitted.

**Logged events** (FR-013):
- `login_success`
- `login_failed` (email attempted stored, no password)
- `logout`
- `account_deactivated`
- `role_changed` (old_role, new_role in metadata JSON)
- `user_created` (by admin)

**Alternatives considered**:
- `spatie/laravel-activitylog` — viable but heavier; overkill for a fixed event taxonomy.
- Writing to Laravel's `Log` facade only — not queryable; fails SC-006 (audit log with no gaps).

---

## Decision 4 — Account Status Enforcement

**Decision**: `is_active` boolean column on `users` table + `EnsureAccountIsActive` middleware

**Rationale**: Breeze's `AuthenticatedSessionController` invokes `Auth::attempt()` which does not check custom columns. A dedicated middleware placed after the `auth` middleware checks `is_active` on every authenticated request, logging the user out and returning a clear error if their account has been deactivated (FR-008).

**Alternatives considered**:
- Overriding `UserProvider::validateCredentials()` — works but couples status checking to the authentication layer, making it harder to test independently.
- Checking in each controller — violates DRY and risks gaps (FR-008 would be unsatisfied for any missed route).

---

## Decision 5 — Post-Login Redirect Strategy

**Decision**: Override `redirectTo()` in a custom `AuthenticatedSessionController` to inspect the authenticated user's role and redirect to the correct dashboard.

**Rationale**: Breeze uses `RouteServiceProvider::HOME` as a single constant redirect. With 3 roles, a role-aware redirect is required. Overriding the controller method is the cleanest Breeze-compatible approach.

**Redirect map**:
| Role | Route | URL |
|---|---|---|
| admin | `admin.dashboard` | `/admin/dashboard` |
| client | `client.dashboard` | `/client/dashboard` |
| reviewer | `reviewer.dashboard` | `/reviewer/dashboard` |

---

## Decision 6 — Self-Deactivation Guard

**Decision**: Business rule enforced in `UserService::deactivate()` + `UserPolicy::deactivate()`

**Rationale**: FR-014 prohibits admins from deactivating their own account. The check is placed in both the Policy (returns `false` if `$currentUser->id === $targetUser->id`) and in `UserService::deactivate()` as a defensive second layer. The controller never reaches the service if the Policy denies.

---

## Decision 7 — Password Strength Validation

**Decision**: Custom `RegisterRequest` Form Request with a `Password` rule object

**Rationale**: FR-011 requires minimum 8 characters, at least one uppercase, one lowercase, and one number. Laravel 11's `Illuminate\Validation\Rules\Password` fluent builder covers this natively: `Password::min(8)->mixedCase()->numbers()`.

---

## Resolved Clarifications

| Item | Resolution |
|---|---|
| Default role at self-registration | `client` (spec Assumptions) |
| Admin/Reviewer role assignment | Admin-only via `/admin/users/{user}/role` |
| Session idle timeout | Laravel default (2 hours); configurable via `SESSION_LIFETIME` in `.env` |
| Seed super-admin credentials | Loaded from `.env` (`ADMIN_EMAIL`, `ADMIN_PASSWORD`); not hardcoded |
| Post-login redirect per role | `/admin/dashboard`, `/client/dashboard`, `/reviewer/dashboard` (spec Clarifications) |
