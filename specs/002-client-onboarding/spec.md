# Feature Specification: Phase 2 — Client Onboarding

**Feature Branch**: `002-client-onboarding`
**Created**: 2026-03-19
**Status**: Draft
**Input**: Phase 2 — Client Onboarding: client fills registration form, account is created automatically, and client is redirected to a role-specific dashboard with 8 tabs covering their visa application journey.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Client Submits Application Form (Priority: P1)

A prospective visa client visits a dedicated public application page and completes a comprehensive registration form with their personal details and visa requirements. On successful submission they immediately have access to their client portal — no separate login step required.

**Why this priority**: This is the sole entry point for every client into the system. Without a working onboarding form, no client can begin a visa application or access any client-facing feature.

**Independent Test**: Visit the application form page as a guest, fill in all required fields with valid data, submit, and verify that a client account and a visa application record are both created, the client is logged in automatically with the Client role, and they are redirected to their dashboard.

**Acceptance Scenarios**:

1. **Given** a prospective client on the application form page, **When** they submit a fully valid form, **Then** a client account is created, a visa application record is saved with a system-generated reference number, the client is logged in with the Client role, and they are redirected to their dashboard.
2. **Given** a prospective client filling the form, **When** they submit an email address that already exists in the system, **Then** the form is rejected with a clear error message and no duplicate account or application record is created.
3. **Given** a prospective client filling the form, **When** they submit with one or more required fields missing or invalid, **Then** validation errors appear alongside each problem field and no records are created.
4. **Given** a prospective client filling the form, **When** they select a visa type from the available list, **Then** the selected visa type is linked to their application record.
5. **Given** an already-logged-in client who has completed onboarding, **When** they attempt to access the application form page, **Then** they are redirected to their dashboard — resubmission is blocked.

---

### User Story 2 — Client Dashboard Access (Priority: P2)

After completing onboarding, a logged-in client can access their personal dashboard. The dashboard presents their visa application journey across 8 clearly organized sections covering all aspects of their application. Each section shows an appropriate placeholder when no content exists yet.

**Why this priority**: The dashboard is the central hub for all future client interactions (documents, payments, task progress). It must exist as a working, navigable foundation before Phase 3+ features are layered on top.

**Independent Test**: Log in as a client who has completed onboarding, navigate to the client dashboard, and verify all 8 tabs are visible and navigable, the client's name and application reference number are displayed, and each empty tab shows a placeholder message rather than a broken UI.

**Acceptance Scenarios**:

1. **Given** a logged-in client, **When** they navigate to the client dashboard, **Then** they see their full name, their application reference number, and exactly 8 navigable tabs.
2. **Given** a logged-in client on the dashboard, **When** they click a tab, **Then** the selected tab becomes visually active and its corresponding section is displayed.
3. **Given** a logged-in client navigating to a tab with no content yet, **When** the section loads, **Then** a clear empty-state message is shown — no broken layout or blank screen.
4. **Given** an unauthenticated visitor, **When** they attempt to access the client dashboard URL directly, **Then** they are redirected to the login page.
5. **Given** a logged-in client with no application record on file, **When** they try to access the dashboard, **Then** they are redirected to the application form to complete onboarding.

---

### User Story 3 — Bilingual Form and Dashboard (Priority: P3)

All form labels, error messages, dashboard headings, and tab names support both Arabic and English. When the portal locale is Arabic the layout switches to right-to-left. Clients can complete onboarding and use the dashboard entirely in their preferred language.

**Why this priority**: The target audience is bilingual; Arabic is a primary language for many visa applicants. A form that cannot be read in Arabic creates a direct barrier to client acquisition.

**Independent Test**: Switch the portal locale to Arabic, visit the application form, and verify all labels, placeholders, and error messages appear in Arabic with a right-to-left layout.

**Acceptance Scenarios**:

1. **Given** the portal locale is set to Arabic, **When** a client views the application form, **Then** every field label, placeholder text, validation error, and button label appears in Arabic with a right-to-left layout.
2. **Given** the portal locale is set to English, **When** a client views the dashboard, **Then** all tab names and section headings appear in English with a left-to-right layout.

---

### Edge Cases

