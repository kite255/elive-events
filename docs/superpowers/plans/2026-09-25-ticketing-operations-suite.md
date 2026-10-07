# Ticketing Operations Suite Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build six coordinated ticketing operations features: All Tickets, Resend Ticket, Check-in History, Capacity Dashboard, Payment Reconciliation, and reusable Audit Logs.

**Architecture:** Reuse existing Ticket, TicketOrder, Payment, TicketType, TicketAccessDeliveryService, TicketAvailabilityService, TicketCheckInService, and event authorization rules. Add focused Filament resources/pages plus a reusable audit subsystem and a reconciliation service that delegates to existing verified payment synchronization/fulfillment paths instead of duplicating payment logic.

**Tech Stack:** Laravel 12.61.0, PHP 8.3, Filament 5.6, PostgreSQL, Redis queues, Pesapal API integration.

**Spec:** `docs/superpowers/specs/2026-09-25-ticketing-operations-suite-design.md`

## Global Constraints

- Refund & Cancellation is out of scope.
- No manual `Mark Paid`, `Force Success`, or unverified ticket issuance action.
- Super Admin has full access.
- Ticket Organizer is restricted to assigned ticketing events.
- Event Manager/Admin follows existing event/report permissions.
- Check-in Officer gets Check-in History only, read-only, for authorized events.
- Resending never creates a new ticket or changes ticket number/public token/QR identity.
- Capacity calculations must reuse the same sold/reserved/unlimited rules as checkout.
- Audit logs must never store QR secrets, API credentials, passwords, access tokens, or webhook secrets.
- Full existing test suite must pass on staging before promotion.

## Review Focus

1. Cross-event access attempts must return no records/actions for ticket organizers, event managers, and check-in officers.
2. Unlimited ticket types (`capacity` null or <= 0) must never display as sold out or negative availability.
3. Manual resend must create a new communication attempt while preserving the original ticket and QR identity.
4. Payment reconciliation actions must reject non-completed payments for fulfillment retry and must verify Pesapal responses through `PaymentService::syncFromGateway()`.
5. Audit metadata must exclude sensitive QR/provider secrets even when the underlying subject contains them.

---

### Task 1: Reusable Audit Log Foundation

**Files:**
- Create: `database/migrations/2026_09_25_200000_create_audit_logs_table.php`
- Create: `app/Models/AuditLog.php`
- Create: `app/Services/Audit/AuditLogService.php`
- Create: `app/Filament/Resources/AuditLogs/AuditLogResource.php`
- Create: `app/Filament/Resources/AuditLogs/Pages/ListAuditLogs.php`
- Create: `app/Filament/Resources/AuditLogs/Tables/AuditLogsTable.php`
- Test: `tests/Feature/Audit/AuditLogTest.php`
- Test: `tests/Feature/Audit/AuditLogResourceTest.php`

**Interfaces:**
- Produces: `AuditLogService::record(string $action, Model $subject, ?User $actor = null, array $metadata = [], ?array $before = null, ?array $after = null): AuditLog`
- Produces: `AuditLog::scopeAccessibleBy(Builder $query, ?User $user): Builder`
- Used later by resend, reconciliation, upgrades, and administrative check-in actions.

- [ ] **Step 1: Write the failing audit persistence test**

```php
public function test_audit_log_records_actor_event_subject_and_metadata(): void
{
    $log = app(AuditLogService::class)->record(
        'ticket.access_resent',
        $ticket,
        $admin,
        ['channels' => ['sms']]
    );

    $this->assertSame('ticket.access_resent', $log->action);
    $this->assertSame($admin->id, $log->actor_id);
    $this->assertSame($ticket->event_id, $log->event_id);
    $this->assertSame(Ticket::class, $log->subject_type);
    $this->assertSame($ticket->id, $log->subject_id);
    $this->assertSame(['sms'], $log->metadata['channels']);
}
```

- [ ] **Step 2: Run the failing test**

Run:
```bash
docker compose exec app php artisan test tests/Feature/Audit/AuditLogTest.php
```
Expected: FAIL because the table/model/service do not exist.

- [ ] **Step 3: Create the migration**

Use PostgreSQL-safe schema definitions:

