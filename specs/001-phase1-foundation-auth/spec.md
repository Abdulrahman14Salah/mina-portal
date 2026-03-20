# Feature Specification: Phase 1 — Foundation & Architecture

**Feature Branch**: `001-phase1-foundation-auth`
**Created**: 2026-03-19
**Status**: Draft
**Input**: Phase 1 of a visa management portal — authentication system, role-based access control, and user model with Admin, Client, and Reviewer roles.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — New User Registration (Priority: P1)

A prospective client visits the portal and creates an account by registering with their email and password. Upon successful registration, they are logged in and directed to the appropriate area for their role.

**Why this priority**: Without a working registration and login flow, no other part of the system can be used. This is the entry point for all users.

**Independent Test**: Can be fully tested by visiting the registration page, submitting valid details, and verifying the user is logged in and lands on the correct page for their role.

**Acceptance Scenarios**:

1. **Given** a visitor on the registration page, **When** they submit a valid email, password, and required details, **Then** an account is created, they are logged in, and redirected to their role-appropriate landing page (`/admin/dashboard` for Admin, `/client/dashboard` for Client, `/reviewer/dashboard` for Reviewer).
2. **Given** a visitor on the registration page, **When** they submit an email that already exists, **Then** registration fails with a clear error message and no duplicate account is created.
3. **Given** a visitor on the registration page, **When** they submit an invalid or incomplete form, **Then** validation errors are displayed inline and no account is created.

---

### User Story 2 — User Login (Priority: P1)

A registered user logs in using their email and password. They are granted access to the portal and directed to their role-specific dashboard.

**Why this priority**: Login is required for all subsequent portal interactions. It must work correctly before any role-gated content is accessible.

**Independent Test**: Can be tested by logging in with valid credentials and verifying redirection to the correct dashboard, and by attempting login with wrong credentials and verifying rejection.

**Acceptance Scenarios**:

1. **Given** a registered user on the login page, **When** they submit correct credentials, **Then** they are authenticated and redirected to their role's landing page (`/admin/dashboard`, `/client/dashboard`, or `/reviewer/dashboard`).
2. **Given** a registered user on the login page, **When** they submit an incorrect password, **Then** login is rejected with a generic error message (no account enumeration).
3. **Given** an authenticated user, **When** they log out, **Then** their session is terminated and they are redirected to the login page.
4. **Given** an unauthenticated user attempting to access a protected page, **When** the page loads, **Then** they are redirected to the login page.

---

### User Story 3 — Role-Based Access Control (Priority: P2)

Each user is assigned exactly one role (Admin, Client, or Reviewer). Their role determines which pages and actions they can access. Attempts to access unauthorized areas are blocked.

**Why this priority**: Role enforcement is foundational to system security. Without it, any user could access any part of the portal.

**Independent Test**: Can be tested by logging in as each role type and verifying that permitted pages are accessible and restricted pages return an appropriate denial response.

**Acceptance Scenarios**:

1. **Given** a logged-in Admin user, **When** they navigate to the admin panel, **Then** access is granted.
2. **Given** a logged-in Client user, **When** they attempt to access the admin panel, **Then** access is denied and they are shown an "unauthorized" message or redirected.
3. **Given** a logged-in Reviewer user, **When** they attempt to access client-only areas, **Then** access is denied appropriately.
4. **Given** a logged-in Admin, **When** they assign a role to a user, **Then** that user's permissions update immediately on their next action.

---

### User Story 4 — Admin User Management (Priority: P3)

An administrator can view, create, edit, and deactivate user accounts from within the admin panel. They can assign or change the role of any user.

**Why this priority**: Admins need to manage the user base — especially for onboarding Reviewers and managing Client accounts — but this is not required for the core auth flow to function.

**Independent Test**: Can be tested by an admin creating a new user with a specified role and confirming that user can log in with the assigned role's permissions.

**Acceptance Scenarios**:

1. **Given** a logged-in Admin, **When** they create a new user and assign a role, **Then** the user account is saved and the user can log in with the assigned role.
2. **Given** a logged-in Admin, **When** they deactivate a user account, **Then** that user can no longer log in.
3. **Given** a logged-in Admin, **When** they change a user's role, **Then** the user's access rights change to reflect the new role on their next session.

