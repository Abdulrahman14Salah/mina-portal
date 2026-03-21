# Feature Specification: Authentication & Application Entry

**Feature Branch**: `008-auth-application-entry`
**Created**: 2026-03-20
**Status**: Draft
**Input**: User description: "Phase 1 — Authentication & Application Entry (from UPDATE.md)"

## Clarifications

### Session 2026-03-20

- Q: What fields does the apply form collect? → A: Full application form — all visa details collected upfront before account creation.
- Q: What happens immediately after successful apply form submission? → A: User is automatically logged in and redirected to their client dashboard — no separate login step required.
- Q: What happens when a visitor submits the apply form with an email that already has an account? → A: Validation error displayed ("An account with this email already exists") with the login link highlighted — no redirect, no silent behaviour.
- Q: Should repeated failed login attempts be rate-limited? → A: Yes — throttle after 5 failed attempts within a time window with a temporary lockout (no admin intervention required to unlock).
- Q: What are the password strength requirements? → A: Minimum 8 characters, at least one uppercase letter, at least one number.

## Overview

Phase 1 establishes the primary entry point and authentication foundation for the client portal. New visitors land directly on an application form, while existing users can toggle to login. All new applicants are automatically assigned the "Client" role upon registration, and authenticated users are prevented from accessing entry pages they no longer need.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - New Applicant Submits Application (Priority: P1)

A prospective client visits the portal for the first time. They are immediately presented with the application form — no redirect, no homepage — and can begin their visa application without any prior account setup.

**Why this priority**: This is the primary conversion point for new clients. If a visitor cannot immediately apply, the portal has failed its core purpose.

**Independent Test**: Can be fully tested by visiting the root URL and verifying the apply form renders and accepts a new submission — delivers a working new-client entry flow.

**Acceptance Scenarios**:

1. **Given** a visitor with no account navigates to the root URL, **When** the page loads, **Then** the apply form is displayed without any redirect.
2. **Given** a visitor navigates to `/apply`, **When** the page loads, **Then** the identical apply form is displayed as at the root URL.
3. **Given** a visitor completes and submits the apply form, **When** the form is processed, **Then** a new account is created, the user is assigned the "Client" role automatically, and the user is immediately logged in and redirected to their client dashboard — no separate login step required.
4. **Given** a visitor submits the apply form with invalid or missing required fields, **When** validation runs, **Then** clear error messages appear next to each invalid field without losing previously entered data.

---

### User Story 2 - Returning Client Logs In (Priority: P2)

A client who already has an account arrives at the portal and needs to sign in to check their application status. From the apply page they can navigate directly to the login form via a clearly visible link.

**Why this priority**: Returning users must be able to access their existing applications without friction, but the system's primary audience is new applicants.

**Independent Test**: Can be fully tested by clicking "Already have an account? Login" from the apply page and completing a successful login — delivers a working returning-client entry flow.

**Acceptance Scenarios**:

1. **Given** a visitor is on the apply page, **When** they click "Already have an account? Login", **Then** they are taken to the login page.
2. **Given** a visitor is on the login page, **When** they click "Don't have an account? Apply now", **Then** they are taken to the apply page.
3. **Given** a visitor submits valid credentials on the login page, **When** authentication succeeds, **Then** they are redirected to their dashboard.
4. **Given** a visitor submits invalid credentials, **When** authentication fails, **Then** a non-specific error message is shown and the form remains available for retry.

---

### User Story 3 - Authenticated User Prevented from Re-entering (Priority: P3)

A user who is already signed in attempts to navigate to the apply form or the login page. The system redirects them to their dashboard rather than showing irrelevant entry pages.

**Why this priority**: Prevents confusion and duplicate applications. Important for a clean experience but not blocking for core functionality.

**Independent Test**: Can be fully tested by logging in, then manually navigating to `/apply` or `/login`, and verifying the redirect to dashboard — delivers a guard against session-state confusion.

**Acceptance Scenarios**:

1. **Given** a logged-in user navigates to `/login`, **When** the page is requested, **Then** they are redirected to their dashboard.
2. **Given** a logged-in user navigates to `/apply`, **When** the page is requested, **Then** they are redirected to their dashboard.
3. **Given** a logged-in user navigates to `/`, **When** the page is requested, **Then** they are redirected to their dashboard.

---

### User Story 4 - Admin Account Auto-Provisioned at Setup (Priority: P4)

When a system administrator initializes or resets the database, an admin account is automatically created using credentials stored in the environment configuration. This ensures the portal is immediately accessible for administration after setup without manual steps.

**Why this priority**: Operational necessity for deployment and reset scenarios, but does not affect the end-user application flow.

**Independent Test**: Can be fully tested by running the database seed process and verifying an admin user exists with the "Admin" role using the environment-defined credentials — delivers a reliably bootstrapped admin account.

**Acceptance Scenarios**:

1. **Given** the database seed process is run, **When** it completes, **Then** an admin user exists with credentials sourced from environment configuration and the "Admin" role assigned.
2. **Given** an admin user already exists with the same identifier, **When** the seed runs again, **Then** the existing record is updated rather than duplicated.
3. **Given** the admin account has been created, **When** the admin logs in with environment-defined credentials, **Then** they access the system with administrative privileges.

---

### Edge Cases

- When a visitor submits the apply form with an email that already has an account, a field-level validation error is displayed ("An account with this email already exists") and the login link below the form is visually highlighted to guide the user — no redirect or silent failure occurs.
- How does the system handle a session that expires mid-form — is submitted data preserved or lost?
- What happens if the environment configuration is missing admin credentials when the seed process runs?
- What if role assignment fails after account creation — can the user still log in and what access do they receive?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The root URL (`/`) MUST render the application form as the default page for unauthenticated visitors.
- **FR-002**: The `/apply` route MUST render the same application form as the root URL — both routes display identical content.
- **FR-003**: New accounts created via the apply form MUST be automatically assigned the "Client" role with no manual intervention, and the user MUST be immediately logged in and redirected to their client dashboard upon successful submission — no separate login step is required.
- **FR-004**: The apply page MUST display a link to the login page ("Already have an account? Login"), positioned below the form and styled as a text link.
- **FR-005**: The login page MUST display a link to the apply page ("Don't have an account? Apply now"), positioned below the form and styled as a text link.
- **FR-006**: Authenticated users MUST be redirected to their dashboard when they attempt to access `/`, `/apply`, or `/login`.
- **FR-007**: The database seed process MUST create or update an admin user account using credentials defined in the environment configuration.
- **FR-008**: The admin user created during seeding MUST be assigned the "Admin" role.
- **FR-009**: The apply form MUST collect all visa application details in a single submission (full application form), including at minimum: applicant identity fields (name, email, password) and visa-specific fields (e.g. nationality, application type, and other required visa details). All required fields MUST be validated and field-level error messages displayed on failure without clearing previously entered data.
- **FR-013**: Passwords MUST meet the following minimum strength requirements: at least 8 characters, at least one uppercase letter, and at least one number. The form MUST display a hint communicating these rules, and MUST surface a clear validation error if the requirements are not met.
- **FR-011**: When the apply form is submitted with an email address that already has an account, the system MUST display a field-level validation error ("An account with this email already exists") and visually highlight the login link below the form — the form MUST NOT redirect or silently discard the submission.
- **FR-010**: The login form MUST display a non-specific error message on failed authentication to avoid revealing whether an email address exists in the system.
- **FR-012**: The login form MUST throttle repeated failed authentication attempts — after 5 consecutive failures within a time window, further attempts MUST be temporarily blocked. The lockout MUST expire automatically without requiring admin intervention.

### Key Entities

- **Applicant (User)**: A person submitting a visa application. Created via the apply form in a single submission. Key attributes: name, email, password, role, plus all visa-specific fields (nationality, application type, and other required visa details). Automatically assigned "Client" role on creation.
- **Administrator (User)**: A privileged system user. Key attributes: name, email, password, role. Assigned "Admin" role. Provisioned via environment-driven seed process.
- **Role**: A classification that determines access level within the portal. Two roles relevant to this phase: "Client" and "Admin".
- **Session**: Represents an authenticated user's active login state. Determines redirect behaviour on entry page access.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of unauthenticated visitors to the root URL see the apply form with no redirect delay.
- **SC-002**: New applicants complete registration, receive role assignment, and land on their client dashboard in a single form submission — zero additional manual steps (including login) required.
- **SC-003**: The navigation toggle between apply and login is present and functional on both pages in 100% of cases.
- **SC-004**: Authenticated users are redirected away from entry pages in 100% of attempted accesses — no logged-in user can view the apply or login forms.
- **SC-005**: The admin seeding process produces a valid, role-assigned admin account on every run, including repeat runs, with no duplicate records created.
- **SC-006**: Apply form validation prevents submission of incomplete data and surfaces field-level errors without data loss in 100% of invalid submission attempts.
- **SC-007**: The login form blocks further attempts after 5 consecutive failures within a time window — brute force credential attacks are throttled automatically without manual admin action.

## Assumptions

- The "Client" role is the default and only role assigned to new applicants at registration. Reviewer and Admin roles are assigned through separate administrative processes.
- The portal does not support social login or single sign-on in this phase — only email and password authentication.
- The apply form and the registration form are the same form: submitting it creates an account and initiates the application. The form collects all visa-related details upfront in a single submission (full application form), not a minimal sign-up followed by a later data-collection step.
- Admin credentials (name, email, password) are stored as environment variables accessible to the seed process.
- Routing to the correct role-specific dashboard after login is handled outside this phase's scope.
- Email uniqueness is enforced at the system level — attempting to register with an existing email surfaces a validation error on the apply form.
