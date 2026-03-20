# Quickstart: Phase 2 — Client Onboarding

**Purpose**: Manual validation scenarios to run after implementation. Each scenario should produce the stated outcome.

---

## Prerequisites

1. Phase 1 is complete and migrations have run (`php artisan migrate`).
2. Visa types are seeded: `php artisan db:seed --class=VisaTypeSeeder`
3. Dev server is running: `php artisan serve` + `npm run dev`
4. A clean browser session (no prior login).

---

## Scenario 1 — Successful Onboarding (Happy Path)

**Steps**:
1. Visit `http://localhost:8000/apply`
2. Verify the page renders in English with an EN/AR toggle in the header
3. Complete Step 1: enter Full Name, Email, Phone, Nationality, Country of Residence → click "Next"
4. Complete Step 2: select a visa type from the dropdown, enter 2 Adults, 0 Children, a future date → click "Next"
5. Complete Step 3: enter Job Title, select Employment Type, enter Monthly Income, optional Notes → check the "I agree to the Privacy Policy and Terms of Service" checkbox → click "Submit"

**Expected**:
- Browser redirects to `/client/dashboard/overview`
- Dashboard shows the client's full name and a reference number in format `APP-00001`
- The Overview tab is active; all 8 tabs are visible
- `users` table has one new record with role `client`
- `visa_applications` table has one new record with `status = 'pending_review'` and `agreed_to_terms = 1`
- `audit_logs` has an `application_created` event

---

## Scenario 2 — Step Validation Blocks Advancement

**Steps**:
1. Visit `/apply`
2. On Step 1, leave Email blank and click "Next"

**Expected**: Step 1 remains active; a validation indicator appears on the Email field; browser does not advance to Step 2.

---

## Scenario 3 — Duplicate Email Rejected

**Steps**:
1. Visit `/apply`
2. Enter an email that already exists in the `users` table
3. Complete all remaining fields and submit

**Expected**:
- Form resets to Step 1 (page redirect back)
- Validation error displayed near the Email field: "The email has already been taken"
- No new user or application record created

---

## Scenario 4 — Consent Checkbox Required

**Steps**:
1. Complete the wizard through to Step 3
2. Fill all fields but leave the "I agree to the Privacy Policy and Terms of Service" checkbox unchecked
3. Attempt to click "Submit"

**Expected**: Submit button remains disabled (or form refuses to submit) until the checkbox is checked.

---

## Scenario 5 — Authenticated Client Blocked from Re-registering

**Steps**:
1. Log in as the client created in Scenario 1
2. Navigate directly to `http://localhost:8000/apply`

**Expected**: Immediately redirected to `/client/dashboard/overview` — form is not shown.

---

## Scenario 6 — Dashboard Tab Navigation

**Steps**:
1. Log in as any onboarded client
2. Visit `/client/dashboard`
3. Click each of the 8 tabs: Overview, Documents, Tasks, Payments, Timeline, Messages, Profile, Support

**Expected**:
- Each tab becomes visually active on click
- Each tab section renders its content or a clear empty-state message
- No broken layout or PHP error on any tab

---

## Scenario 7 — Arabic Locale Onboarding

**Steps**:
1. Visit `/apply` (no login)
2. Click the "AR" toggle in the header
3. Observe the form

**Expected**:
- All field labels, placeholders, and buttons render in Arabic
- The page layout switches to right-to-left
- Switching back to "EN" restores English LTR layout
- The locale toggle persists if you navigate between the page and `/apply` again

---

## Scenario 8 — Visa Type Added Without Code Deploy

**Steps**:
1. Insert a new row directly into `visa_types`: `INSERT INTO visa_types (name, is_active) VALUES ('Digital Nomad Visa', 1)`
2. Visit `/apply` and open the visa type dropdown on Step 2

**Expected**: "Digital Nomad Visa" appears in the list without any code change or cache clear.

---

## Database Spot-Checks (via `php artisan tinker`)

```php
// Confirm reference number format
App\Models\VisaApplication::first()->reference_number; // → "APP-00001"

// Confirm role assignment
App\Models\User::latest()->first()->getRoleNames(); // → ["client"]

// Confirm status
App\Models\VisaApplication::first()->status; // → "pending_review"

// Confirm audit log
DB::table('audit_logs')->where('event', 'application_created')->first();
```
