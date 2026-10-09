# Ticketing Operations Suite Design

Date: 2026-09-25
Status: Approved design, pending implementation plan
Target: eLive Events
Branch: staging

## 1. Purpose

This specification defines six operational ticketing features for eLive Events:

1. All Tickets
2. Resend Ticket
3. Check-in History
4. Capacity Dashboard
5. Payment Reconciliation Dashboard
6. Reusable Audit Logs

Refund and cancellation workflows are explicitly out of scope for this package.

The goal is to give event and ticketing staff a complete operational view of issued tickets, delivery, venue entry, inventory/capacity, payment exceptions, and administrative actions without duplicating the existing ticket, order, payment, communication, or check-in data models.

## 2. Design Principles

- Reuse existing Ticket, TicketOrder, Payment, TicketCheckIn, CommunicationLog, TicketType, Event, and user authorization models/services wherever possible.
- Avoid duplicate sources of truth.
- Keep payment trust server-side. No UI action may manually mark an online Pesapal payment as paid.
- Preserve current secure ticket identifiers and QR behavior.
- Respect event-level authorization consistently across all new pages and actions.
- Make operational pages searchable and filterable without exposing ticket QR secrets or other sensitive credentials.
- Prefer idempotent recovery actions for communications and payment fulfillment.
- Audit administrative actions that change or retry system behavior.

## 3. Navigation Architecture

The approved approach is hybrid: separate operational lists plus dashboards for aggregated views.

Ticketing navigation should become:

- All Tickets
- Manual Lookup
- Sales Dashboard
- Capacity Dashboard
- Payment Reconciliation
- Check-in History
- Ticket Orders
- Ticket Types
- Ticket Template Library
- Event Ticket Templates
- Audit Logs

Resend Ticket is an action, not a standalone navigation page.

## 4. Authorization Model

### Super Admin

- Full access to all six features and all events.

### Ticket Organizer

- Access only to assigned ticketing events.
- May view All Tickets, Capacity Dashboard, Payment Reconciliation, Check-in History, and allowed Audit Logs for assigned events.
- May use Resend Ticket for assigned events.

### Event Manager / Event Admin

- Access according to existing event/report permission rules.
- New pages must call the same established event authorization logic rather than inventing a parallel permission system.

### Check-in Officer

- Read-only access to Check-in History for assigned/authorized events.
- No access to payment reconciliation, ticket resend, capacity administration, or global audit data unless existing policy explicitly grants it.

### Other Users

- No access to these operational tools.

Authorization must be enforced at both navigation/page access and query level so hidden navigation cannot be bypassed by direct URLs.

## 5. Feature 1: All Tickets

### Purpose

Provide one central list of every issued ticket as an individual record rather than only showing purchase orders.

### Data Source

Use the existing `tickets` table and relationships to Event, TicketType, TicketOrder, TicketOrderItem, holder/attendee where present, and check-in records.

No new ticket-copy table is required.

### Primary Columns

- Ticket Number
- Event
- Buyer / Holder
- Ticket Type
- Order Reference
- Price / Currency
- Ticket Status
- Check-in Status
- Checked-in At, where applicable
- Issued At
- Actions

### Search

Search should support:

- Ticket number
- Order reference
- Buyer name
- Holder name
- Buyer phone
- Buyer email

### Filters

- Event
- Ticket Type
- Ticket Status
- Checked In / Not Checked In
- Issue date / date range

### Row Actions

- View Ticket
- View Order
- Copy Secure Link
- Resend Ticket
- View Check-in History
- View Audit History

### Security Requirements

- Never expose raw QR secrets or QR token hashes in table output.
- Public access must continue to use the existing secure public ticket/order tokens.
- Event scoping must be applied before search/filter results are returned.

## 6. Feature 2: Resend Ticket

### Purpose

Allow staff to resend access to an existing ticket/order without issuing a replacement ticket or changing its QR identity.

### Entry Points

