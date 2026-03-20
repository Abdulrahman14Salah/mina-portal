# Feature Specification: Admin Panel Foundation & Architecture

**Feature Branch**: `006-admin-panel-foundation`
**Created**: 2026-03-20
**Status**: Draft
**Input**: User description: "Read PLAN.md and create a specification for the phase 6: Foundation and Architecture page ONLY."

## Clarifications

### Session 2026-03-20

- Q: Should significant admin actions be recorded in the existing audit log system? → A: Yes — all significant admin actions must be recorded (e.g., visa type created, reviewer assigned, client deactivated).
- Q: When a dashboard summary count query fails, does the whole page error or do cards fail independently? → A: Each card fails independently with an inline "Unable to load" state; other cards and the recent applications list remain functional.
- Q: Does admin list search filter in real-time or on explicit submission? → A: On explicit submission — Enter key or search button press triggers a server request; no real-time keystroke filtering.
- Q: Do destructive row-level admin actions require confirmation before executing? → A: Yes — destructive/irreversible actions (deactivate, delete, status change) must prompt for confirmation before executing.
- Q: What is the default sort order for all admin list views? → A: Most recently created/submitted first — descending by creation date, applied consistently across all admin list views.

---

## Context

The admin panel is the operational control center of the visa portal. Phases 1–5 established client-facing flows (onboarding, workflows, documents, payments) and a minimal admin surface (application list, document upload, payment management). Phase 6 builds the full administrative interface that lets admins manage visa types, clients, task templates, and reviewer assignments from a single, unified panel.

This specification covers the **foundational layer only**: the admin panel's navigation structure, dashboard home page, role-gated access model, and shared UI conventions that every Phase 6 sub-feature inherits. The four management sections (Visa Management, Client Management, Task Builder, Reviewer Assignment) are separate specifications that depend on this foundation being in place.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Admin Navigates the Panel (Priority: P1)

A logged-in admin arrives at the admin area and sees a persistent navigation menu listing all major admin sections. They can move between sections without returning to a home page, and the currently active section is visually highlighted.

**Why this priority**: Every Phase 6 feature depends on the navigation being in place. Without it, admins have no consistent way to reach any management section.

**Independent Test**: An admin can log in, land on the admin dashboard, and click through to each major section (Applications, Visa Types, Clients, Task Builder, Reviewers, Users) via the navigation — even if those destination pages are placeholder stubs.

**Acceptance Scenarios**:

1. **Given** an admin is logged in, **When** they visit the admin area, **Then** they see a navigation menu with links to: Dashboard, Applications, Visa Types, Clients, Task Builder, Reviewers, Users.
2. **Given** an admin is on any admin page, **When** they click a navigation link, **Then** they are taken to the destination without losing page context.
3. **Given** an admin is on the "Clients" section, **When** they look at the navigation, **Then** the "Clients" link is visually active/highlighted and all other links are not.
4. **Given** an admin is on any admin page, **When** they look at the breadcrumb trail, **Then** the breadcrumb reflects their current location within the panel hierarchy (e.g., Admin → Clients → View Client).

---

### User Story 2 — Non-Admins Are Blocked (Priority: P1)

Any user who does not hold the admin role attempting to access any admin panel URL is denied access with a clear, appropriate response — not a confusing blank page or server error.

**Why this priority**: Security is non-negotiable. The admin panel must not be accessible to clients or reviewers regardless of URL knowledge.

**Independent Test**: A client or reviewer user navigating directly to any `/admin/*` URL receives a 403 Forbidden response — never a 404 or 500.

**Acceptance Scenarios**:

1. **Given** a client user is authenticated, **When** they navigate directly to any `/admin/*` URL, **Then** they receive a 403 Forbidden response with a user-friendly message.
2. **Given** a reviewer user is authenticated, **When** they navigate directly to any `/admin/*` URL, **Then** they receive a 403 Forbidden response with a user-friendly message.
3. **Given** an unauthenticated visitor, **When** they navigate to any `/admin/*` URL, **Then** they are redirected to the login page, and after login they are redirected to their intended URL.
4. **Given** an authenticated admin, **When** they navigate to any `/admin/*` URL, **Then** they can access it without any access errors.

---

### User Story 3 — Admin Dashboard Home Page (Priority: P2)

When an admin first enters the admin panel, they land on a dashboard home page that gives an at-a-glance summary of the system's current state — pending applications, recent activity, and quick-access shortcuts to the most common tasks.

