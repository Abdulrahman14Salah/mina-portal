# Feature Specification: Menu Unification — Foundation and Architecture

**Feature Branch**: `011-menu-unification-foundation`
**Created**: 2026-03-22
**Status**: Draft
**Input**: User description: "Read UPDATE-MENU003.md and create a specification for the phase 1: Foundation and Architecture page ONLY."

## Clarifications

### Session 2026-03-22

- Q: Should this phase consolidate separate role-specific layout templates into one, or keep layouts separate and share only the menu component? → A: Keep existing role-specific layout templates; each one imports the same unified menu component.
- Q: Should menu item labels use the existing translation system or be fixed English strings? → A: Use the existing translation system so labels render in the active language (English or Arabic, including RTL).
- Q: Is menu item display order fixed in the central definition or configurable by admins at runtime? → A: Fixed in the central definition; only a developer can change order by editing the config.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Centralized Menu Definition (Priority: P1)

As the development team, we want all navigation items defined in one place so that adding, removing, or changing a menu item requires editing only a single file or configuration, not hunting through multiple role-specific view templates.

**Why this priority**: This is the foundational structural change. Every other story depends on having a single source of truth for navigation items. Without it, the unification has not happened.

**Independent Test**: Can be verified by confirming that no menu items are defined inside Blade layout files — all items come from one central definition. Delivers the core maintainability improvement even before the UI is wired.

**Acceptance Scenarios**:

1. **Given** the application has Admin, Reviewer, and Client navigation items, **When** a developer inspects the codebase, **Then** all menu item definitions (label, target destination, visibility rules) exist in exactly one location.
2. **Given** a new menu item needs to be added for all roles, **When** the developer adds it to the central definition, **Then** it appears for all applicable roles without any other file changes.
3. **Given** an existing menu item needs to be renamed, **When** the developer updates the label in the central definition, **Then** the renamed label appears consistently across all role dashboards.

---

### User Story 2 — Role-Based Menu Visibility (Priority: P1)

As a logged-in user, I see only the navigation items relevant to my role so that I am not confused by links I cannot access and the interface feels purposeful and uncluttered.

**Why this priority**: Equal priority to US1 because the centralized definition is only valuable if it correctly filters per role. Both must work together for the feature to deliver user value.

**Independent Test**: Can be verified by logging in as each role (Admin, Reviewer, Client) and confirming that only that role's designated items are visible, with no items bleeding across roles.

**Acceptance Scenarios**:

1. **Given** a user is logged in as Admin, **When** they view the navigation, **Then** they see Admin-only items and any shared items, but no Reviewer-only or Client-only items.
2. **Given** a user is logged in as Reviewer, **When** they view the navigation, **Then** they see Reviewer-only items and any shared items, but no Admin-only or Client-only items.
3. **Given** a user is logged in as Client, **When** they view the navigation, **Then** they see Client-only items and any shared items, but no Admin-only or Reviewer-only items.
4. **Given** a menu item is marked as shared, **When** any authenticated user views the navigation regardless of role, **Then** that item is always visible.

---

### User Story 3 — Active Navigation State (Priority: P2)

As a logged-in user, I can see which section of the application I am currently in because the corresponding menu item is visually highlighted, making it easy to orient myself.

**Why this priority**: Enhances usability and is straightforward to implement once the unified menu exists, but the system is still functional without it.

**Independent Test**: Can be verified by navigating to each major section and confirming the correct menu item is highlighted, including pages that are sub-routes of a top-level menu item.

**Acceptance Scenarios**:

1. **Given** a user navigates to any page, **When** the navigation renders, **Then** the menu item whose route matches the current page is visually distinguished from others.
2. **Given** a user is on a sub-page of a top-level section (e.g., viewing a specific application detail under the Applications section), **When** the navigation renders, **Then** the parent menu item for that section remains highlighted.
3. **Given** a user is on a page not associated with any menu item, **When** the navigation renders, **Then** no menu item is incorrectly highlighted.

---

### User Story 4 — Role-Aware Dashboard Link (Priority: P2)

As a logged-in user, clicking the "Dashboard" link in the navigation always takes me to my own role-appropriate dashboard, not to a generic page or a forbidden one.

**Why this priority**: A correct dashboard link is a basic usability expectation; without it, users with non-admin roles could land on error or redirect pages.

**Independent Test**: Can be verified by clicking the Dashboard navigation item as each role and confirming the correct dashboard page loads without a redirect or error.

**Acceptance Scenarios**:

1. **Given** a user is logged in as Admin and clicks the Dashboard link, **When** the page loads, **Then** they arrive at the Admin dashboard.
2. **Given** a user is logged in as Reviewer and clicks the Dashboard link, **When** the page loads, **Then** they arrive at the Reviewer dashboard.
3. **Given** a user is logged in as Client and clicks the Dashboard link, **When** the page loads, **Then** they arrive at the Client dashboard.

---

### Edge Cases

- What happens when a user has multiple roles assigned? The menu must display the union of all permitted items without duplication.
- What happens if a menu item points to a route that does not exist in the application? The navigation must not crash; missing routes should be gracefully excluded or flagged during development.
- What happens when a user attempts to access a URL directly that is not in their visible menu? Backend authorization must still block access regardless of menu visibility — the menu is not the security boundary.
- What happens when a user's session expires mid-session? The menu should not expose items based on stale role data after re-authentication.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST define all navigation items (label, destination, visibility scope) in a single centralized location, eliminating duplication across role-specific layout files.
- **FR-002**: The system MUST support four visibility scopes for each menu item: Admin-only, Reviewer-only, Client-only, and Shared (all authenticated roles).
- **FR-003**: The system MUST filter and deliver only the menu items permitted for the currently authenticated user's role when rendering navigation.
- **FR-004**: The system MUST resolve the Dashboard navigation link to the correct role-specific dashboard destination based on the authenticated user's role.
- **FR-005**: The system MUST visually indicate the active menu item based on the current page, including pages that are sub-routes of a top-level section.
- **FR-006**: The system MUST NOT use navigation visibility as a substitute for backend authorization — all existing route protection, policies, and middleware must remain fully enforced.
- **FR-007**: The system MUST NOT hardcode role checks inside Blade view templates; role-based filtering must be handled by a dedicated service or helper outside the view layer.
- **FR-008**: The system MUST retain the existing role-specific layout templates (Admin, Reviewer, Client); each layout imports the same unified menu component rather than merging layouts into one. No currently-working pages may break after the refactor.
- **FR-009**: Users with multiple roles MUST see the union of all menu items permitted for each of their roles, with no duplicates.
- **FR-010**: Menu item labels MUST be sourced from the application's existing translation system so they render correctly in all supported languages (English and Arabic), including right-to-left layout when Arabic is active.

### Key Entities

- **Menu Item**: A navigation entry with a translation key (resolved to a display label in the active language), a target destination, a fixed display position, and a visibility rule defining which roles can see it. May be scoped to one role or shared across all roles. Order is set by developers in the central definition and is not runtime-configurable.
- **Menu Service / Configuration**: The single authoritative source that holds all menu item definitions and provides role-filtered lists to the layout layer on request.
- **Role**: A user classification (Admin, Reviewer, Client) that determines which menu items are visible and which destinations are accessible.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Zero menu item definitions exist outside the centralized definition — confirmed by a codebase audit finding no menu arrays or link lists embedded in individual Blade layout files.
- **SC-002**: Each of the three roles (Admin, Reviewer, Client) sees exactly the items designated for their role with no bleed-over, verifiable by role-switching tests covering every defined menu item.
- **SC-003**: The active navigation state is correctly highlighted on 100% of application pages, including sub-route pages, with no false positives on unrelated menu items.
- **SC-004**: Zero regressions in existing page accessibility — all pages reachable before the refactor remain reachable after, and no new access errors are introduced.
- **SC-005**: A developer can add, remove, or rename a menu item by editing the centralized definition only, with no additional file changes required for the change to take effect across all roles.

## Assumptions

- The existing application has exactly three roles: Admin, Reviewer, and Client. No other roles exist at the time of this phase.
- All menu items currently in use across the three role-specific layouts are known and can be catalogued before implementation begins.
- The three role-specific layout templates remain separate; this phase only introduces a shared menu component that each layout includes. Full layout unification is out of scope.
- Users will not commonly have overlapping role assignments in the initial implementation, though the multi-role edge case is handled defensively per FR-009.
- Menu caching, icon support, nested menu groups, permission-based (granular) visibility, and runtime admin-configurable ordering are explicitly out of scope for this phase and deferred to future enhancements.

## Out of Scope

- Icon support for menu items
- Nested or grouped menu hierarchies
- Menu caching or performance optimization
- Permission-based (granular) visibility beyond role-level scoping
- Any changes to backend authorization, middleware, or policies
- Changes to the visual design or styling of the navigation beyond the active-state highlight