```php
Schema::create('audit_logs', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
    $table->string('action', 120);
    $table->string('subject_type');
    $table->unsignedBigInteger('subject_id');
    $table->string('reference')->nullable();
    $table->json('before_data')->nullable();
    $table->json('after_data')->nullable();
    $table->json('metadata')->nullable();
    $table->string('ip_address', 64)->nullable();
    $table->text('user_agent')->nullable();
    $table->timestamp('created_at')->useCurrent();

    $table->index(['event_id', 'created_at']);
    $table->index(['actor_id', 'created_at']);
    $table->index(['action', 'created_at']);
    $table->index(['subject_type', 'subject_id']);
});
```

- [ ] **Step 4: Implement `AuditLog` and `AuditLogService`**

Sanitize metadata recursively before insert. Remove keys matching these normalized names:

```php
private const SENSITIVE_KEYS = [
    'qr_token_encrypted',
    'qr_token_hash',
    'qr_secret',
    'access_token',
    'api_key',
    'api_secret',
    'consumer_secret',
    'password',
    'webhook_secret',
];
```

Use the subject's `event_id` when available, otherwise derive from `$subject->event?->getKey()`.

- [ ] **Step 5: Add read-only Filament resource and authorization**

`AuditLogResource` must return `false` from create/edit/delete methods and scope records using `AuditLog::accessibleBy(auth()->user())`.

- [ ] **Step 6: Add access tests**

Test Super Admin sees all logs, Ticket Organizer sees only assigned-event logs, Check-in Officer can see only logs for events they can check in when the page is permitted, and unrelated users see none.

- [ ] **Step 7: Run audit tests**

```bash
docker compose exec app php artisan test tests/Feature/Audit
```
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_09_25_200000_create_audit_logs_table.php app/Models/AuditLog.php app/Services/Audit app/Filament/Resources/AuditLogs tests/Feature/Audit
git commit -m "feat: add reusable audit logs"
```

---

### Task 2: All Tickets Resource

**Files:**
- Create: `app/Filament/Resources/Tickets/TicketResource.php`
- Create: `app/Filament/Resources/Tickets/Pages/ListTickets.php`
- Create: `app/Filament/Resources/Tickets/Tables/TicketsTable.php`
- Modify: `app/Models/Ticket.php`
- Test: `tests/Feature/Tickets/AllTicketsResourceTest.php`

**Interfaces:**
- Consumes existing `Ticket` relationships: `event`, `order`, `ticketType`, `attendee`.
- Produces: `Ticket::scopeAccessibleBy(Builder $query, ?User $user): Builder`.
- Produces navigation item `All Tickets` under `Ticketing`.

- [ ] **Step 1: Write failing authorization/query tests**

Include tests that:

```php
$this->assertTrue(TicketResource::canAccess());
$this->assertCount(1, TicketResource::getEloquentQuery()->get());
$this->assertSame($assignedTicket->id, TicketResource::getEloquentQuery()->first()->id);
```

Use separate events to prove a Ticket Organizer cannot see an unassigned event ticket.

- [ ] **Step 2: Run the failing resource test**

```bash
docker compose exec app php artisan test tests/Feature/Tickets/AllTicketsResourceTest.php
```

- [ ] **Step 3: Add `Ticket::scopeAccessibleBy()`**

Rules:

```php
if (! $user) return $query->whereRaw('1 = 0');
if ($user->isSuperAdmin()) return $query;
if ($user->isTicketOrganizer()) {
    $ids = $user->assignedTicketingEventIds();
    return $ids->isEmpty() ? $query->whereRaw('1 = 0') : $query->whereIn('event_id', $ids);
}
return $query->whereHas('event', fn (Builder $q) => $q->accessibleBy($user));
```

Also add:

```php
public function checkIns(): HasMany
{
    return $this->hasMany(TicketCheckIn::class);
}
```

Task 4 creates the `TicketCheckIn` model before final merge; until then this relationship may be omitted from the first commit if tests do not require it.

- [ ] **Step 4: Build the Filament table**

Columns:

```text
Ticket Number | Event | Buyer/Holder | Ticket Type | Order | Price | Status | Check-in | Issued At
```

Searchable fields must include ticket number and related order buyer/order reference. Filters must include event, ticket type, status, checked-in state, issued date range.

Do not include `qr_token_encrypted` or `qr_token_hash` anywhere in visible output.

- [ ] **Step 5: Add secure row actions**

Actions:
- Open public ticket using `route('tickets.show', ['token' => $record->public_token])` only if that is the existing route name; otherwise use the current public ticket route found in the repository.
- Open related order page where available.
- Copy secure public ticket URL.
- Resend Ticket action is wired in Task 3.

- [ ] **Step 6: Add QR secret regression assertion**

```php
$this->get(TicketResource::getUrl('index'))
    ->assertDontSee($ticket->qr_token_hash)
    ->assertDontSee((string) $ticket->qr_token_encrypted);
