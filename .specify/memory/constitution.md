<!--
SYNC IMPACT REPORT
==================
Version change: [TEMPLATE] → 1.0.0 (initial constitution — all placeholders replaced)

Modified principles: N/A (initial authoring from template)

Added sections:
  - Core Principles (12 principles)
  - Tech Stack & Constraints
  - Development Workflow
  - Anti-Patterns (Forbidden)
  - Definition of Done
  - Governance

Removed sections: N/A

Templates reviewed:
  ✅ .specify/templates/plan-template.md — Constitution Check section present; gates align with principles below
  ✅ .specify/templates/spec-template.md — Functional Requirements / Success Criteria align with DoD below
  ✅ .specify/templates/tasks-template.md — Phase structure aligns with modular/workflow principles
  ✅ .specify/templates/constitution-template.md — Source template; no changes needed

Follow-up TODOs:
  - TODO(RATIFICATION_DATE): Date set to first-authoring date 2026-03-19; confirm with team if an earlier adoption date should be recorded.
  - TODO(MULTI_TENANT): Multi-tenant SaaS scalability is a declared future goal; constitution will require a MINOR amendment once active work begins.
-->

# Visa Application Client Portal Constitution

## Core Principles

### I. Modular Architecture (NON-NEGOTIABLE)

The application MUST be organized into discrete, self-contained modules:
**Auth · Client · Visa · Tasks · Payments · Documents · Notifications · Admin · Reviewer**

Each module MUST contain its own: Models, Controllers, Form Requests (validation), and Services (business logic).
No module may reach into another module's internals; cross-module communication MUST go through Services.

**Rationale**: Modules enforce independent testability, reduce coupling, and support future extraction
into separate packages or microservices.

### II. Separation of Concerns (NON-NEGOTIABLE)

Responsibility boundaries MUST be respected at all times:

- **Controllers** → handle HTTP request/response only; zero business logic
- **Services** → own all business logic; framework-agnostic where possible
- **Models** → encapsulate database interaction and relationships only
- **Views (Blade)** → render UI only; zero business logic, zero direct DB queries

Any PR that places business logic in a Controller or Blade template MUST be rejected.

**Rationale**: Enforces API-readiness, testability, and maintainability across the lifetime of the project.

### III. Database-Driven Workflows (CRITICAL — NON-NEGOTIABLE)

Visa definitions, task definitions, task steps, and form field structures MUST live in the database as
configurable records (JSON where structure varies). No workflow, step sequence, or form schema may be
hardcoded in PHP or Blade.

- Visa types → `visa_types` table
- Tasks → `tasks` table linked to visa types
- Task steps → `task_steps` table with JSON field definitions
- Forms → JSON column on step records

**Rationale**: The business domain changes frequently; hardcoded workflows create expensive code changes
for every visa type adjustment.

### IV. API-Ready Design

Even while using Blade for rendering, all features MUST be implemented in a way that supports future
API exposure without refactoring:

- All data retrieval and mutation MUST go through Service classes
- Controllers MUST return structured data (array/DTO) that Views consume
- No direct Eloquent queries inside Controllers or Views

**Rationale**: Supports future Vue/React frontend, mobile apps, and multi-tenant SaaS API consumers
without architectural rework.

### V. Roles & Permissions — Granular & Data-Driven

All authorization MUST use `spatie/laravel-permission`. The three roles are:

- **Admin** → full system control
- **Reviewer** → read + review access on assigned applications
- **Client** → own data only

Permissions MUST be granular (e.g., `applications.view`, `documents.upload`) and seeded via migrations/seeders.
Role-based conditionals MUST NOT be hardcoded as `if ($user->role === 'admin')` — use `$user->can()` and
Laravel Policies exclusively.

**Rationale**: Granular permissions allow fine-grained access control changes without code deploys.

### VI. Payment Integrity

Stripe is the ONLY payment provider. Payments are divided into exactly **3 independently tracked stages**.
Each stage MUST:

- Be stored as a separate payment record with its own status lifecycle
- Trigger a Stripe Checkout/PaymentIntent and record the Stripe reference
- Handle Stripe webhooks to confirm status (do not trust redirect callbacks alone)
- Send an email confirmation on success

No payment stage may be marked complete without webhook confirmation.

**Rationale**: Redirect-only confirmation is unreliable; webhook confirmation prevents revenue leakage
and disputes.

### VII. Secure Document Handling

All file uploads MUST:

- Be validated (MIME type, size, extension allowlist) via Form Requests before storage
- Be stored outside the public web root (local disk `private` or S3 private bucket)
- Be linked to both a **client** and an **application** record
- Be served only through signed, time-limited URLs

Access to documents MUST be enforced by a Policy; direct URL guessing MUST NOT grant access.
The storage driver MUST be swappable via `.env` (local ↔ S3) with no code changes.

**Rationale**: Passport/visa documents are sensitive PII; exposure constitutes a legal and reputational risk.

### VIII. Dynamic Step-Based Workflow Engine

The workflow system is the core product capability. It MUST be:

- **Dynamic**: steps and fields are configured per visa type in the database
- **Step-based**: each task progresses through ordered steps; steps cannot be skipped
- **Trackable**: progress state is persisted per application/task/step with timestamps
- **Visible**: both Client and Admin dashboards MUST reflect real-time step status