Resend Ticket should be available from:

- All Tickets
- Ticket Orders
- Manual Lookup

### Delivery Channels

The modal/action should show only channels available for the purchaser/order contact data and current system configuration:

- WhatsApp
- SMS
- Email
- All available channels

### Confirmation UI

Before dispatch, show:

- Event
- Ticket or order reference
- Recipient name
- Masked phone/email
- Selected channel(s)

### Delivery Behavior

- Reuse the existing ticket access delivery infrastructure and communication logs.
- Resending must not create a new ticket.
- Resending must not change public token, ticket number, QR secret/hash, or ticket type.
- Manual resend attempts should be safe to retry.
- Existing automatic delivery deduplication must not prevent an explicitly requested manual resend; manual attempts need their own audit context and communication attempt handling.

### Audit Event

At minimum record:

- action: `ticket.access_resent`
- actor
- event
- ticket/order subject
- selected channels
- masked destination metadata or non-sensitive delivery context
- timestamp

## 7. Feature 3: Check-in History

### Purpose

Provide a searchable, read-only operational history of ticket entry attempts/check-ins.

### Data Source

Use existing ticket check-in records and relationships to:

- Ticket
- Event
- Ticket Type
- Check-in point/gate where available
- User/officer

### Columns

- Timestamp
- Event
- Ticket Number
- Holder / Buyer
- Ticket Type
- Check-in Point / Gate
- Officer
- Method
- Result

### Supported Methods

Display the recorded method using existing values where available, including examples such as:

- QR
- Ticket Number
- Manual Lookup

Do not invent a value when older records do not contain a method; show a neutral fallback such as `Unknown`.

### Filters

- Event
- Ticket Type
- Officer
- Check-in Point / Gate
- Date / Date Range
- Result, if existing data records distinct results

### Search

- Ticket number
- Order reference if relationship allows
- Holder/buyer name

### Version 1 Constraint

The page is read-only. No destructive check-in deletion, undo, or editing is part of this version.

## 8. Feature 4: Capacity Dashboard

### Purpose

Give organizers a reliable view of ticket inventory and venue entry progress using the same rules as checkout.

### Event Summary Metrics

- Event Capacity, when meaningful from ticket types
- Tickets Sold
- Active Reservations
- Available Capacity
- Checked In

### Per-Ticket-Type Metrics

- Ticket Type
- Capacity
- Sold
- Reserved
- Available
- Checked In
- Remaining

### Calculation Rules

Capacity metrics must reuse or centralize the same business rules currently used by ticket checkout/order reservation logic.

Definitions:

- Sold: issued/eligible tickets counted by existing sold-count semantics.
- Reserved: quantities held by active, unexpired pending/processing orders according to existing reservation logic.
- Available / Remaining: capacity minus sold and active reservations where capacity is finite.
- Checked In: tickets with successful recorded check-in / used state according to current scanner semantics.

### Unlimited Capacity

Ticket types where current business logic treats null or non-positive capacity as unlimited must display `Unlimited` and must not be shown as sold out because capacity is zero.

### Consistency Requirement

The Capacity Dashboard must not implement an independent inventory formula that can disagree with public checkout.

## 9. Feature 5: Payment Reconciliation Dashboard

### Purpose

Surface payment/order/ticket inconsistencies and provide safe recovery actions without weakening provider verification.

### Scope

Focus on online Pesapal payments and the existing payment/order/ticket fulfillment flow.

### Summary Indicators

- Needs Attention
- Unfulfilled Completed Payments
- Pending Provider Verification
- Amount/Currency Mismatches
- Resolved Today, where resolution tracking is available from audit/reconciliation metadata

### Reconciliation Conditions

At minimum detect or clearly surface cases such as:

- Provider-verified completed payment while local order remains pending/processing
- Provider-verified completed payment but expected tickets were not issued
- Local paid order without a matching completed payment where online payment is required
- Completed payment with `fulfilled_at` missing
- Payment amount mismatch against order/upgrade expected amount
- Currency mismatch
- Missing/inconsistent provider tracking/reference fields
- Failed fulfillment job or retryable incomplete fulfillment state when detectable from current models/jobs

### Row Detail

Each exception row should show enough context to investigate safely:

- Payment reference
- Provider tracking/reference
- Event
- Customer / purchaser
- Local payment status
- Provider status when freshly synchronized or available
- Order reference/status
- Expected amount/currency
- Ticket issuance count / expected quantity where applicable
- Fulfillment status
- Human-readable issue description

### Safe Actions

#### Re-sync with Pesapal

- Calls the existing provider synchronization/verification path.
- Fetches current provider status.
- Validates merchant reference, tracking ID, amount, and currency using existing server-side rules.
- Applies only statuses confirmed by the provider.
- Must not trust browser/user input for the result.
- Must be audited.

#### Retry Fulfillment

- Available only when local payment is already verified `completed` under normal payment validation rules.
- Reuses the existing PaymentFulfillmentService / job path.
- Must remain idempotent.
- Must not create duplicate tickets or duplicate upgrade fulfillment.
- Must be audited.

#### View Payment / View Order

- Investigation-only navigation actions.

### Explicitly Forbidden Actions

Do not add:

- Mark Paid
- Force Success
- Issue Tickets Without Verified Payment
- Browser-supplied payment status override

### Ticket Upgrade Compatibility

Reconciliation must recognize both normal ticket-order payments and ticket-upgrade payments. Retry fulfillment must dispatch to the correct fulfillment path based on the existing payment relationships.

## 10. Feature 6: Reusable Audit Logs

### Purpose

Create one reusable audit subsystem that can support the broader eLive Events application over time, while initially wiring it to the six ticketing operational features.

### Data Model

Create an `audit_logs` table/model with fields sufficient for:

- id
- actor/user id, nullable for system actions
- event id, nullable when global/not event-specific
- action string
- subject type
- subject id
- reference/display value, nullable
- before data, nullable JSON
- after data, nullable JSON
- metadata/context, nullable JSON
- ip address, nullable
- user agent, nullable
- created_at

No update timestamp is required if audit records are append-only.

### Behavior

- Audit records are immutable from the admin UI.
- Audit failures should not silently corrupt the primary business transaction. Where possible, write audit records inside the same transaction for critical state changes, or use a safe post-action strategy where transaction coupling is inappropriate.
- Sensitive values such as QR secrets, access tokens, API credentials, passwords, full webhook secrets, and raw provider secrets must never be written into audit metadata.

### Initial Actions

At minimum wire auditing to:

- `ticket.access_resent`
- `ticket.upgrade.created`
- `ticket.upgrade.completed`
- `payment.resync`
- `payment.fulfillment_retry`
- relevant administrative check-in actions introduced by this suite

Additional existing actions may be migrated to the audit system later.

### Audit Log Page

Read-only Filament page/resource with:

Columns:

- Timestamp
- User / Actor
- Action
- Event
- Resource / Subject
- Reference
- IP
- Details

Filters:

- Event
- Actor
- Action
- Subject type
- Date range

Details view should render structured metadata clearly without exposing protected secrets.

## 11. Shared Event Scoping Service / Pattern

Because these pages all need the same event authorization behavior, implementation should centralize or consistently reuse existing methods such as:

- super-admin all-event access
- ticket-organizer assigned event IDs
- existing `canViewEventReports` or equivalent event-manager checks

Do not copy subtly different authorization queries into every page if an existing shared service/policy can be extended safely.

## 12. Data Integrity and Concurrency

- All Tickets is read-only except explicit safe actions such as resend/copy/view.
- Capacity values are computed from authoritative records, not manually edited counters.
- Retry Fulfillment must use existing row locking/idempotency mechanisms where already implemented.
- Re-sync must go through provider verification and existing payment status transition rules.
- Manual resend must not mutate ticket issuance or QR identity.
- Audit records must preserve actor and context for administrative recovery actions.

