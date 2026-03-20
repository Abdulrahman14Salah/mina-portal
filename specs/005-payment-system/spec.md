# Feature Specification: Phase 5 — Payment System

**Feature Branch**: `005-payment-system`
**Created**: 2026-03-19
**Status**: Draft
**Input**: User description: "Read PLAN.md and create a specification for the phase 5: Payment System."

## Overview

The Visa Application Portal charges clients three sequential fees across the life of their application. This phase implements the payment infrastructure: a three-stage payment model, a client-facing Payments tab for initiating payments, automated payment confirmation via provider webhooks, and basic admin visibility into payment status.

Payments are processed through a third-party payment provider (Stripe). No card data is ever stored in the system. Every payment record is append-only — status changes produce new audit entries rather than mutating existing rows.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Client Pays a Due Stage (Priority: P1)

A client is notified or sees on their Payments tab that a payment is now due. They click "Pay Now", are redirected to a secure Stripe Checkout page, complete the payment, and are redirected back to their dashboard with a confirmation message. The payment stage status updates from "due" to "paid".

**Why this priority**: Revenue cannot be collected without this flow. Every other story depends on payments being payable.

**Independent Test**: Create a test application with Stage 1 marked as "due". Log in as the client, open the Payments tab, complete a test payment via Stripe test mode, and verify the stage shows "Paid" with the correct amount and date.

**Acceptance Scenarios**:

1. **Given** a client has an application with Stage 1 status = "due", **When** the client clicks "Pay Now" on the Payments tab, **Then** they are redirected to a Stripe Checkout session for the correct amount.
2. **Given** the client completes payment on Stripe, **When** they are redirected back, **Then** the Payments tab shows Stage 1 as "Paid" and a success message is displayed.
3. **Given** Stage 1 is "paid", **When** the client views the Payments tab, **Then** the "Pay Now" button is absent for Stage 1 and replaced with a "Paid" confirmation and date.
4. **Given** Stage 2 is "pending" (not yet due), **When** the client views the Payments tab, **Then** Stage 2 shows "Pending" with no Pay Now button.

---

### User Story 2 — Webhook Confirms Payment Automatically (Priority: P1)

After the client completes a payment on Stripe, Stripe sends a webhook event to the portal. The system verifies the webhook's authenticity and marks the corresponding payment stage as "paid". This happens automatically without any client or admin action.

**Why this priority**: The portal must not rely on the redirect-back flow alone to confirm payment — clients can close their browser before returning. Webhooks are the authoritative confirmation source.

**Independent Test**: Simulate a Stripe webhook `checkout.session.completed` event (using Stripe CLI or test fixture). Verify the matching payment stage transitions to "paid" and an audit log entry is written — without any browser redirect.

**Acceptance Scenarios**:

1. **Given** an active Stripe Checkout session, **When** Stripe sends a `checkout.session.completed` webhook, **Then** the matching payment stage is marked "paid".
2. **Given** a webhook with an invalid signature, **When** the system receives it, **Then** it responds with HTTP 400 and takes no action.
3. **Given** the same webhook is delivered twice (Stripe retry), **When** the system processes it a second time, **Then** the payment stage remains "paid" with no duplicate record created.
4. **Given** a webhook for an unknown session ID, **When** the system receives it, **Then** it responds with HTTP 200 (to stop Stripe retrying) and logs a warning.

---

### User Story 3 — Stage Becomes Due (Priority: P2)

Stage 1 is automatically marked "due" the moment an application is created — the client can pay it immediately. Stages 2 and 3 start as "pending" and are manually marked "due" by an admin when the time is right for that application. This gives admins full control over the billing cadence for later stages while removing friction from the initial fee.

**Why this priority**: Without a trigger mechanism, no payment can be initiated. Depends on US1 having the payment infrastructure in place.

**Independent Test**: Create a fresh application and verify Stage 1 is immediately "due" on the Payments tab. Then log in as admin, mark Stage 2 as "due", log back in as the client, and verify the Pay Now button appears for Stage 2.

**Acceptance Scenarios**:

1. **Given** a new application is created, **When** the client opens the Payments tab, **Then** Stage 1 shows "Due" with a Pay Now button; Stages 2 and 3 show "Pending" with no button.
2. **Given** Stage 2 is "pending", **When** an admin marks Stage 2 as "due", **Then** Stage 2 status becomes "due" and the client sees a Pay Now button on their Payments tab.
3. **Given** Stage 2 is already "paid", **When** an admin attempts to mark it "due" again, **Then** Stage 2 remains "paid" (idempotent, no state change).

