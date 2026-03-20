# Implementation Plan: Phase 4 — Document Management System

**Branch**: `004-document-management` | **Date**: 2026-03-19 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/004-document-management/spec.md`

## Summary

Implement secure document upload and download for the Visa Application Portal: clients upload files (PDF/JPG/JPEG/PNG ≤ 10 MB) against their current document-required workflow task; files are stored on a private disk (local dev, S3 prod) under UUID-based names; downloads are served through a policy-gated controller route; reviewers see all uploaded documents inline on the application detail page; admins upload on behalf of clients via a minimal admin application page. `DocumentService` owns all business logic including the `awaiting_documents` status transition. Built on Laravel 11 / Blade SSR with `DocumentPolicy` enforcing all access control.

## Technical Context

**Language/Version**: PHP 8.2+ / Laravel 11
**Primary Dependencies**: Laravel Breeze (Blade), Alpine.js v3, `spatie/laravel-permission` v6+
**Storage**: MySQL (MAMP local dev); SQLite in-memory (tests); `private` local disk for files (dev), S3 (prod) via `FILESYSTEM_DISK` env var
**Testing**: PHPUnit via Laravel feature tests
**Target Platform**: Web server — MAMP (local), Linux (production)
**Performance Goals**: Client upload listed within 5 s (SC-001); admin upload attributed within 10 s (SC-005)
**Constraints**: No SPA; Blade SSR; CSRF enabled; all protected routes behind `auth` middleware; no inline `$request->validate()`; no `$guarded = []`; all strings via `__()`; files stored on private disk only — never public
**Scale/Scope**: Up to hundreds of active applications; up to 20 files per application; single reviewer pool (Phase 6 adds assignment)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

| # | Principle | Status | Notes |
|---|-----------|--------|-------|
| I | Modular Architecture | ✅ PASS | New `Documents` module: `app/Http/Controllers/Client/DocumentController`, `Admin/DocumentController`, `app/Controllers/DocumentController` (shared download), `app/Services/Documents/DocumentService`, `app/Policies/DocumentPolicy`. |
| II | Separation of Concerns | ✅ PASS | `DocumentService` owns all file storage, DB writes, status transitions, and audit logging. Controllers delegate entirely. Zero business logic in Blade. |
| III | Database-Driven Workflows | ✅ PASS | The `is_document_required` flag on `workflow_step_templates` (seeded in Phase 3) drives which steps show the upload form. No step names or document types hardcoded in PHP. |
| IV | API-Ready Design | ✅ PASS | All document data accessed via `DocumentService`. Controllers pass arrays/models to views. |
| V | Roles & Permissions | ✅ PASS | Three new permissions: `documents.upload`, `documents.download`, `documents.admin-upload`. `DocumentPolicy` enforces per-document authorization. `$user->can()` gates only. |
| VI | Payment Integrity | ✅ N/A | Phase 5. |
| VII | Secure Document Handling | ✅ PASS | This IS the implementation of Principle VII. Private disk only. UUID stored filenames. MIME validation via Form Request. Policy-gated download route. Disk swappable via env. |
| VIII | Dynamic Step-Based Workflow Engine | ✅ PASS | Upload form shown/hidden based on `is_document_required` flag from the database — not hardcoded. Phase 3 workflow engine unchanged. |
| IX | Security by Default | ✅ PASS | `UploadDocumentRequest` and `AdminUploadDocumentRequest` validate MIME type + size. `$fillable` on `Document` model. CSRF on all forms. `auth` middleware on all routes. `DocumentPolicy` registered in `AppServiceProvider`. |
| X | Multi-Language Support | ✅ PASS | New lang files: `lang/en/documents.php` and `lang/ar/documents.php` (proxies) + `resources/lang/en/documents.php` and `resources/lang/ar/documents.php` (content). All Blade strings via `__('documents.*')`. RTL inherited from `app.blade.php`. |
| XI | Observability & Activity Logging | ✅ PASS | `AuditLogService::log()` called for: `document_uploaded` (on every successful upload), `document_downloaded` (on every successful download). |
| XII | Testing Standards | ✅ PASS | Feature tests: valid upload, type rejection, size rejection, reviewer download, cross-client 403, unauthenticated 302, admin upload, idempotent status transition, documents retained after approval. |

**Constitution Gate**: PASS — no violations. No Complexity Tracking entries required.

## Project Structure

### Documentation (this feature)

```text
specs/004-document-management/
├── plan.md              ← This file
├── spec.md              ← Feature specification
├── research.md          ← Phase 0 output
├── data-model.md        ← Phase 1 output
├── quickstart.md        ← Phase 1 output
├── contracts/
│   └── routes.md        ← Phase 1 output
└── tasks.md             ← Phase 2 output (/speckit.tasks — not created here)
```

### Source Code Layout

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── DocumentController.php                     # GET /documents/{document}/download (shared, policy-gated)
│   │   ├── Client/
│   │   │   └── DocumentController.php                 # POST /client/documents
│   │   └── Admin/
│   │       ├── ApplicationController.php              # GET /admin/applications (minimal list)
│   │       └── DocumentController.php                 # GET+POST /admin/applications/{application}/documents
│   ├── Requests/
│   │   ├── Client/
│   │   │   └── UploadDocumentRequest.php              # file: mimes:pdf,jpg,jpeg,png, max:10240; application_task_id: exists
│   │   └── Admin/
│   │       └── AdminUploadDocumentRequest.php         # same rules
│   └── Middleware/                                    # No new middleware
├── Models/
│   ├── Document.php                                   # belongsTo VisaApplication, ApplicationTask, User(uploader)
│   ├── VisaApplication.php                            # MODIFY: add documents() HasMany
│   └── ApplicationTask.php                            # MODIFY: add documents() HasMany
├── Policies/
│   └── DocumentPolicy.php                             # upload(), download(), adminUpload()
├── Services/
│   └── Documents/
│       └── DocumentService.php                        # upload(), serve(), listForApplication()
└── Providers/
    └── AppServiceProvider.php                         # MODIFY: register DocumentPolicy

database/
├── migrations/
│   └── xxxx_create_documents_table.php
└── seeders/
    └── RolePermissionSeeder.php                       # MODIFY: add documents.upload, documents.download, documents.admin-upload

resources/
├── views/
│   ├── client/
│   │   └── dashboard/
│   │       └── tabs/
│   │           └── documents.blade.php               # MODIFY: replace stub with upload form + doc list
│   ├── reviewer/
│   │   └── applications/
│   │       └── show.blade.php                        # MODIFY: add document list section below task list
│   └── admin/
│       └── applications/
│           ├── index.blade.php                       # Minimal application list (pre-Phase 6)
│           └── documents.blade.php                   # Admin document management + upload form
└── lang/
    ├── en/
    │   └── documents.php                             # EN document strings (content)
    └── ar/
        └── documents.php                             # AR document strings (content)

lang/
├── en/
│   └── documents.php                                 # Proxy → resource_path('lang/en/documents.php')
└── ar/
    └── documents.php                                 # Proxy → resource_path('lang/ar/documents.php')

routes/
└── web.php                                           # MODIFY: add shared download route + client upload route + admin application+document routes

app/Http/Controllers/Client/DashboardController.php  # MODIFY: eager-load tasks.documents.uploader
```

**Structure Decision**: Laravel monolith (consistent with Phases 1–3). Shared download controller at `app/Http/Controllers/DocumentController.php` (root namespace) keeps the policy-gated download route neutral — not tied to client, reviewer, or admin. Client upload under `Client\` namespace. Admin under `Admin\`. `DocumentService` in `app/Services/Documents/` for module isolation.

## Complexity Tracking

> No constitution violations — table not required.
