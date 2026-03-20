# Research: Reviewer Panel (007)

**Date**: 2026-03-20 | **Branch**: `007-reviewer-panel`

---

## Decision 1: Rejection Reason Validation

**Decision**: Change `RejectTaskRequest` to make `note` required (not nullable). Minimum 5 characters.

**Rationale**: The spec (FR-005, US2 Scenario 5) explicitly requires a non-empty rejection reason. The current `nullable` rule is a bug introduced by the cheaper LLM. The lang keys `note_label` / `note_placeholder` also need updating to reflect "required reason" semantics.

**Alternatives considered**:
- Keep nullable and handle in controller — rejected: validation belongs in Form Request (constitution Principle IX).
- Custom Rule class — overkill; `required|string|min:5|max:2000` is sufficient.

---

## Decision 2: Document Source Type Tracking

**Decision**: Add a `source_type` string column (`client`, `reviewer`, `admin`) to the `documents` table via a new migration. Update `DocumentService::upload()` to accept a `string $sourceType = 'client'` parameter. Update `Document::$fillable`.

**Rationale**: The spec requires reviewer-uploaded documents to be "labelled distinctly" (FR-011). Deriving source at display time from the uploader's role is fragile — roles can change; a user could have multiple roles. A dedicated column is authoritative and queryable.

**Alternatives considered**:
- Derive from uploader role at display time — rejected: role changes break attribution retroactively.
- Add a boolean `is_reviewer_upload` column — rejected: doesn't cleanly support the `admin` source type already in use.

---

## Decision 3: Reviewer Upload Permission

**Decision**: Add a new permission `documents.reviewer-upload` and grant it to the `reviewer` role. Add a `reviewerUpload(User $user): bool` method to `DocumentPolicy`. Do NOT reuse `documents.admin-upload` for reviewers.

**Rationale**: Constitution Principle V requires granular, named permissions. Reusing `admin-upload` would conflate admin and reviewer capabilities, making future permission narrowing harder. Separate permission allows independent revocation.

**Alternatives considered**:
- Reuse `documents.admin-upload` for reviewers — rejected: violates principle of granular permissions; conflates distinct roles.
- Skip Policy, check role directly — rejected: forbidden by constitution (Principle V anti-pattern: `if ($user->role === 'reviewer')`).

---

## Decision 4: Reviewer Document Upload Routing & Controller

**Decision**: Add a `ReviewerDocumentController` in `App\Http\Controllers\Reviewer\` with a single `store()` method. Route: `POST /reviewer/applications/{application}/documents`. Reuse `DocumentService::upload()` with `$sourceType = 'reviewer'`.

**Rationale**: Constitution Principle I requires each module to have its own controllers. Putting reviewer upload in the existing `ReviewerApplicationController` would mix application-lifecycle concerns with document concerns. A dedicated controller keeps responsibilities clean.

**Alternatives considered**:
- Add `storeDocument()` to `ReviewerApplicationController` — rejected: mixes concerns, violates SRP.
- Reuse `Admin\DocumentController` — rejected: wrong module boundary; admin routes have different middleware and authorization.

---

## Decision 5: Reviewer Layout Component

**Decision**: Create `resources/views/components/reviewer-layout.blade.php` wrapping `<x-app-layout>` with a reviewer-specific header and navigation tab (Applications). Update reviewer views to use `<x-reviewer-layout>`.

**Rationale**: Consistency with `<x-admin-layout>` and `<x-client-layout>` patterns already established. The reviewer dashboard has only one tab currently but the layout component makes future tab additions trivial.

**Alternatives considered**:
- Keep using raw `<x-app-layout>` — rejected: inconsistent UX; reviewer panel has no visual identity.
- One large template include — rejected: doesn't follow the component pattern already established for admin/client.

---

## Decision 6: Permission Seeding Strategy

**Decision**: Update both `database/migrations/2026_03_20_000001_seed_roles_permissions_and_visa_types.php` AND `database/seeders/RolePermissionSeeder.php` to include `documents.reviewer-upload`, assigned to the `reviewer` role. Both use `syncPermissions` (idempotent).

**Rationale**: The migration-based seeding pattern was established in Phase 5/6 to survive `migrate:fresh` without `--seed`. Both locations must stay in sync. `syncPermissions` prevents unique constraint violations.

**Alternatives considered**:
- New migration file only — rejected: RolePermissionSeeder is also called independently in tests and must stay consistent.
- Only update the seeder — rejected: `migrate:fresh` without `--seed` would miss the permission.
