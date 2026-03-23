Project Executive Summary

This project is a production-ready Laravel 11 application designed as a client portal system for managing visa applications.

The system focuses on:
	•	Structured application workflows
	•	Task and document management
	•	Multi-stage payment handling (Stripe)
	•	Role-based access control (Admin, Reviewer, Client)

The goal is to build a scalable, maintainable, and extensible system that can evolve into a SaaS product.

⸻

PHASE 1 — Authentication & Application Entry

Specs:
	1.	Apply Form as Default Entry Point
	2.	Login / Apply Toggle Navigation
	3.	Authentication System (Laravel Breeze - customized)
	4.	Role Assignment (Client by default)

⸻

PHASE 2 — Workflow System (CORE SYSTEM)

Specs:
	1.	Task System Spec
	2.	Task Steps Spec (6 Steps)
	3.	Progress Tracking Spec
	4.	Task UI Spec

⸻

PHASE 3 — Document Management System

Specs:
	1.	File Upload System
	2.	Role-based Upload Permissions
	3.	File Validation & Security
	4.	Reviewer Upload Attribution

⸻

PHASE 4 — Payment System (Stripe Integration)

Specs:
	1.	Multi-stage Payment Flow (3 Stages)
	2.	Payment Confirmation Handling
	3.	Webhook Integration
	4.	Payment Status Tracking

⸻

PHASE 5 — Admin & Reviewer Panel

Specs:
	1.	Admin Dashboard
	2.	Reviewer Assignment System
	3.	Task Monitoring Interface
	4.	Application Status Control

⸻

PHASE 6 — System Enhancements & Optimization

Specs:
	1.	Performance Optimization
	2.	Security Hardening
	3.	Logging & Monitoring
	4.	Code Refactoring & Cleanup

⸻

FIX — Auth & System Adjustments

Specs:

1. Default Route Spec (Apply as Entry Point)
	•	The root route / must load the Apply form directly.
	•	/ and /apply should render the same view.

2. Auth Navigation Spec (Login ↔ Apply Toggle)
	•	Apply Page: “Already have an account? Login” → /login
	•	Login Page: “Don’t have an account? Apply now” → /apply
	•	Positioned under the form, centered, styled as a text link.

3. Admin Auto-Creation Spec (ENV Seeder)
	•	On php artisan migrate:refresh --seed
	•	Create or update admin user from .env
	•	Assign role admin

4. Apply Route Spec
	•	Explicit route /apply
	•	Same behavior as /

5. Auth Protection Spec (Recommended)
	•	Authenticated users cannot access /login or /apply
	•	Redirect to dashboard
