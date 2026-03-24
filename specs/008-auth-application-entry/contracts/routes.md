# Route Contracts: Authentication & Application Entry (Phase 1)

**Date**: 2026-03-20
**Feature**: 008-auth-application-entry

> These are the HTTP route contracts for all entry-point and auth routes relevant to Phase 1. Routes marked **[CHANGE]** are being modified. Routes marked **[EXISTS]** are already in place and should not be altered. Routes marked **[NEW]** do not yet exist.

---

## Apply / Onboarding Routes

### GET / — Root Route
**Status**: [CHANGE]
**Middleware**: `guest`
**Before**: Returns `view('welcome')`
**After**: `redirect()->route('onboarding.show')` (301/302 redirect to `/apply`)
**Auth behaviour**: Authenticated users redirected to `/dashboard` by `guest` middleware (no change to middleware)

---

### GET /apply — Show Apply Form
**Status**: [EXISTS — no change]
**Name**: `onboarding.show`
**Controller**: `Client\OnboardingController@show`
**Middleware**: `guest`
**Response**: Renders `client.onboarding.form` with `$visaTypes` collection
**Auth behaviour**: Authenticated users redirected to `/dashboard` by guest middleware

---

### POST /apply — Submit Apply Form
**Status**: [EXISTS — no change]
**Name**: `onboarding.store`
**Controller**: `Client\OnboardingController@store`
**Middleware**: `guest`
**Request**: `Client\OnboardingRequest` (validated)
**On success**: Creates User + VisaApplication + auto-login → redirect to `client.dashboard` with success flash
**On validation failure**: Redirect back with errors and old input preserved

**Apply form fields (all required unless noted)**:

| Field | Type | Validation |
|---|---|---|
| full_name | string | required, max:255 |
| email | string | required, email, unique:users (custom error message) |
| password | string | required, confirmed, min:8, mixed case, numbers |
| password_confirmation | string | required |
| phone | string | required, max:30 |
| nationality | string | required, max:100 |
| country_of_residence | string | required, max:100 |
| visa_type_id | integer | required, exists:visa_types,id |
| adults_count | integer | required, min:1, max:20 |
| children_count | integer | required, min:0, max:20 |
| application_start_date | date | required, after_or_equal:today |
| job_title | string | required, max:150 |
| employment_type | enum | required, in:employed,self_employed,unemployed,student |
| monthly_income | numeric | required, min:0 |
| notes | text | nullable, max:2000 |
| agreed_to_terms | checkbox | required, accepted |

**Duplicate email behaviour** [CHANGE]:
- Validation error key: `email`
- Error message: "An account with this email already exists."
- View response: Login link below form receives visible highlight class

---

## Auth Routes

### GET /login — Login Page
**Status**: [EXISTS — partial change]
**Name**: `login`
**Controller**: `Auth\AuthenticatedSessionController@create`
**Middleware**: `guest`
**Change**: Add "Don't have an account? Apply now" text link below the form, linking to `route('onboarding.show')`

---

### POST /login — Process Login
**Status**: [EXISTS — no change]
**Name**: `login` (POST)
**Controller**: `Auth\AuthenticatedSessionController@store`
**Middleware**: `guest`
**Rate limit**: 5 attempts per email+IP combination; temporary lockout on breach
**On failure**: Non-specific error ("These credentials do not match our records.")
**On success**: Redirect to intended route or dashboard

---

### POST /logout — Logout
**Status**: [EXISTS — no change]
**Name**: `logout`
**Controller**: `Auth\AuthenticatedSessionController@destroy`
**Middleware**: `auth`

---

## Dashboard Redirect Routes

| Route | Middleware | Target |
|---|---|---|
| GET /dashboard | auth, verified | `DashboardController@index` (role-aware redirect) |
| GET /client/dashboard | auth, verified, active | `Client\DashboardController@show` |
| GET /admin/dashboard | auth, verified, can:dashboard.admin | `Admin\DashboardController@index` |
| GET /reviewer/dashboard | auth, verified, can:tasks.view | `Reviewer\DashboardController@show` |

These routes are **[EXISTS — no change]**. Referenced here as the redirect targets after login and registration.

---

## Navigation Toggle Summary

| Page | Toggle Link Text | Target Route |
|---|---|---|
| `/apply` (onboarding form) | "Already have an account? Login" | `route('login')` |
| `/login` | "Don't have an account? Apply now" | `route('onboarding.show')` |