**Why this priority**: The dashboard home is the entry point for every admin session. A useful home page reduces time spent hunting for what needs attention.

**Independent Test**: An admin landing on the dashboard home sees summary counts (pending applications, total clients) and can click through to a filtered list view — all without navigating elsewhere.

**Acceptance Scenarios**:

1. **Given** an admin loads the dashboard home page, **When** the page renders, **Then** they see summary counts for: total active applications, applications pending review, and total registered clients.
2. **Given** an admin loads the dashboard home page, **When** the page renders, **Then** they see the 5 most recently submitted applications with reference numbers, client names, and current statuses.
3. **Given** an admin clicks a summary count card (e.g., "12 Pending Review"), **When** they arrive at the destination, **Then** the applications list is pre-filtered to show only the corresponding records.

---

### User Story 4 — Consistent Admin List UI (Priority: P2)

All admin list views share the same layout conventions: a header with page title and primary action button, a searchable data table with pagination, and row-level action menus. An admin who learns one section can immediately use another.

**Why this priority**: Consistency reduces training time and errors. All Phase 6 sub-features must conform to these patterns, so they must be defined and demonstrated here before sub-features are built.

**Independent Test**: The existing Applications list is refactored to match the shared admin table layout and can serve as the reference implementation for all subsequent Phase 6 list views.

**Acceptance Scenarios**:

1. **Given** an admin views any admin list page, **When** the page loads, **Then** the layout consistently includes: a page title, an optional primary action button, a search input, and a data table with column headers.
2. **Given** an admin list has more than 15 records, **When** the page loads, **Then** pagination controls are visible showing the current page number and total record count.
3. **Given** an admin types in the search input on any list page, **When** they submit or type, **Then** the table filters to matching records and the visible count updates.
4. **Given** an admin views any row in an admin list, **When** they look at the row, **Then** relevant row-level actions (View, Edit, Deactivate, etc.) are accessible without leaving the list.

---

### Edge Cases

- What happens when an admin account is deactivated mid-session — are they immediately locked out on the next request?
- ~~What happens when a dashboard summary count query fails?~~ Resolved: each card fails independently with an inline "Unable to load" state (see FR-008a).
- What happens when a search returns zero results — does the table show an empty state message or just an empty table?
- How does the admin navigation behave on tablet-width screens (768px) — does it collapse into a menu?
- What happens if an admin bookmarks a deep admin URL and their session expires — do they return to that URL after re-authentication?

---

## Requirements *(mandatory)*

### Functional Requirements

**Navigation**

- **FR-001**: The admin panel MUST display a persistent sidebar or top navigation visible on every admin page, containing links to: Dashboard, Applications, Visa Types, Clients, Task Builder, Reviewers, Users.
- **FR-002**: The navigation MUST visually distinguish the currently active section from inactive links.
- **FR-003**: Every admin page MUST display a breadcrumb trail showing the user's location within the panel hierarchy.
- **FR-004**: The admin navigation MUST remain functional on screen widths down to 768px, collapsing or adapting as needed.

**Access Control**

- **FR-005**: All routes under the `/admin/*` path MUST require the `admin` role. Any other authenticated role MUST receive HTTP 403.
- **FR-006**: An unauthenticated request to any `/admin/*` URL MUST redirect to the login page, preserving the intended destination URL for redirect after login.
- **FR-007**: Access denial MUST return a user-friendly error page — never a raw server error or blank page.

**Dashboard Home Page**

- **FR-008**: The admin dashboard home MUST display current counts for: total active visa applications, applications in `pending_review` status, and total registered client accounts.
- **FR-008a**: Each summary count card MUST load and fail independently — if one card's data cannot be retrieved, it MUST display an inline "Unable to load" message without affecting other cards or the recent applications list.
- **FR-009**: The dashboard MUST display the 5 most recently submitted applications with: reference number, client full name, visa type name, and current status.
- **FR-010**: Each recent application entry MUST link directly to that application's detail page.
- **FR-011**: Each summary count card MUST link to the relevant list view, pre-filtered to show only the counted records.

**Shared Admin List UI Pattern**

