PHASE 1

Get system running with auth + roles

Specs:
1.Auth System Spec
2.Roles & Permissions Spec
3.User Model Spec

Output:
•Login / Register
•Roles:
•Admin
•Client
•Reviewer

⸻

PHASE 2 — Client Onboarding (CRITICAL)

Specs:
1.Visa Model Spec
2.Client Registration Form Spec
3.Auto Account Creation Spec
4.Dashboard Layout Spec (8 Tabs)

Output:
•Client fills form → account created → redirected to dashboard

⸻

PHASE 3 — Workflow System (CORE SYSTEM)

Specs:
1.Task System Spec
2.Task Steps Spec (6 Steps)
3.Progress Tracking Spec
4.Task UI Spec

Output:
•Dynamic workflow per visa
•Step-by-step forms

👉 This is the heart of the system

⸻

PHASE 4 — File & Document System

Specs:
1.Client Upload System Spec
2.Admin Document Upload Spec
3.File Storage Spec (S3/local)
4.Access Control Spec

⸻

PHASE 5 — Payment System (Stripe 💳)

Specs:
1.Payment Model Spec
2.3-Stage Payment Logic Spec
3.Stripe Integration Spec
4.Payment Webhook Spec

⸻

PHASE 6 — Admin Panel

Specs:
1.Visa Management Spec
2.Client Management Spec
3.Task Builder Spec
4.Reviewer Assignment Spec

⸻

PHASE 7 — Reviewer Panel

Specs:
1.Reviewer Dashboard Spec
2.Client Review Spec
3.Document Upload Spec

⸻

PHASE 8 — Integrations

Specs:
1.WordPress API (FAQ) Spec
2.WordPress API (Services) Spec
3.Social Embed Spec

⸻

PHASE 9 — Advanced Features

Specs:
1.Notification System
2.Activity Log
3.Chat System
4.Status Engine
5.Multi-language (Arabic/English)

⸻

How You Use AI With Spec Kit

Workflow:
1.You write spec (or I help you)
2.You send spec to AI:
“Implement this spec in Laravel 11”
3.AI generates:
•migrations
•models
•controllers
•views
4.You review + refine

✍️ Example Spec (Short Sample)

specs/client-registration.md

# Client Registration Spec

## Context
Clients must submit visa application data before accessing dashboard.

## Requirements
- Form fields:
  - full_name
  - email
  - phone
  - visa_id
  - adults_count
  - children_count
  - nationality
  - country_of_residence
  - job_title
  - work_type
  - monthly_income
  - application_date
  - notes

## Behavior
- On submit:
  - Create user (role: client)
  - Create application record
  - Redirect to dashboard

## Validation
- email unique
- required fields enforced

## Acceptance Criteria
- User is created
- Application is saved
- Redirect works