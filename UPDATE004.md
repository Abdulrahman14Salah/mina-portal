UPDATE003 — Integrity & Security Fixes Specification

Executive Summary

This specification addresses critical integrity, authorization, and consistency issues identified after the initial implementation. The goal is to fix real bugs without changing system behavior or breaking existing tests.

This phase focuses on:
	•	Data integrity
	•	Authorization correctness
	•	Workflow reliability
	•	Concurrency safety

No new features are introduced.

⸻

Phase 1 — Workflow Integrity Fixes

Goal

Ensure workflow execution is logically correct and does not produce invalid states.

Specs

Fix 1 — Prevent false workflow_started logs
	•	Only log workflow_started when tasks are actually created

Rules:
	•	If no tasks are seeded → do not log

Implementation:
	•	Track seeded state with boolean flag
	•	Wrap audit log inside condition

⸻

Fix 2 — Correct next task lookup
Problem:
	•	position + 1 fails when positions are non-contiguous

Specs:
	•	Always select the next task by position greater than current

Implementation:
	•	Replace equality lookup with ordered query

Rules:
	•	Must work with any position gaps

⸻

Phase 2 — Authorization Hardening

Goal

Ensure strict access control based on assignment and role

Specs

Fix 3 — Reopen task authorization
Problem:
	•	Any reviewer can reopen any task

Rules:
	•	Only assigned reviewer OR admin can reopen

Implementation:
	•	Add assignment check identical to approve/reject logic

⸻

Fix 4 — Restrict reviewer assignment
Problem:
	•	Any user can be assigned as reviewer

Rules:
	•	Only users with reviewer role can be assigned

Implementation:
	•	Validate role in request or controller

⸻

Phase 3 — Data Consistency & Transactions

Goal

Ensure atomic operations for critical writes

Specs

Fix 5 — Wrap document upload in transaction
Problem:
	•	Partial writes possible (document saved without state updates)

Rules:
	•	All related writes must succeed or fail together

Implementation:
	•	Wrap create + updates in DB transaction
	•	Move audit logging outside transaction

⸻

Phase 4 — Concurrency Safety

Goal

Prevent race conditions in ordered data

Specs

Fix 6 — Position assignment race condition
Problem:
	•	max(position)+1 causes duplicates under concurrent requests

Rules:
	•	Position must remain unique per scope

Implementation options:
	•	Use DB transaction with locking
	•	OR enforce unique constraint at DB level

⸻

Constraints
	•	Do NOT change system behavior
	•	Do NOT break existing tests
	•	Do NOT introduce new features
	•	Changes must be minimal and targeted

⸻

Testing Requirements
	•	Workflow does not log start when no tasks exist
	•	Reviewer cannot reopen unassigned task
	•	Non-reviewer cannot be assigned as reviewer
	•	Upload remains consistent under failure scenarios
	•	No duplicate positions under concurrent requests

⸻

Summary

This phase ensures the system is:
	•	Safe under concurrency
	•	Secure in access control
	•	Consistent in data handling

It prepares the system for production reliability without altering existing functionality.