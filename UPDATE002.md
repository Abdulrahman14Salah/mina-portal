Visa Portal Workflow & Document System

Executive Summary

This document defines the architecture and implementation plan for a dynamic visa application workflow system. The system enables administrators to define visa-specific task templates, automatically generate tasks per client application, manage document uploads, and assign reviewers.

The goal is to create a scalable, auditable, and production-ready workflow engine where:
	•	Each visa type has its own structured process (sections + tasks)
	•	Each client application gets its own isolated copy of tasks
	•	Documents are managed with strict rules (limits, roles, retention)
	•	Reviewers are assigned and responsible for processing applications

This system is designed to be:
	•	Modular (template vs application separation)
	•	Secure (role-based access via policies)
	•	Scalable (no shared state between applications)
	•	LLM-friendly (clear separation of responsibilities)

⸻

Phase 1: Visa Templates & Workflow Definition

Goal

Allow admins to define reusable visa workflows.

Requirements
	•	Create visa types
	•	Each visa type contains:
	•	Sections (grouping layer)
	•	Tasks within each section

Data Model
	•	visa_types
	•	workflow_sections
	•	workflow_tasks

Rules
	•	Tasks must support:
	•	title
	•	description (optional)
	•	type (upload / text / both)
	•	Sections and tasks must be ordered

⸻

Phase 2: Application Creation & Task Generation

Goal

Automatically generate tasks when a client applies.

Requirements
	•	When a client submits an application:
	•	Selects visa type
	•	System clones workflow into application-specific tasks

Data Model
	•	visa_applications
	•	application_tasks

Rules
	•	Tasks are copied (not referenced)
	•	Template changes do NOT affect existing applications

⸻

Phase 3: Task UI & Client Interaction

Goal

Provide structured task experience for clients.

Requirements
	•	Tasks grouped by section
	•	Each task has its own page

Task Page
	•	Show title
	•	Show description
	•	Render input based on type:
	•	upload
	•	text
	•	both

Rules
	•	Max 10 files per task
	•	Client can delete own files only when task is open

⸻

Phase 4: Document System

Goal

Manage document uploads consistently and safely.

Rules
	•	Add-only uploads (no overwrite)
	•	Max 10 files per task
	•	Max 10 application-level files
	•	Files linked to:
	•	application_task_id OR application_id

Permissions
	•	Client: upload/delete own files (open tasks only)
	•	Reviewer: upload to assigned applications
	•	Admin: full access

Retention
	•	Files archived after application closure
	•	Retained for 24 months
	•	Marked for deletion after expiry

⸻

Phase 5: Reviewer Assignment

Goal

Assign responsibility for each application.

Requirements
	•	Admin assigns reviewer to application

Rules
	•	Only assigned reviewer can:
	•	view application
	•	upload documents
	•	review tasks

Visibility
	•	Client sees assigned reviewer
	•	Admin sees assignment overview

⸻

Phase 6: Task Workflow & Status

Goal

Enable review lifecycle.

Task Status
	•	pending
	•	in_progress
	•	completed
	•	rejected

Reviewer Actions
	•	Approve task
	•	Reject task with reason

Client Actions
	•	Re-upload if rejected

⸻

Phase 7: Permissions & Security

Goal

Centralize access control.

Requirements
	•	Use policies for all actions:
	•	view
	•	upload
	•	delete

Rules
	•	No role checks inside controllers
	•	All access via policy layer

⸻

Phase 8: Testing

Goal

Ensure system stability.

Required Tests
	•	Task generation per visa type
	•	File upload limits
	•	Role-based access
	•	Reviewer assignment restrictions
	•	Rejected task re-upload flow

⸻

Phase 9: Future Enhancements (Optional)
	•	Progress tracking per section
	•	Notifications (approval/rejection)
	•	Due dates per task
	•	File versioning UI
	•	Audit logs expansion

⸻

Final Notes

This system must maintain strict separation between:
	•	Templates vs Applications
	•	Tasks vs Documents
	•	Roles vs Permissions

All business logic should be centralized in service classes, ensuring controllers remain thin and maintainable.
