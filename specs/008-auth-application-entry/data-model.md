# Data Model: Authentication & Application Entry (Phase 1)

**Date**: 2026-03-20
**Feature**: 008-auth-application-entry

> Phase 1 introduces no new database tables or migrations. All entities described here are already implemented. This document records the authoritative model definitions relevant to this feature for planning and testing reference.

---

## Entities

### User

**Table**: `users`
**Model**: `App\Models\User`
**Traits**: `HasRoles` (spatie/laravel-permission), `Notifiable`

| Field | Type | Rules |
|---|---|---|
| id | unsignedBigInteger | PK, auto-increment |
| name | string(255) | required |
| email | string(255) | required, unique |
| password | string (hashed) | required; min 8 chars, mixed case, at least 1 number |
| is_active | boolean | default: true |
| last_login_at | datetime | nullable; updated on each successful login |
| email_verified_at | datetime | nullable |
| remember_token | string(100) | nullable |
| created_at / updated_at | timestamps | auto-managed |

**Fillable**: `name`, `email`, `is_active`, `last_login_at`, `password`

**Roles** (via spatie): `admin`, `client`, `reviewer`
- New applicants via apply form → always assigned `client`
- Admin via seeder → always assigned `admin`

**Identity rule**: Email is globally unique across all users and roles.

---

### VisaApplication

**Table**: `visa_applications`
**Model**: `App\Models\VisaApplication`

| Field | Type | Rules |
|---|---|---|
| id | unsignedBigInteger | PK, auto-increment |
| reference_number | string | auto-generated after create (`APP-00001`) |
| user_id | foreignId → users | required; links application to account |
| visa_type_id | foreignId → visa_types | required; must exist in visa_types |
| status | string | default: `pending_review` on creation |
| full_name | string(255) | required |
| email | string(255) | required |
| phone | string(30) | required |
| nationality | string(100) | required |
| country_of_residence | string(100) | required |
| job_title | string(150) | required |
| employment_type | enum | required; one of: `employed`, `self_employed`, `unemployed`, `student` |
| monthly_income | decimal(10,2) | required; min 0 |
| adults_count | integer | required; min 1, max 20 |
| children_count | integer | required; min 0, max 20 |
| application_start_date | date | required; must be today or future |
| notes | text | nullable; max 2000 chars |
| agreed_to_terms | boolean | required; must be accepted (true) |
| created_at / updated_at | timestamps | auto-managed |

**Identity rule**: One `VisaApplication` per `User` is the expected pattern for Phase 1 (enforced by application logic, not a DB unique constraint).

**Lifecycle / state transitions (status field)**:

```
pending_review → [admin action] → active | rejected
```

Phase 1 only sets `pending_review` on creation. Further transitions are out of scope.

---

### Role (spatie managed)

**Table**: `roles` (managed by spatie/laravel-permission)

| Role | Permissions |
|---|---|
| `admin` | All 17 permissions |
| `client` | `dashboard.client`, `documents.upload`, `payments.pay` |
| `reviewer` | `dashboard.reviewer`, `tasks.view`, `tasks.advance`, `tasks.reject`, `documents.download`, `documents.reviewer-upload` |

Seeded by `RolePermissionSeeder`. No changes to roles or permissions in Phase 1.

---

### AuditLog

**Table**: `audit_logs`
**Service**: `App\Services\Auth\AuditLogService`

| Field | Type | Notes |
|---|---|---|
| id | unsignedBigInteger | PK |
| user_id | foreignId → users | nullable (pre-auth events) |
| event | string | e.g. `user_created`, `login_success`, `login_failed`, `application_created` |
| ip_address | string | captured per request |
| user_agent | string | captured per request |
| metadata | JSON | contextual data (e.g. reference_number) |
| created_at | timestamp | auto-managed |

Events emitted by Phase 1 flows:
- `user_created` — fired in `OnboardingService` on new account creation
- `application_created` — fired in `OnboardingService` on new VisaApplication creation
- `login_success` / `login_failed` — fired in `AuthService::login()`

---

## Relationships (Phase 1 scope)

```
User ──< VisaApplication  (one-to-one in practice; one-to-many by DB schema)
User ──< AuditLog         (nullable; pre-auth events have no user)
VisaApplication >── VisaType
VisaApplication >── User
```

---

## No Schema Changes Required

Phase 1 requires zero new migrations. All tables and columns are already present.
