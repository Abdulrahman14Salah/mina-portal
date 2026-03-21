# portal-sass Development Guidelines

Auto-generated from all feature plans. Last updated: 2026-03-20

## Active Technologies
- MySQL (MAMP local dev); SQLite in-memory (tests) (003-workflow-engine)
- MySQL (MAMP local dev); SQLite in-memory (tests); `private` local disk for files (dev), S3 (prod) via `FILESYSTEM_DISK` env var (004-document-management)
- PHP 8.2+ / Laravel 11 + Laravel Breeze (Blade), Alpine.js v3, `spatie/laravel-permission` v6+, `stripe/stripe-php` (official Stripe PHP SDK) (005-payment-system)
- PHP 8.2+ / Laravel 11 + Laravel Breeze (Blade SSR), Alpine.js v3, `spatie/laravel-permission` v6+ (006-admin-panel-foundation)
- MySQL (MAMP local dev, port 8889); SQLite in-memory (tests) (006-admin-panel-foundation)
- PHP 8.2+ / Laravel 11 + spatie/laravel-permission v6+, Laravel Blade, local/S3 filesystem (via `FILESYSTEM_DISK`) (007-reviewer-panel)
- PHP 8.2+ / Laravel 11 + Laravel Breeze (Blade SSR), spatie/laravel-permission v6+, Alpine.js v3 (008-auth-application-entry)
- MySQL (MAMP local, port 8889) for dev; SQLite in-memory for tests (008-auth-application-entry)

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
- 008-auth-application-entry: Added PHP 8.2+ / Laravel 11 + Laravel Breeze (Blade SSR), spatie/laravel-permission v6+, Alpine.js v3
- 007-reviewer-panel: Added PHP 8.2+ / Laravel 11 + spatie/laravel-permission v6+, Laravel Blade, local/S3 filesystem (via `FILESYSTEM_DISK`)
- 006-admin-panel-foundation: Added PHP 8.2+ / Laravel 11 + Laravel Breeze (Blade SSR), Alpine.js v3, `spatie/laravel-permission` v6+


<!-- MANUAL ADDITIONS START -->
<!-- MANUAL ADDITIONS END -->