## 13. Performance Considerations

- Use eager loading to avoid N+1 queries in All Tickets, Check-in History, and Audit Logs.
- Paginate operational lists.
- Index audit log fields used for common filtering, especially event, actor, action, subject, and created_at.
- Reconciliation dashboard should avoid live provider API calls for every row on initial page load. Provider synchronization occurs when explicitly requested or through existing reconciliation jobs/commands.
- Capacity dashboard should aggregate efficiently and avoid per-ticket-type query loops where practical.

## 14. UI/UX Requirements

- Follow existing Filament visual and navigation patterns.
- Use clear status badges for ticket, check-in, payment, and reconciliation states.
- Mask customer contact data where a full value is not operationally necessary.
- Destructive-looking or recovery actions require confirmation modals with clear consequences.
- Re-sync and Retry Fulfillment should display success/failure notifications with a useful message and reference.
- Empty states should explain whether no records exist versus no records are visible due to filters.

## 15. Testing Requirements

Implementation must add automated coverage for at least:

### All Tickets

- resource/page availability
- authorization by role/event
- event scoping
- search/filter behavior
- secure output does not expose QR secrets

### Resend Ticket

- available-channel selection
- correct delivery service invocation
- no new ticket issuance
- manual resend audit log
- authorization

### Check-in History

- read-only page availability
- event scoping
- officer/check-in/ticket fields
- check-in officer access restrictions

### Capacity Dashboard

- sold/reserved/available/check-in calculations
- active reservation expiration handling
- unlimited-capacity semantics
- isolation across events

### Payment Reconciliation

- identifies completed-but-unfulfilled payment
- identifies amount/currency mismatch
- Re-sync uses provider verification path
- Retry Fulfillment only works for verified completed payments
- idempotent fulfillment retry
- no manual paid override exists
- ticket-upgrade payment compatibility

### Audit Logs

- records expected actor/action/event/subject/context
- access/scoping by role
- sensitive values are excluded
- records are not editable/deletable through normal admin UI

The full existing test suite must pass before promotion from staging to main.

## 16. Rollout Strategy

1. Implement and test on staging.
2. Run new targeted tests.
3. Run the full Laravel test suite.
4. Apply new audit-log migration on staging.
5. Perform browser smoke tests for all six features with representative roles.
6. Validate a safe Pesapal Re-sync scenario and a completed-payment Retry Fulfillment scenario without creating duplicate tickets.
7. Promote verified changes to main using the project's safe branch integration process.
8. Run production migrations and clear caches.
9. Smoke test production pages without forcing live payment state changes.

## 17. Out of Scope for This Version

- Refund & Cancellation workflows
- CSV/Excel exports
- Ticket transfers
- Promo codes
- Seat maps
- Waitlists
- Offline scanner mode
- Manual paid overrides
- Destructive check-in editing/undo
- Broad historical migration of every existing action into audit logs

## 18. Acceptance Criteria

The suite is considered complete when:

- Authorized staff can view every issued ticket they are permitted to access from All Tickets.
- Staff can resend existing ticket access without changing ticket/QR identity.
- Check-in history is visible and read-only according to role permissions.
- Capacity figures match the same reservation/sold rules used by checkout.
- Payment reconciliation reliably identifies common inconsistencies.
- Re-sync with Pesapal uses provider-verified data only.
- Retry Fulfillment cannot run unless payment is already verified completed and does not duplicate issuance.
- No manual Mark Paid or equivalent bypass exists.
- Audit logs capture required administrative recovery/resend actions and remain read-only.
- Event scoping prevents cross-event access by ticket organizers/managers/check-in officers.
- No QR secrets, access tokens, or provider credentials are exposed in admin lists or audit metadata.
- All targeted tests and the full existing test suite pass on staging before promotion.
