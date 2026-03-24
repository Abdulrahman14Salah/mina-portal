# Quickstart: Authentication & Application Entry (Phase 1)

**Date**: 2026-03-20
**Feature**: 008-auth-application-entry

## What This Phase Does

Completes the entry-point wiring for the portal:
1. Root URL (`/`) redirects to the apply form instead of a welcome page
2. Apply and Login pages each display a toggle link to the other
3. Submitting the apply form with an existing email shows a specific error message and highlights the login link

## Prerequisites

- MAMP running with MySQL on port 8889
- `.env` configured with `DB_*`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`
- Dependencies installed: `composer install`
- Database seeded: `php artisan migrate:refresh --seed`

## Running the Application

```bash
php artisan serve
```

Visit `http://localhost:8000` — you should land on the apply form (not a welcome page).

## Key User Flows to Verify

### New Applicant (Happy Path)
1. Navigate to `http://localhost:8000`
2. Verify the apply form loads (3-step wizard)
3. Complete all fields and submit
4. Verify you are automatically logged in and land on `/client/dashboard`

### Returning Client (Toggle Navigation)
1. Navigate to `http://localhost:8000/apply`
2. Verify "Already have an account? Login" link is visible below the form
3. Click it — verify you land on `/login`
4. On the login page, verify "Don't have an account? Apply now" link is visible below the form
5. Click it — verify you return to `/apply`

### Duplicate Email Handling
1. Register once with `test@example.com`
2. Log out
3. Navigate to `/apply` and submit with the same email
4. Verify the error message reads "An account with this email already exists."
5. Verify the login link below the form is visually highlighted

### Authenticated User Guard
1. Log in as any user
2. Navigate to `/`, `/apply`, and `/login`
3. Verify all three redirect to your dashboard — none show the entry forms

### Admin Seeder
```bash
php artisan migrate:refresh --seed
```
Log in with `ADMIN_EMAIL` and `ADMIN_PASSWORD` from your `.env` — verify admin dashboard access.

## Running Tests

```bash
php artisan test --filter=ApplicationEntryTest
php artisan test  # full suite — must stay green
```

## Key Files

| File | Purpose |
|---|---|
| `routes/web.php` | Root route change (`GET /` → redirect to `/apply`) |
| `resources/views/auth/login.blade.php` | "Apply now" toggle link added |
| `resources/views/client/onboarding/form.blade.php` | "Login" toggle link + duplicate-email highlight |
| `app/Http/Requests/Client/OnboardingRequest.php` | Custom duplicate-email error message |
| `lang/en/client.php` | Toggle link lang keys |
| `lang/en/auth.php` | Toggle link lang key for login page |
| `lang/ar/client.php` | Arabic translations |
| `lang/ar/auth.php` | Arabic translations |
| `tests/Feature/Auth/ApplicationEntryTest.php` | Feature tests for all 3 gaps |

## What Was Already Working Before This Phase

The following flows work unchanged and should not be broken:
- Password reset flow
- Email verification flow
- Admin/Reviewer dashboard access
- Document upload/download
- Payment flow