```

- [ ] **Step 7: Run tests and commit**

```bash
docker compose exec app php artisan test tests/Feature/Tickets/AllTicketsResourceTest.php
git add app/Models/Ticket.php app/Filament/Resources/Tickets tests/Feature/Tickets/AllTicketsResourceTest.php
git commit -m "feat: add all tickets administration"
```

---

### Task 3: Manual Resend Ticket Workflow

**Files:**
- Modify: `app/Services/Tickets/TicketAccessDeliveryService.php`
- Create: `app/Services/Tickets/ManualTicketResendService.php`
- Modify: `app/Filament/Resources/Tickets/Tables/TicketsTable.php`
- Modify: `app/Filament/Resources/TicketOrders/Tables/TicketOrdersTable.php`
- Modify: `app/Filament/Pages/AdminTicketLookup.php`
- Modify: `resources/views/filament/pages/admin-ticket-lookup.blade.php`
- Test: `tests/Feature/Tickets/ManualTicketResendServiceTest.php`
- Test: `tests/Feature/Tickets/TicketResendAuthorizationTest.php`

**Interfaces:**
- Produces: `ManualTicketResendService::availableChannels(TicketOrder $order): array`
- Produces: `ManualTicketResendService::queue(TicketOrder $order, array $channels, User $actor): array`
- Consumes `AuditLogService::record()`.

- [ ] **Step 1: Write failing manual resend tests**

Verify:
- a paid order with email+phone exposes email/sms and WhatsApp only when configured;
- selecting `sms` queues a new `CommunicationLog` even when an earlier automatic SMS log exists;
- ticket count and ticket identifiers are unchanged;
- an audit log with `ticket.access_resent` is created;
- an unauthorized event is rejected.

- [ ] **Step 2: Run failing tests**

```bash
docker compose exec app php artisan test tests/Feature/Tickets/ManualTicketResendServiceTest.php tests/Feature/Tickets/TicketResendAuthorizationTest.php
```

- [ ] **Step 3: Add explicit manual resend purpose**

In `CommunicationLog` add:

```php
public const PURPOSE_TICKET_ACCESS_MANUAL_RESEND = 'ticket_access_manual_resend';
```

Update `SendTicketAccessLinkJob`/message routing only if current purpose filtering requires it, while preserving the same ticket access URL behavior.

- [ ] **Step 4: Implement manual resend service**

The service must create fresh delivery logs only for the explicitly selected channels and dispatch `SendTicketAccessLinkJob` to existing queues. It must not call `TicketIssuanceService` and must not mutate `Ticket`.

Suggested return shape:

```php
[
    'queued' => ['sms', 'email'],
    'skipped' => [],
]
```

Audit metadata:

```php
[
    'channels' => array_values($channels),
    'order_reference' => $order->order_number,
]
```

- [ ] **Step 5: Wire actions into All Tickets, Ticket Orders, and Manual Lookup**

Use a Filament modal with checkbox/select options limited to `availableChannels()`. Show masked contact details only.

- [ ] **Step 6: Run targeted tests and existing delivery tests**

```bash
docker compose exec app php artisan test \
  tests/Feature/Tickets/ManualTicketResendServiceTest.php \
  tests/Feature/Tickets/TicketResendAuthorizationTest.php \
  tests/Feature/Tickets/TicketAccessDeliveryServiceTest.php \
  tests/Feature/Tickets/SendTicketAccessLinkJobTest.php