- **FR-012**: All admin list pages MUST follow a shared layout: page heading, optional primary action button (top-right), search input, sortable data table, and pagination controls.
- **FR-012a**: All admin list views MUST default to sorting by creation date descending (most recently created/submitted first). This default applies consistently across all resource types unless the admin explicitly changes the sort column.
- **FR-013**: Admin data tables MUST paginate at 15 records per page by default.
- **FR-014**: Admin data tables MUST support text search triggered by Enter key or an explicit search button press. Search queries are sent to the server; results replace the current table contents without a full page reload where possible. Real-time keystroke filtering is not required.
- **FR-015**: Each row in an admin data table MUST expose applicable row-level actions (View, Edit, Deactivate, etc.) accessible without leaving the list page.
- **FR-015a**: Destructive or irreversible row-level actions (deactivate, delete, status change) MUST present a confirmation prompt before executing. Non-destructive actions (View, Edit) execute immediately without confirmation.
- **FR-016**: Admin list tables MUST display an empty-state message when no records match the current search or filters.

**Audit Logging**

- **FR-019**: The admin panel MUST record significant admin actions in the existing audit log system. Actions that MUST be logged include: creating or modifying a visa type, assigning or removing a reviewer from an application, deactivating a client account, and any status change made by an admin on a client's application.
- **FR-020**: Each audit log entry for an admin action MUST capture: the acting admin's identity, the action performed, the affected record's identifier, and a timestamp.
- **FR-021**: Audit log entries for admin actions MUST be readable by admins via the existing audit log interface (no new admin UI for log viewing is required in this phase).

**Routing & Extensibility**

- **FR-017**: All admin routes MUST use the URL prefix `/admin/` and named route prefix `admin.` consistently.
- **FR-018**: Adding a new admin management section MUST require only: a new controller, a new route group entry, and new views — with no changes to the navigation template or middleware configuration.

### Key Entities

- **Admin Navigation Item**: A labeled link with an associated route and active-state rule. Navigation items are defined in a single shared location (not repeated in each view) so adding a new section requires one edit.
- **Dashboard Summary Card**: A self-contained widget on the dashboard home page displaying a count, a label, and a link to the filtered list view. Each card loads and fails independently.
- **Admin List View Pattern**: A reusable table layout consumed by all Phase 6 management sections. Accepts: page title, column definitions, data collection, search binding, pagination settings, and row actions.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An admin can navigate from the dashboard home to any admin section in 1 click and return to the dashboard in 1 click, with no more than 1 page load each way.
- **SC-002**: 100% of `/admin/*` URLs return HTTP 403 (not 404 or 500) when accessed by an authenticated non-admin user — verifiable by automated test.
- **SC-003**: The admin dashboard home page fully renders all summary counts within 2 seconds under normal operating conditions.
- **SC-004**: A new admin list section built following the shared pattern requires zero changes to the navigation template — only a single route entry addition.
- **SC-005**: Any two admin list pages (e.g., Applications list vs. Users list) are visually consistent enough that an observer unfamiliar with the system identifies them as the same design system without prompting.
- **SC-006**: After session expiry, an admin who re-authenticates is redirected to the page they originally tried to access.

---

## Assumptions

- The `admin` role already exists in the system (Phase 1). No new roles are introduced by this foundation spec.
- The existing `/admin/applications` list and `/admin/users` views will be refactored to conform to the shared admin list pattern (FR-012 through FR-016). This refactoring is in-scope.
- Navigation entries for "Task Builder" and "Reviewer Assignment" will be present in the nav from day one, but may link to placeholder pages until their Phase 6 sub-specs are implemented.
- The admin panel uses a distinct visual shell (layout template) separate from the client dashboard. They share the same authentication system but not the same layout.
- Existing permissions (`dashboard.admin`) are sufficient for admin panel access control. No new permissions are required at the foundation layer.
- The admin panel is English-only for Phase 6. Arabic RTL support for the admin panel is deferred to Phase 9 (Multi-language).
- Summary counts on the dashboard home page are not real-time (no WebSocket/polling). They reflect database state at the time the page is loaded.

---

## Dependencies

- **Phase 1** (Auth & Roles): `admin` role and permission system must be in place. ✅ Complete.
- **Phase 2** (Client Onboarding): `VisaApplication` and `VisaType` models must exist for dashboard summary counts. ✅ Complete.
- **Phase 3** (Workflow System): Task data referenced in application detail views. ✅ Complete.
- **Phase 4** (Document System): Document data referenced in application detail views. ✅ Complete.
- **Phase 5** (Payment System): Payment data referenced in application detail views. ✅ Complete.
- **Phase 6 sub-features** (Visa Management, Client Management, Task Builder, Reviewer Assignment): All depend on this foundation spec being implemented first.