## Clarifications

### Session 2026-03-19

- Q: How does each payment stage become due? → A: Stage 1 is automatically due on application creation; Stages 2 and 3 are manually marked "due" by an admin.
- Q: What new permissions does the payment system introduce? → A: Two permissions — `payments.pay` (assigned to client role) and `payments.manage` (assigned to admin role, covers both viewing and marking stages as due).
- Q: What happens to a payment record when the Stripe Checkout session expires without payment? → A: The stage remains "due"; a new "Pay Now" click creates a fresh Stripe session and overwrites the stored session ID. The "failed" status is reserved exclusively for card-declined events, not session abandonment.

---

### User Story 4 — Admin Manages Payment Stages (Priority: P3)

An admin opens an application's payment section. They can see the current status of all three stages (pending, due, paid) with amounts and payment dates. For any stage that is still "pending", they can mark it "due" to unlock it for the client. They do not initiate or process payments themselves — that is the client's action.

**Why this priority**: Admins control the billing cadence for Stages 2 and 3, and need visibility for support queries.

**Independent Test**: Create an application with Stage 1 paid and Stages 2–3 pending. Log in as admin, mark Stage 2 as "due", then verify the client's Payments tab shows a Pay Now button for Stage 2.

**Acceptance Scenarios**:

1. **Given** an application has Stage 1 paid and Stages 2–3 pending, **When** an admin views the application's payment section, **Then** all three stages are listed with their statuses, amounts, and — for Stage 1 — the payment date and Stripe reference.
2. **Given** Stage 2 is "pending", **When** an admin clicks "Mark as Due" for Stage 2, **Then** Stage 2 becomes "due" and the client can pay it.
3. **Given** Stage 1 is "paid", **When** an admin views the stage, **Then** no "Mark as Due" action is available for Stage 1 (it is already beyond "due").
4. **Given** a non-admin (client or reviewer) accesses the admin payment section, **When** the request is processed, **Then** they receive a 403 Forbidden response.

---

### Edge Cases

- What happens if the client closes the browser before being redirected back from Stripe? → Webhook (US2) confirms the payment without the redirect.
- What if Stripe is down when the client attempts to initiate payment? → System shows an error message; no payment record is created; client can retry.
- What if a webhook arrives before the portal has created the payment record? → System logs a warning and responds HTTP 200; no orphan record is created.
- What if Stage 1 is already "due" and an admin attempts to mark it "due" again? → No change; system is idempotent.
- What if a client abandons the Stripe Checkout page and the session expires? → The payment stage remains "due". The next "Pay Now" click creates a new Stripe session and overwrites the old session ID on the record. No "failed" status is set for abandonment.
- What if a client attempts to access a Stripe Checkout URL directly without an active session? → Stripe returns an expired session page; the portal's return URL shows a neutral "payment not completed" message.
- What if a payment is refunded by the admin outside the portal? → Out of scope for Phase 5; refund status reconciliation is a Phase 9 concern.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST maintain three sequential payment stages per visa application, each with a name, amount, and status (pending, due, paid, failed). The "failed" status is set only when a card payment is explicitly declined — not when a Checkout session is abandoned or expires.
- **FR-002**: Payment amounts for each stage MUST be configurable per visa type and NOT hardcoded in application logic.
- **FR-003**: A client MUST only see the "Pay Now" action for stages that are currently "due" — not for pending or already-paid stages.
- **FR-004**: Initiating a payment MUST redirect the client to a Stripe Checkout session; no card data may be collected or stored by the portal.
- **FR-005**: Upon returning from Stripe (success path), the system MUST display a confirmation message to the client; the payment stage status is confirmed by webhook, not by this redirect.
- **FR-006**: System MUST expose a webhook endpoint that receives and verifies payment event notifications from Stripe.
- **FR-007**: Webhook signature verification MUST be performed before any payment state change is applied. Invalid signatures MUST be rejected with HTTP 400.
- **FR-008**: Duplicate webhook events for the same payment MUST be handled idempotently — the stage remains "paid" and no duplicate record is created.
- **FR-009**: Every payment state transition MUST be recorded in the audit log with: stage number, old status, new status, Stripe reference, and actor (system or user).
- **FR-010**: A payment stage MUST NOT be payable more than once. Once marked "paid", it is locked.
- **FR-011**: The client's Payments tab MUST list all three stages with their name, amount, status, and payment date (for paid stages).
- **FR-012**: Admins MUST be able to view payment status for any application, including the Stripe transaction reference for paid stages.
- **FR-013**: The system MUST record the Stripe Checkout Session ID on the payment record at the moment it is created (before the client completes payment) to enable webhook-to-payment matching. If the client initiates a new session for the same stage (e.g., after abandoning the previous one), the stored session ID is overwritten with the new one.
- **FR-014**: Stage 1 MUST be automatically set to "due" when a new visa application is created. Stages 2 and 3 MUST remain "pending" until an admin explicitly marks them "due" via the admin panel.
- **FR-015**: All client-facing payment strings MUST be available in both English and Arabic.
- **FR-016**: The system MUST enforce payment actions via two dedicated permissions: `payments.pay` (granted to the client role — required to initiate a Checkout session) and `payments.manage` (granted to the admin role — required to view payment details and mark stages as due). Reviewers receive neither permission.