```

- [ ] **Step 7: Commit**

```bash
git add app/Services/Tickets app/Models/CommunicationLog.php app/Filament/Resources/Tickets app/Filament/Resources/TicketOrders/Tables/TicketOrdersTable.php app/Filament/Pages/AdminTicketLookup.php resources/views/filament/pages/admin-ticket-lookup.blade.php tests/Feature/Tickets
git commit -m "feat: add manual ticket resend workflow"
```

---

### Task 4: Ticket Check-in History

**Files:**
- Create: `app/Models/TicketCheckIn.php`
- Modify: `app/Models/Ticket.php`
- Create: `app/Filament/Resources/TicketCheckIns/TicketCheckInResource.php`
- Create: `app/Filament/Resources/TicketCheckIns/Pages/ListTicketCheckIns.php`
- Create: `app/Filament/Resources/TicketCheckIns/Tables/TicketCheckInsTable.php`
- Modify: `app/Services/Tickets/TicketCheckInService.php`
- Test: `tests/Feature/Tickets/TicketCheckInHistoryTest.php`

**Interfaces:**
- Maps existing `ticket_check_ins` table to `TicketCheckIn` model.
- Produces `TicketCheckIn::scopeAccessibleBy(Builder $query, ?User $user): Builder`.
- `Ticket::checkIns(): HasMany` uses this model.

- [ ] **Step 1: Write failing model/resource tests**

Verify ticket, event, check-in point, officer relationships; Check-in Officer read-only access; Ticket Organizer assigned-event scoping; unrelated event exclusion.

- [ ] **Step 2: Run failing tests**

```bash
docker compose exec app php artisan test tests/Feature/Tickets/TicketCheckInHistoryTest.php
```

- [ ] **Step 3: Implement `TicketCheckIn` model**

Fillable/casts must match the existing migration exactly:

```php
[
    'event_id', 'ticket_id', 'check_in_point_id', 'checked_in_by',
    'method', 'checked_in_at', 'device_name', 'ip_address', 'note',
]
```

Relationships: `event()`, `ticket()`, `checkInPoint()`, `checkedInBy()`.

- [ ] **Step 4: Change `TicketCheckInService` from raw inserts/reads to model use**

Keep the existing transaction, row lock, unique constraint, and duplicate-entry behavior intact. Replace `DB::table('ticket_check_ins')` with `TicketCheckIn::query()` only where behavior remains identical.

- [ ] **Step 5: Build read-only Filament resource**

Columns:

```text
Time | Event | Ticket Number | Holder | Ticket Type | Check-in Point | Officer | Method
```

Filters: Event, Ticket Type, Officer, Check-in Point, Date Range, Method.

No create/edit/delete actions.

- [ ] **Step 6: Add method fallback test**

Unknown stored method must render a neutral humanized label rather than throwing.

- [ ] **Step 7: Run scanner/check-in regressions**

```bash
docker compose exec app php artisan test \
  tests/Feature/Tickets/TicketCheckInHistoryTest.php \
  tests/Feature/Tickets/TicketCheckInServiceTest.php \
  tests/Feature/Tickets/TicketScannerEndpointTest.php