---

### Edge Cases

- What happens when a user's session expires mid-action? They should be redirected to login without losing their intended destination (post-login redirect).
- What happens if an admin attempts to deactivate their own account? The system should prevent self-deactivation to avoid lockout.
- What happens if a user with a deactivated account attempts to log in? Login is denied with a clear message (e.g., "Account disabled — contact support").
- What if the same email is used for registration simultaneously from two browsers? Only one account should be created; the second should receive a duplicate email error.
- What if a user has no role assigned? Access to all protected areas is denied until a role is assigned by an Admin.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow any visitor to register a new account using a unique email address and password.
- **FR-002**: System MUST authenticate registered users via email and password on login.
- **FR-003**: System MUST assign exactly one role to each user account: Admin, Client, or Reviewer.
- **FR-004**: System MUST enforce role-based access control on all protected pages and actions.
- **FR-005**: System MUST redirect unauthenticated users attempting to access protected pages to the login page.
- **FR-006**: System MUST deny access to users who attempt to access areas outside their role's permissions, displaying an appropriate message.
- **FR-007**: System MUST allow users to log out, terminating their session immediately.
- **FR-008**: System MUST prevent login for deactivated user accounts with a user-facing message.
- **FR-009**: Admins MUST be able to create, view, edit, and deactivate user accounts.
- **FR-010**: Admins MUST be able to assign or change a user's role.
- **FR-011**: System MUST validate registration input: email format, password strength (minimum 8 characters, at least one uppercase letter, one lowercase letter, and one number), and required fields.
- **FR-012**: System MUST prevent account enumeration — login error messages must not reveal whether an email exists in the system.
- **FR-013**: System MUST log all authentication events (login, logout, failed attempts) for audit purposes.
- **FR-014**: System MUST prevent an admin from deactivating their own account.
- **FR-015**: Users MUST be able to request a password reset via their registered email address.

### Key Entities

- **User**: Represents any person with a portal account. Has an email, hashed password, role, active/inactive status, and timestamps for creation and last login.
- **Role**: One of three fixed values — Admin, Client, or Reviewer. Determines what a user can access and do within the portal.
- **Session**: A time-limited authenticated state established at login and destroyed at logout or expiry. Ties an active request to a User.
- **Audit Log Entry**: A record of a security-relevant event (login, logout, failed attempt, role change) associated with a User and a timestamp.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A new user can complete registration and arrive at their role-appropriate landing page in under 60 seconds.
- **SC-002**: A registered user can log in and reach their dashboard in under 10 seconds under normal conditions.
- **SC-003**: 100% of protected pages enforce role-based access — no page is accessible to an unauthorized role.
- **SC-004**: Zero cases of account enumeration — login responses are indistinguishable for an unknown email versus a wrong password.
- **SC-005**: An admin can create a new user account and assign a role in under 2 minutes.
- **SC-006**: All authentication events (login, logout, failed attempts) are captured in the audit log with no gaps.
- **SC-007**: A deactivated user is unable to log in regardless of whether they provide correct credentials.

---

## Assumptions

- The three roles (Admin, Client, Reviewer) are fixed for Phase 1. Custom or additional roles are out of scope.
- Role assignment at self-registration defaults to "Client." Admin and Reviewer roles are assigned by an Admin only.
- Password reset ("forgot password") functionality is included as it is essential for a production auth system.
- The portal is a web-based application accessible via modern browsers; native mobile apps are out of scope for Phase 1.
- A single Admin account is seeded into the system at setup (a "super admin" seed) to bootstrap user management.
- Session expiry follows standard security practices with an idle timeout; exact duration is a configuration detail.

---

## Out of Scope

- Social login (Google, GitHub, etc.) — not required for Phase 1.
- Two-factor authentication (2FA) — may be considered in a later phase.
- Granular permissions within a role (e.g., sub-permissions for Admins) — roles are coarse-grained for Phase 1.
- Client onboarding form and dashboard — covered in Phase 2.

---

## Clarifications

### Session 2026-03-19

- Q: What should each role land on immediately after login? → A: Role-specific dashboards at named routes — `/admin/dashboard` for Admin, `/client/dashboard` for Client, `/reviewer/dashboard` for Reviewer.
