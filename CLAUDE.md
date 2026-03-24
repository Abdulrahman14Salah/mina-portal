# portal-sass Development Guidelines

Auto-generated from all feature plans. Last updated: 2026-03-24

## Active Technologies
- MySQL (MAMP local dev); SQLite in-memory (tests) (003-workflow-engine)
- MySQL (MAMP local dev); SQLite in-memory (tests); `private` local disk for files (dev), S3 (prod) via `FILESYSTEM_DISK` env var (004-document-management)
- PHP 8.2+ / Laravel 11 + Laravel Breeze (Blade), Alpine.js v3, `spatie/laravel-permission` v6+, `stripe/stripe-php` (official Stripe PHP SDK) (005-payment-system)
- PHP 8.2+ / Laravel 11 + Laravel Breeze (Blade SSR), Alpine.js v3, `spatie/laravel-permission` v6+ (006-admin-panel-foundation)
- MySQL (MAMP local dev, port 8889); SQLite in-memory (tests) (006-admin-panel-foundation)
- PHP 8.2+ / Laravel 11 + spatie/laravel-permission v6+, Laravel Blade, local/S3 filesystem (via `FILESYSTEM_DISK`) (007-reviewer-panel)
- PHP 8.2+ / Laravel 11 + Laravel Breeze (Blade SSR), spatie/laravel-permission v6+, Alpine.js v3 (008-auth-application-entry)
- MySQL (MAMP local, port 8889) for dev; SQLite in-memory for tests (008-auth-application-entry)
- PHP 8.2+ / Laravel 11 + spatie/laravel-permission v6+, Laravel Blade, AuditLogService (009-workflow-system)
- MySQL (MAMP local, port 8889); SQLite in-memory (tests) (009-workflow-system)
- PHP 8.2+ / Laravel 11 + `spatie/laravel-permission` v6+, Laravel Blade (SSR), Alpine.js v3 (011-menu-unification-foundation)
- N/A — config file only, no migrations (011-menu-unification-foundation)
- PHP 8.2+ / Laravel 11 + `spatie/laravel-permission` v6+, `AuditLogService` (internal) (012-workflow-integrity)
- No schema changes — existing `application_tasks` and `visa_applications` tables (012-workflow-integrity)
- No new tables — two migrations alter existing `workflow_sections` and `workflow_tasks` tables (013-static-workflow-structure)
- PHP 8.2+ / Laravel 11 + `spatie/laravel-permission` v6+, `AuditLogService` (internal), `DocumentService` (internal) (015-task-type-behavior)
- MySQL (MAMP local, port 8889) for dev; SQLite in-memory for tests; private disk for receipt files (015-task-type-behavior)
- PHP 8.2+ / Laravel 11 + Laravel Blade (SSR), Alpine.js v3, `spatie/laravel-permission` v6+ (016-task-page-ui)
- Private local disk (dev) / S3 (prod) via `FILESYSTEM_DISK` — existing document system reused for receipts (016-task-page-ui)
- PHP 8.2+ / Laravel 11 + Blade SSR, Alpine.js v3, spatie/laravel-permission v6+, Laravel Queue (database driver) (018-reviewer-validation)
- MySQL (MAMP, port 8889 dev); SQLite in-memory (tests); private disk for file uploads (018-reviewer-validation)

- PHP 8.2+ / Laravel 11 + Laravel Breeze (Blade), Alpine.js v3, `spatie/laravel-permission` v6+ (002-client-onboarding)

## Project Structure

```text
src/
tests/
```

## Commands

# Add commands for PHP 8.2+ / Laravel 11

## Code Style

PHP 8.2+ / Laravel 11: Follow standard conventions

## Recent Changes
- 018-reviewer-validation: Added PHP 8.2+ / Laravel 11 + Blade SSR, Alpine.js v3, spatie/laravel-permission v6+, Laravel Queue (database driver)
- 017-task-progression: Added [if applicable, e.g., PostgreSQL, CoreData, files or N/A]
- 016-task-page-ui: Added PHP 8.2+ / Laravel 11 + Laravel Blade (SSR), Alpine.js v3, `spatie/laravel-permission` v6+


<!-- MANUAL ADDITIONS START -->
<!-- MANUAL ADDITIONS END -->