Step completion state MUST be stored in a pivot/progress table, not derived from other columns.

**Rationale**: The entire value proposition depends on auditable, configurable workflows.

### IX. Security by Default

- All routes MUST be protected by `auth` middleware; no authenticated content is publicly accessible
- All user input MUST be validated using Laravel Form Request classes — inline `$request->validate()` is forbidden
- All models MUST use `$fillable` (not `$guarded = []`) to prevent mass-assignment
- CSRF protection MUST remain enabled for all state-changing web routes
- All file uploads MUST be sanitized (type + size validation) before storage
- Authorization checks MUST use Laravel Policies registered in `AuthServiceProvider`

**Rationale**: A portal handling visa applications and payment data is a high-value target.

### X. Multi-Language Support

The application MUST support **Arabic** and **English** as first-class languages:

- All user-facing strings MUST use Laravel localization (`__()` / `@lang`)
- Locale switching MUST persist per user session/preference
- Content fetched from the WordPress REST API MUST respect the active locale when the API supports it
- RTL layout MUST be applied when locale is `ar`

Hardcoding English strings in Blade templates or PHP is forbidden.

**Rationale**: The target audience is bilingual; Arabic is a primary language for many visa applicants.

### XI. Observability & Activity Logging

An activity log entry MUST be created for every:

- Payment stage initiated, succeeded, or failed
- Document uploaded or deleted
- Task step completed or rejected
- User role or permission change

Errors MUST be logged with full context (user, action, model ID) using Laravel's logging facilities.
Log entries MUST NOT contain raw payment credentials or file contents.

**Rationale**: Audit trails are required for dispute resolution, compliance, and debugging production issues.

### XII. Testing Standards

Feature tests MUST be written for:

- **Authentication** flows (login, registration, password reset)
- **Workflow** progression (step advancement, validation, rejection)
- **Payment** stages (initiation, webhook handling, status update)

Tests MUST use a test database (SQLite in-memory or a dedicated test DB) and MUST NOT affect production data.
Unit tests are encouraged for all Service classes.

**Rationale**: The payment and workflow systems carry financial and legal consequences; untested regressions
are unacceptable.

## Tech Stack & Constraints

| Layer | Technology | Notes |
|---|---|---|
| Backend | Laravel 11 | PHP 8.2+ required |
| Frontend | Blade (SSR) | No SPA framework initially |
| Auth | Laravel Breeze | Extends with Spatie roles |
| Roles/Permissions | spatie/laravel-permission | v6+ |
| Payments | Stripe | Checkout + Webhooks |
| Storage | Local (dev) / AWS S3 (prod) | Configurable via `FILESYSTEM_DISK` |
| External API | WordPress REST API | FAQ & Services content |
| Queue | Laravel Queue (database driver minimum) | Required for notifications |

**Constraints**:
- PHP business logic MUST NOT depend on a specific storage driver
- WordPress API calls MUST be cached (minimum 5 minutes) to avoid rate limits
- Queue workers MUST be running in production for notifications and webhook processing

## Development Workflow

For every feature:

1. Create a spec file at `specs/[###-feature-name]/spec.md` using the spec template
2. Define: requirements, data structures, acceptance criteria
3. Generate a plan (`/speckit.plan`) before writing any code
4. Implement using Laravel following all principles above
5. Validate against acceptance criteria before marking complete

**Feature is complete (Definition of Done) when**:
- Spec fully implemented
- All inputs validated via Form Requests
- Permissions enforced via Policies
- UI functional and localized (AR + EN)
- Activity log entries confirmed
- Manually tested against acceptance scenarios
- No hardcoded strings, workflows, or role checks

## Anti-Patterns (Forbidden)

The following patterns MUST be caught in code review and rejected:

| Forbidden Pattern | Required Alternative |
|---|---|
| Hardcoded visa/task workflows in PHP | Database-driven task/step records |
| Business logic in Blade templates | Service class method |
| Direct Eloquent queries in Controllers | Service class method |
| `$request->validate()` inline | Dedicated Form Request class |
| `if ($user->role === 'admin')` | `$user->can('permission')` + Policy |
| Storing files in `public/` | Private disk + signed URLs |
| Trusting payment redirect as confirmation | Stripe webhook confirmation |
| Hardcoded English strings in views | `__('key')` with lang files |

## Governance

This constitution supersedes all other development conventions for this project. Any practice that
conflicts with a principle stated here MUST be brought to the team for a constitution amendment —
it MUST NOT be silently worked around.

**Amendment procedure**:
1. Propose the amendment in writing (PR to `.specify/memory/constitution.md`)
2. Identify the version bump type (MAJOR/MINOR/PATCH) per semantic versioning rules
3. Update `LAST_AMENDED_DATE` and `CONSTITUTION_VERSION`
4. Run the consistency propagation checklist (update dependent templates as needed)
5. Merge only after team review

**Versioning policy**:
- MAJOR: Removal or fundamental redefinition of an existing principle
- MINOR: New principle or section added; material expansion of guidance
- PATCH: Clarifications, wording corrections, typo fixes

**Compliance review**: Every PR must be checked against the Core Principles and Anti-Patterns table.
Complexity violations require a written justification entry in the plan's Complexity Tracking table.

**Version**: 1.0.0 | **Ratified**: 2026-03-19 | **Last Amended**: 2026-03-19