### Key Entities

- **Payment**: Represents a single payment stage for an application. Key attributes: application reference, stage number (1, 2, or 3), stage name, amount, currency, status (pending / due / paid / failed), Stripe Checkout Session ID, Stripe Payment Intent ID, payment date, created/updated timestamps.
- **VisaApplication** (extended from Phase 2): Gains a `payments` relationship — one application has up to three payment records (one per stage).
- **VisaType** (extended from Phase 2): Gains stage amount configuration — each visa type defines the three payment amounts.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A client can initiate and complete a payment (from clicking "Pay Now" to seeing confirmation on return) in under 3 minutes under normal network conditions.
- **SC-002**: Payment stage status is updated within 30 seconds of Stripe dispatching the webhook event.
- **SC-003**: Duplicate webhook deliveries (Stripe retries) produce zero duplicate payment records — 100% idempotency rate.
- **SC-004**: All payment state changes are traceable in the audit log; zero unlogged transitions.
- **SC-005**: Stage amounts can be updated by an admin per visa type without requiring code changes or a deployment.
- **SC-006**: An invalid or tampered webhook is rejected 100% of the time — zero unauthorized state changes from unverified events.

---

## Assumptions

- Phase 2 (Client Onboarding) and Phase 3 (Workflow Engine) are complete. A client has an active application before any payment is initiated.
- The Payments tab on the client dashboard was stubbed in Phase 2 — this phase replaces the stub with real content.
- Currency is a single configurable value (e.g., USD or AED) — multi-currency is out of scope for Phase 5.
- Refunds are out of scope. Refunded payments are not tracked in Phase 5.
- The system uses Stripe Checkout (hosted payment page) — not Stripe Elements (embedded card form). Card data never touches the portal server.
- Stage names are configurable strings (e.g., "Application Fee", "Processing Fee", "Visa Fee") — not hardcoded labels.
- The webhook endpoint is excluded from CSRF protection (it receives server-to-server POST requests from Stripe, not browser form submissions).
- Payment failures (card declined, etc.) are recorded but the client can retry by initiating a new Checkout session for the same stage.
- The `under_review` application status transition (deferred from Phase 4) remains deferred to Phase 7.
- Admin payment actions in Phase 5 are: viewing payment status (all stages) and marking Stages 2 or 3 as "due". These are the only write actions available to admins — they do not initiate or process payments on behalf of clients.

---

## Dependencies

- **Phase 2**: `visa_applications` table, client dashboard Payments tab stub, `visa_types` table.
- **Phase 3**: No coupling required — payment stages are triggered by application creation (Stage 1) and admin action (Stages 2 & 3), not by workflow step advancement.
- **Stripe Account**: A Stripe account with test/live API keys and a configured webhook endpoint (pointing to `/payments/webhook`).

---

## Out of Scope (Phase 5)

- Refunds or partial refunds.
- Multi-currency support.
- Invoice or receipt PDF generation.
- Recurring / subscription payments.
- Admin ability to manually mark a payment as "paid" without Stripe processing (manual override).
- Payment analytics or revenue reporting dashboard.