```

- [ ] **Step 8: Commit**

```bash
git add app/Models/TicketCheckIn.php app/Models/Ticket.php app/Services/Tickets/TicketCheckInService.php app/Filament/Resources/TicketCheckIns tests/Feature/Tickets/TicketCheckInHistoryTest.php
git commit -m "feat: add ticket check-in history"
```

---

### Task 5: Capacity Dashboard

**Files:**
- Create: `app/Services/Tickets/TicketCapacityMetricsService.php`
- Create: `app/Filament/Pages/TicketCapacityDashboard.php`
- Create: `resources/views/filament/pages/ticket-capacity-dashboard.blade.php`
- Test: `tests/Feature/Tickets/TicketCapacityMetricsServiceTest.php`
- Test: `tests/Feature/Tickets/TicketCapacityDashboardPageTest.php`

**Interfaces:**
- Consumes `TicketAvailabilityService::soldQuantity()`, `reservedQuantity()`, `availableQuantity()`.
- Produces: `TicketCapacityMetricsService::forEvent(Event $event): array`.

- [ ] **Step 1: Write failing metrics tests**

Create finite and unlimited ticket types. Assert shape:

```php
[
    'capacity' => 100,
    'sold' => 30,
    'reserved' => 5,
    'available' => 65,
    'checked_in' => 12,
]
```

For unlimited capacity assert `capacity === null` and `available === null`.

Also test expired pending orders do not count as reserved.

- [ ] **Step 2: Run failing tests**

```bash
docker compose exec app php artisan test tests/Feature/Tickets/TicketCapacityMetricsServiceTest.php
```

- [ ] **Step 3: Implement metrics service**

Per ticket type call the existing availability service rather than duplicating sold/reserved calculations. Count check-ins via `ticket_check_ins`/`TicketCheckIn` joined to the ticket type.

Return:

```php
[
    'event' => [...],
    'summary' => [...],
    'ticket_types' => [...],
]
```

- [ ] **Step 4: Build Filament dashboard page**

Use the same event selection/scoping pattern as `OrganizerSalesDashboard`. Navigation label: `Capacity Dashboard`, group: `Ticketing`.

- [ ] **Step 5: Add cross-event and unlimited-capacity tests**

Ensure a Ticket Organizer selecting an unassigned event receives empty/forbidden data rather than metrics.

- [ ] **Step 6: Run tests and commit**

```bash
docker compose exec app php artisan test \
  tests/Feature/Tickets/TicketCapacityMetricsServiceTest.php \
  tests/Feature/Tickets/TicketCapacityDashboardPageTest.php \
  tests/Feature/Tickets/TicketReservationTest.php

git add app/Services/Tickets/TicketCapacityMetricsService.php app/Filament/Pages/TicketCapacityDashboard.php resources/views/filament/pages/ticket-capacity-dashboard.blade.php tests/Feature/Tickets
git commit -m "feat: add ticket capacity dashboard"
```

---

### Task 6: Payment Reconciliation Service and Dashboard

**Files:**
- Create: `app/Services/Payments/PaymentReconciliationService.php`
- Create: `app/Filament/Pages/PaymentReconciliation.php`
- Create: `resources/views/filament/pages/payment-reconciliation.blade.php`
- Modify: `app/Models/Payment.php`
- Test: `tests/Feature/Payments/PaymentReconciliationServiceTest.php`
- Test: `tests/Feature/Payments/PaymentReconciliationPageTest.php`

**Interfaces:**
- Produces: `PaymentReconciliationService::issuesForEvent(Event $event): Collection`
- Produces: `PaymentReconciliationService::resync(Payment $payment, User $actor): Payment`
- Produces: `PaymentReconciliationService::retryFulfillment(Payment $payment, User $actor): Payment`
- Consumes `PaymentService::syncFromGateway()` and `PaymentFulfillmentService::fulfill()`.

- [ ] **Step 1: Write failing reconciliation detection tests**

Cover:
- completed payment + missing `fulfilled_at`;
- completed order payment + ticket count below expected quantity;
- order marked paid with no completed payment where online payment is expected;
- payment amount mismatch against TicketOrder total;
- ticket upgrade payment amount mismatch against `upgrade_amount`;
- currency mismatch.

Each issue should have stable machine code plus human-readable text, for example:

```php
[
    'code' => 'completed_unfulfilled',
    'message' => 'Payment is completed but fulfillment has not finished.',
]
```

- [ ] **Step 2: Run failing tests**

```bash
docker compose exec app php artisan test tests/Feature/Payments/PaymentReconciliationServiceTest.php
```

- [ ] **Step 3: Implement issue analysis without provider fan-out**

Initial page load uses local database state only. Do not call Pesapal for every row.

Expected amount helper:

```php
private function expectedAmount(Payment $payment): ?float
{
    if ($payment->ticket_upgrade_id) return (float) $payment->ticketUpgrade?->upgrade_amount;
    if ($payment->ticket_order_id) return (float) $payment->ticketOrder?->total;
    return null;
}
```

- [ ] **Step 4: Implement safe `resync()`**

Authorize event first, then:

```php
$updated = app(PaymentService::class)->syncFromGateway($payment);
```

Record audit event `payment.resync` with previous/current status and payment reference. Do not accept a status argument from the UI.

- [ ] **Step 5: Implement safe `retryFulfillment()`**

Guard:

```php
if (! $payment->isCompleted()) {
    throw new RuntimeException('Only verified completed payments can be fulfilled.');
}
```

Call existing fulfillment service/job path. Audit `payment.fulfillment_retry`. Refresh and return payment.

- [ ] **Step 6: Build dashboard page**

Summary cards:

```text
Needs Attention | Unfulfilled Completed | Pending Verification | Amount/Currency Mismatches | Resolved Today
```

Rows include payment reference, event, purchaser, local status, order/upgrade reference, expected vs actual amount/currency, ticket count, issue text, actions.

No `Mark Paid` button exists in page class or Blade.

- [ ] **Step 7: Add security/action tests**

Assert:

```php
$this->expectException(RuntimeException::class);
$service->retryFulfillment($pendingPayment, $admin);
```

Mock the gateway for `resync()` and assert `PaymentService` verification rejects mismatched provider amount/reference using existing behavior.

- [ ] **Step 8: Run payment regressions**

```bash
docker compose exec app php artisan test \
  tests/Feature/Payments/PaymentReconciliationServiceTest.php \
  tests/Feature/Payments/PaymentReconciliationPageTest.php \
  tests/Feature/Payments/ReconcilePendingPaymentsCommandTest.php \
  tests/Feature/Payments/TicketPaymentFulfillmentTest.php \
  tests/Feature/Payments/TicketUpgradeFulfillmentTest.php
