# Data Model: Phase 2 — Client Onboarding

**Date**: 2026-03-19
**Status**: Complete

---

## Entity: `visa_types`

Configurable catalogue of visa categories that clients can apply for. Managed by admins (Phase 6); seeded for Phase 2.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `bigIncrements` | PK | |
| `name` | `string(100)` | NOT NULL, UNIQUE | Display name shown on the form (e.g., "Tourist Visa") |
| `description` | `text` | NULLABLE | Optional description for UI tooltip |
| `is_active` | `boolean` | NOT NULL, DEFAULT true | Only active types appear on the onboarding form |
| `created_at` | `timestamp` | | |
| `updated_at` | `timestamp` | | |

**Relationships**:
- Has many `visa_applications`

**Seeder**: `VisaTypeSeeder` — seeds at least 3 sample visa types (Tourist, Work Permit, Family Reunification) so the onboarding form is immediately usable.

---

## Entity: `visa_applications`

The core record created when a client completes the onboarding wizard. Represents one client's formal visa application.

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| `id` | `bigIncrements` | PK | |
| `user_id` | `foreignId` | NOT NULL, FK → `users.id`, CASCADE DELETE | The owning client account |
| `visa_type_id` | `foreignId` | NOT NULL, FK → `visa_types.id`, RESTRICT | The selected visa category |
| `reference_number` | `string(15)` | NOT NULL, UNIQUE | Format: `APP-XXXXX` (e.g., `APP-00001`); set by model `created` event |
| `status` | `string(30)` | NOT NULL, DEFAULT `'pending_review'` | Initial value on creation; transitions owned by Phase 3+ |
| `full_name` | `string(255)` | NOT NULL | Copied from form; may differ from `users.name` |
| `email` | `string(255)` | NOT NULL | Copied from form at submission time |
| `phone` | `string(30)` | NOT NULL | |
| `nationality` | `string(100)` | NOT NULL | Free-text country name |
| `country_of_residence` | `string(100)` | NOT NULL | |
| `job_title` | `string(150)` | NOT NULL | |
| `employment_type` | `string(50)` | NOT NULL | Enum values: `employed`, `self_employed`, `unemployed`, `student` |
| `monthly_income` | `decimal(10,2)` | NOT NULL | Currency: local default; no threshold validation in Phase 2 |
| `adults_count` | `unsignedTinyInteger` | NOT NULL, DEFAULT 1 | Minimum 1 (the applicant themselves) |
| `children_count` | `unsignedTinyInteger` | NOT NULL, DEFAULT 0 | |
| `application_start_date` | `date` | NOT NULL | Preferred start date provided by client |
| `notes` | `text` | NULLABLE | Additional notes from client |
| `agreed_to_terms` | `boolean` | NOT NULL, DEFAULT false | Must be `true` for submission to succeed (FR-013a) |
| `created_at` | `timestamp` | | |
| `updated_at` | `timestamp` | | |

**Relationships**:
- Belongs to `User` (the client account)
- Belongs to `VisaType`

**Reference number generation**: `VisaApplication::created` model event calls `$model->updateQuietly(['reference_number' => sprintf('APP-%05d', $model->id)])`. The `reference_number` column is `NOT NULL` but populated in the same DB transaction as the insert.

**Status lifecycle** (Phase 2 only — full FSM in Phase 3):

```
[created] → pending_review
```

Phase 3 will add transitions: `pending_review → in_progress → awaiting_documents → under_review → approved / rejected`.

---

## Entity: `users` (modified from Phase 1)

No schema changes required. Phase 2 reads `users.id`, `users.name`, `users.email` and assigns the `client` role via Spatie. The `is_active` and `last_login_at` columns added in Phase 1 are used as-is.

---

## Validation Rules (from `OnboardingRequest`)

| Field | Rules |
|-------|-------|
| `full_name` | required, string, max:255 |
| `email` | required, email, max:255, unique:users,email |
| `phone` | required, string, max:30 |
| `visa_type_id` | required, exists:visa_types,id (where is_active = true) |
| `adults_count` | required, integer, min:1, max:20 |
| `children_count` | required, integer, min:0, max:20 |
| `nationality` | required, string, max:100 |
| `country_of_residence` | required, string, max:100 |
| `job_title` | required, string, max:150 |
| `employment_type` | required, in:employed,self_employed,unemployed,student |
| `monthly_income` | required, numeric, min:0 |
| `application_start_date` | required, date, after_or_equal:today |
| `notes` | nullable, string, max:2000 |
| `agreed_to_terms` | required, accepted |

---

## Migration Order

1. `xxxx_create_visa_types_table.php`
2. `xxxx_create_visa_applications_table.php` (depends on `visa_types`)

Both run after the Phase 1 migrations (users, permissions, audit_logs).