- What happens if a client submits the form and immediately refreshes or resubmits? The system must be idempotent — a duplicate submission must not create two accounts or two application records.
- What happens if a client refreshes the browser mid-wizard? Wizard state is not preserved — the client restarts from Step 1 with a blank form. No warning is shown.
- What happens if the visa type the client selected is removed from the catalogue between page load and form submission? The form must reject the submission with a clear message rather than silently creating an orphaned application.
- What happens if a client already has an account and tries to use the onboarding form with a different email? They receive a "this email is already registered" message; no second account is created.
- What happens if the client dashboard is accessed by a client whose application record is missing due to a data integrity issue? The system must not crash — it should redirect to the application form or a support page.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST provide a dedicated application registration form accessible to unauthenticated visitors.
- **FR-002**: The form MUST collect the following fields: full name, email address, phone number, visa type (selected from the active visa catalogue), number of adults, number of children, nationality, country of residence, job title, employment type, monthly income, preferred application start date, and any additional notes.
- **FR-003**: System MUST validate all required fields before processing: email format and uniqueness, required field presence, and numeric values for adult/child counts and monthly income.
- **FR-004**: System MUST create a client user account and a visa application record atomically on successful submission — either both are saved or neither is.
- **FR-005**: Every account created via the onboarding form MUST be assigned the Client role automatically with no admin action required.
- **FR-006**: System MUST log the newly created client in immediately after successful registration — no separate login step required.
- **FR-007**: System MUST redirect the logged-in client to their dashboard immediately after registration completes.
- **FR-008**: Each application record MUST receive a unique, system-generated reference number at the moment of creation. The format MUST be `APP-XXXXX` where `XXXXX` is a zero-padded sequential integer (e.g., `APP-00001`, `APP-00042`). This reference number is displayed on the client dashboard and used in all client-facing communications.
- **FR-008a**: Every newly created application MUST be assigned an initial status of **Pending Review** automatically — no admin or client action is required to set this status.
- **FR-009**: The client dashboard MUST display the client's full name and their application reference number prominently.
- **FR-010**: The client dashboard MUST be organized into exactly 8 navigable tabs in this order: **Overview, Documents, Tasks, Payments, Timeline, Messages, Profile, Support**.
- **FR-011**: Each dashboard tab MUST display a clear empty-state message when no content is yet available for that section.
- **FR-012**: Visa types shown on the registration form MUST be sourced dynamically from the system's visa type catalogue — adding a new visa type must cause it to appear on the form without any code deployment.
- **FR-013**: The application form MUST be presented as a **multi-step wizard** with 3 steps: Step 1 — Personal Information (full name, email, phone, nationality, country of residence); Step 2 — Visa Details (visa type, number of adults, number of children, preferred application start date); Step 3 — Employment & Financial Information (job title, employment type, monthly income, additional notes). The client must not be able to advance to the next step until all required fields in the current step pass validation. Back navigation between steps must be supported.
- **FR-013a**: Step 3 MUST include a mandatory checkbox requiring the client to confirm they have read and agree to the Privacy Policy and Terms of Service before the form can be submitted. The form submission button MUST remain disabled until this checkbox is checked.
- **FR-014**: System MUST block an already-authenticated client from accessing the registration form — they must be redirected to their dashboard instead.
- **FR-015**: All form labels, validation messages, dashboard headings, and tab names MUST be available in both English and Arabic.
- **FR-015a**: A language toggle (EN / AR) MUST be visible in the page header on all public pages, including the application form, and must be accessible without logging in. The selected locale MUST persist for the duration of the browser session.
- **FR-016**: When the portal locale is Arabic, the application form and client dashboard MUST render right-to-left.

### Key Entities

- **Visa Application**: A formal application record created when a client completes onboarding. Holds all information from the registration form, is linked to one client account and one visa type, and carries a unique system-generated reference number. An application is created with an initial status of **Pending Review**, indicating it awaits admin acknowledgment. This status is visible to the client on their dashboard Overview tab.
- **Visa Type**: A configurable record representing a category of visa the portal supports (e.g., tourist, work, family). Clients select one during onboarding. Managed by admins; sourced dynamically at form load time.
- **Client Account**: A user account carrying the Client role. Created automatically when the onboarding form is submitted successfully. Grants access to the client dashboard and all client-facing sections.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A client can complete the registration form and land on their dashboard in under 3 minutes from first page load under normal conditions.
- **SC-002**: 100% of successful form submissions result in exactly one client account and one application record — zero orphaned records, zero duplicates.
- **SC-003**: The client dashboard with all 8 tabs loads and becomes interactive in under 5 seconds.
- **SC-004**: 100% of accounts created via the onboarding form carry the Client role — no account is ever created without a role assignment.
- **SC-005**: Adding a new visa type through the admin catalogue causes it to appear on the registration form without any code deployment.
- **SC-006**: All 13 form fields, all validation messages, and all dashboard navigation elements render correctly and completely in both English and Arabic, with right-to-left layout confirmed for the Arabic locale.

---

## Assumptions

- At least one visa type must exist in the catalogue before the onboarding form is usable. Visa type seeding is a prerequisite handled before Phase 2 goes live.
- The registration form is intended for new, unauthenticated clients only. Clients who already have an account use the standard login page.
- Each client has exactly one active application in Phase 2. Multiple simultaneous applications per client is out of scope.
- The 8-tab dashboard is a foundational layout for Phase 2. Individual tab content (documents, tasks, payments) will be delivered in Phases 3–5; empty-state placeholders are sufficient for Phase 2.
- The application reference number is system-generated and not entered by the client.
- Monthly income is collected for record-keeping purposes; no eligibility threshold or income validation rule is required in Phase 2.
- Email address is the client's login credential — there is no separate username field.

---

## Clarifications

### Session 2026-03-19

- Q: What is the initial status of a Visa Application when created? → A: `Pending Review` — set automatically on creation, visible to the client on their Overview tab.
- Q: Does the form require privacy/consent acceptance before submission? → A: Yes — a single mandatory checkbox on Step 3 confirming agreement to Privacy Policy and Terms of Service; submission is blocked until checked.
- Q: How does an unauthenticated client select their language on the public form page? → A: A visible EN/AR toggle in the page header, persisted to the browser session; no login required.
- Q: What format should the system-generated application reference number follow? → A: `APP-XXXXX` — zero-padded sequential integer with `APP-` prefix (e.g., `APP-00001`).
- Q: Should wizard state be preserved if the client refreshes the browser mid-wizard? → A: No — restart from Step 1 with a blank form; no state persistence, no warning shown.

---

## Out of Scope

- Dashboard tab content implementation (file uploads, task steps, payment flows) — covered in Phases 3–5.
- Admin-side visa type catalogue management — covered in Phase 6.
- Email confirmation or email verification after registration — may be added in a later phase.
- Client ability to edit application details after submission — out of scope for Phase 2.
- Multiple simultaneous applications per client — single active application only.
- Reviewer-facing views of client applications — covered in Phase 7.
