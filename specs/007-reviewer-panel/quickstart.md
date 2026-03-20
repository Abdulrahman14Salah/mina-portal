# Quickstart Test Scenarios: Reviewer Panel (007)

**Date**: 2026-03-20 | **Branch**: `007-reviewer-panel`

After running `php artisan migrate:fresh --seed`, use these scenarios to manually verify all spec requirements.

---

## Prerequisites

1. Log in as admin → create a test client account (or register one via `/register`).
2. As admin, ensure the client has a submitted visa application (status `pending_review`).
3. Log in as reviewer (seed creates reviewer@example.com / password).

---

## Scenario 1: Application Queue (US1)

1. Log in as reviewer → navigate to `/reviewer/dashboard`.
2. ✅ Queue shows the test application with: reference number, client name, visa type, submission date.
3. ✅ Applications with status `approved` or `rejected` do NOT appear.
4. As admin, approve the test application → refresh reviewer queue → ✅ no longer visible.

---

## Scenario 2: Task Review — Advance (US2)

1. As reviewer, open an application with status `pending_review`.
2. ✅ Tasks listed in order; Task 1 shown as active (in_progress).
3. Enter an optional note → click "Mark as Complete".
4. ✅ Task 1 becomes `completed`; Task 2 becomes active.
5. Advance all 6 tasks → ✅ application status becomes `Approved`; success message shown.

---

## Scenario 3: Task Review — Reject Without Reason (US2 Scenario 5)

1. As reviewer, open an application with an active task.
2. Leave the rejection reason field empty → click "Reject".
3. ✅ Form fails validation; error message shown; task is NOT rejected.

---

## Scenario 4: Task Review — Reject With Reason (US2 Scenario 4)

1. As reviewer, open an application with an active task.
2. Enter a rejection reason ("Missing passport copy") → click "Reject".
3. ✅ Task status becomes `rejected`; application status becomes `rejected`.
4. ✅ Application no longer appears in reviewer queue.

---

## Scenario 5: Document Review (US3)

1. As client, upload a document (passport.pdf) to the application.
2. As reviewer, open the application detail.
3. ✅ "passport.pdf" appears in the documents section with upload date and client's name.
4. Click "Download" → ✅ file downloads.
5. Check audit log (`audit_logs` table) → ✅ row with `event = 'document_downloaded'` exists.

---

## Scenario 6: Reviewer Document Upload (US4)

1. As reviewer, open an application detail.
2. In the "Upload Document" section, select a task from the dropdown and attach "decision_letter.pdf".
3. Click "Upload".
4. ✅ "decision_letter.pdf" appears in the documents list labelled "Reviewer Upload" with reviewer's name.
5. ✅ Upload is attributed as `source_type = 'reviewer'` in `documents` table.
6. Log in as client → view dashboard → documents tab → ✅ "decision_letter.pdf" visible, attributed to reviewer/office.

---

## Scenario 7: Access Control

1. Log in as client → attempt to navigate to `/reviewer/dashboard` → ✅ 403 Forbidden.
2. Log in as admin → attempt to navigate to `/reviewer/dashboard` → ✅ 403 Forbidden (admin uses admin panel).
3. Unauthenticated user → GET `/reviewer/dashboard` → ✅ redirected to login.

---

## Scenario 8: Invalid File Upload

1. As reviewer, attempt to upload a `.exe` file.
2. ✅ Validation error: "The file must be a file of type: pdf, jpg, jpeg, png."