```

- [ ] **Step 9: Commit**

```bash
git add app/Services/Payments/PaymentReconciliationService.php app/Filament/Pages/PaymentReconciliation.php resources/views/filament/pages/payment-reconciliation.blade.php app/Models/Payment.php tests/Feature/Payments
git commit -m "feat: add payment reconciliation dashboard"
```

---

### Task 7: Wire Audit Events into Existing Ticket Upgrade and Operational Actions

**Files:**
- Modify: `app/Services/Tickets/TicketUpgradeService.php`
- Modify: `app/Services/Tickets/TicketUpgradeFulfillmentService.php`
- Modify: `app/Services/Tickets/TicketCheckInService.php`
- Modify: `app/Services/Tickets/ManualTicketResendService.php`
- Test: `tests/Feature/Audit/TicketOperationsAuditTest.php`

**Interfaces:**
- Consumes `AuditLogService::record()` from Task 1.

- [ ] **Step 1: Write failing audit integration tests**

Assert creation of:

```text
ticket.upgrade.created
ticket.upgrade.completed
ticket.access_resent
payment.resync
payment.fulfillment_retry
```

For check-in, record `ticket.checked_in` only if this does not duplicate an already existing event/audit mechanism. The record should include method and check-in point but not QR token/hash.

- [ ] **Step 2: Run failing tests**

```bash
docker compose exec app php artisan test tests/Feature/Audit/TicketOperationsAuditTest.php
```

- [ ] **Step 3: Add audit calls at successful state boundaries**

For upgrade creation:

```php
$audit->record('ticket.upgrade.created', $upgrade, $actor, [
    'ticket_number' => $ticket->ticket_number,
    'from_ticket_type_id' => $upgrade->from_ticket_type_id,
    'to_ticket_type_id' => $upgrade->to_ticket_type_id,
    'amount' => (float) $upgrade->upgrade_amount,
    'currency' => $upgrade->currency,
]);
```

For completion, record after the ticket type change succeeds inside/after the same controlled transaction boundary.

- [ ] **Step 4: Add secret-exclusion assertions**

Serialize audit metadata and assert it does not contain the ticket's `qr_token_hash`, decrypted QR secret, Pesapal consumer secret, or WhatsApp access token.

- [ ] **Step 5: Run tests and commit**

```bash
docker compose exec app php artisan test tests/Feature/Audit/TicketOperationsAuditTest.php tests/Feature/Payments/TicketUpgradeFulfillmentTest.php

git add app/Services tests/Feature/Audit
git commit -m "feat: audit ticketing operations"
```

---

### Task 8: Navigation, Authorization, Full Regression, and Staging Rollout

**Files:**
- Modify as needed: navigation sort values in new Filament resources/pages
- Modify as needed: `app/Providers/Filament/AdminPanelProvider.php` only if page/resource discovery is not automatic
- Test: `tests/Feature/Tickets/TicketingOperationsNavigationTest.php`

**Interfaces:**
- Final navigation order:

```text
All Tickets
Manual Lookup
Sales Dashboard
Capacity Dashboard
Payment Reconciliation
Check-in History
Ticket Orders
Ticket Types
Ticket Template Library
Event Ticket Templates
Audit Logs
```

- [ ] **Step 1: Add navigation/access test**

Test each role against the approved permission matrix. Check-in Officer must not see Payment Reconciliation or Audit Logs unless explicitly permitted by the spec's event-scoped read-only rule implemented in Task 1.

- [ ] **Step 2: Run targeted feature groups**

```bash
docker compose exec app php artisan test tests/Feature/Tickets tests/Feature/Payments tests/Feature/Audit
```
Expected: PASS with zero failures.

- [ ] **Step 3: Run static migration check**

```bash
docker compose exec app php artisan migrate:status
```
Confirm the audit migration is present and pending locally before applying if not yet migrated.

- [ ] **Step 4: Apply local/staging-safe migration in development environment**

```bash
docker compose exec app php artisan migrate
```

- [ ] **Step 5: Run the complete test suite**

```bash
docker compose exec app php artisan test
```
Expected: zero failures.

- [ ] **Step 6: Browser smoke test on staging**

Verify with representative users:

```text
Super Admin: all six features
Ticket Organizer: assigned event only
Event Manager: permitted event only
Check-in Officer: Check-in History only/read-only
Unrelated user: no access
```

Also verify:
- All Tickets never displays QR secrets.
- Resend sends the same ticket link and does not issue a ticket.
- Capacity numbers match public checkout availability.
- Payment Re-sync cannot be supplied a manual result.
- Retry Fulfillment is hidden/rejected for non-completed payments.
- Audit Logs are read-only.

- [ ] **Step 7: Check production-impact diff**

```bash
git diff --check origin/staging...HEAD
git status --short
```
Expected: no whitespace errors and clean working tree after commits.

- [ ] **Step 8: Commit final navigation/test adjustments**

```bash
git add app/Filament tests/Feature/Tickets/TicketingOperationsNavigationTest.php
git commit -m "test: verify ticketing operations access"
```

- [ ] **Step 9: Push staging**

```bash
git push origin staging
```

- [ ] **Step 10: Staging deployment commands**

Run inside the staging application container after deployment:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan route:list | grep -E "ticket|reconciliation|audit"
```

Then repeat the browser smoke tests before any main-branch promotion.

---

## Self-Review Notes

### Spec coverage

- All Tickets: Task 2.
- Resend Ticket: Task 3.
- Check-in History: Task 4.
- Capacity Dashboard: Task 5.
- Payment Reconciliation: Task 6.
- Reusable Audit Logs: Tasks 1 and 7.
- Permission matrix: Tasks 1, 2, 4, 5, 6, and final Task 8.
- No manual paid override: Task 6 tests/UI constraint.
- Ticket-upgrade reconciliation compatibility: Task 6.
- Security/no QR/provider secrets: Tasks 1, 2, 7.
- Staging-first rollout/full suite: Task 8.

### Placeholder scan

The plan contains no `TBD`, deferred implementation placeholders, or unspecified test steps. Every feature task names concrete files, interfaces, commands, and expected behavior.

### Type/interface consistency

- `AuditLogService::record()` is defined in Task 1 and consumed by Tasks 3, 6, and 7.
- `TicketCheckIn` is defined in Task 4 and used by `Ticket::checkIns()` and capacity/check-in reporting.
- `PaymentReconciliationService` delegates to existing `PaymentService` and `PaymentFulfillmentService` instead of creating a second payment state machine.
- Capacity metrics consume the existing `TicketAvailabilityService` methods and preserve its unlimited-capacity semantics.

### Review Focus coverage

- Cross-event access: Tasks 2, 4, 5, 6, 8.
- Unlimited capacity: Task 5.
- Manual resend does not alter ticket identity: Task 3.
- Verified-only payment recovery: Task 6.
- Secret redaction in audit logs: Tasks 1 and 7.
