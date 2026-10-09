# Ticket Upgrades Implementation Plan

> **Spec:** `docs/superpowers/specs/2026-09-25-ticket-upgrades.md`

**Goal:** Implement secure administrator-initiated ticket upgrades that charge only the tier difference, verify Pesapal payment server-side, upgrade the same unused ticket atomically, notify the buyer, and report upgrade revenue separately.

**Architecture:** Add a dedicated `TicketUpgrade` aggregate linked to one existing `Ticket`, `TicketOrder`, source/target `TicketType`, and one or more payments. Extend the existing generic payment flow so upgrade payments use the same Pesapal start/sync/callback/IPN pipeline while routing completed upgrade payments into a dedicated idempotent fulfillment service. Keep original order/order-item accounting immutable.

**Tech stack:** Laravel 12, Eloquent, PostgreSQL, Filament, queued communications, Pesapal, PHPUnit/Pest-style Laravel feature tests.

## Global Constraints

- Implement first on `staging` only.
- Follow the approved design spec exactly.
- Never mark an upgrade complete from a browser redirect alone.
- Never issue a second active ticket during an upgrade.
- Never change the original order item to pretend the buyer originally bought the target tier.
- Never allow upgrades after ticket check-in.
- Server-side validation is authoritative even when the UI hides an invalid option.
- All completion logic must be idempotent and transaction-safe.
- New behavior requires tests before implementation changes where practical.
- The GitHub connector does not provide a PHP runtime; repository tests can be authored and inspected here, but execution must be performed in the project runtime before production promotion.

## Task 1: Persist Ticket Upgrades and Payment Linkage

**Files:**
- Create: `database/migrations/2026_09_25_170000_create_ticket_upgrades_table.php`
- Create: `database/migrations/2026_09_25_170100_add_ticket_upgrade_id_to_payments_table.php`
- Create: `app/Models/TicketUpgrade.php`
- Modify: `app/Models/Ticket.php`
- Modify: `app/Models/TicketOrder.php`
- Modify: `app/Models/TicketType.php`
- Modify: `app/Models/Payment.php`
- Create: `tests/Feature/Tickets/TicketUpgradeModelTest.php`

**RED:** Add tests asserting status constants/casts/relations, random public token generation, one active upgrade query semantics, payment relation, and that historical ticket/order relations remain intact.

**GREEN:** Add schema and Eloquent relations. `payments.ticket_upgrade_id` is nullable and independently indexed/foreign-keyed. `TicketUpgrade` stores source/target price snapshots, balance, currency, lifecycle timestamps, financial snapshot fields, metadata, and initiating/completing user IDs.

**Verification:** `php artisan test tests/Feature/Tickets/TicketUpgradeModelTest.php`

## Task 2: Eligibility, Upgrade Creation, and Secure References

**Files:**
- Create: `app/Services/Tickets/TicketUpgradeService.php`
- Modify: `app/Services/Payments/PaymentReferenceService.php` only if needed for upgrade-facing reference generation; otherwise keep payment reference generation unchanged.
- Create: `tests/Feature/Tickets/TicketUpgradeServiceTest.php`

**RED:** Cover:
- Regular -> VIP allowed when issued, unused, paid, same event/currency, higher price, capacity available.
- used/checked-in ticket rejected.
- cancelled/refunded ticket rejected.
- unpaid order rejected.
- same/lower price rejected.
- cross-event target rejected.
- inactive target rejected.
- sold-out target rejected.
- unresolved duplicate upgrade rejected/reused deterministically.
- server calculates balance and ignores caller-provided amount.

**GREEN:** Add `TicketUpgradeService::create(...)`, `eligibleTargets(...)`, and authorization-neutral domain validation helpers. Generate random opaque public token and modern `ELV-UPG-{EVENT}-{RANDOM}` reference. Default expiry should be bounded and recorded.

**Verification:** `php artisan test tests/Feature/Tickets/TicketUpgradeServiceTest.php`

## Task 3: Create and Start Upgrade Payments

**Files:**
- Modify: `app/Services/Payments/PaymentService.php`
- Modify: `app/Models/Payment.php`
- Modify: `app/Services/Payments/PesapalService.php` if its customer-data resolver assumes attendee/order only.
- Create: `tests/Feature/Payments/TicketUpgradePaymentTest.php`

**RED:** Cover upgrade payment creation, amount/currency from immutable upgrade snapshot, reuse of pending/processing upgrade payment, no `ticket_order_id` on upgrade payment, Pesapal customer identity sourced from the linked order, processing status propagation to upgrade, and expired/completed upgrade start protection.

**GREEN:** Add `createForTicketUpgrade()` and teach generic payment start logic how to validate/transition upgrade payments without treating them as ticket-order reservations. Reuse existing gateway reference verification.

**Verification:** `php artisan test tests/Feature/Payments/TicketUpgradePaymentTest.php`

## Task 4: Public Secure Upgrade Page

**Files:**
- Create: `app/Http/Controllers/PublicTicketUpgradeController.php`
- Create: `resources/views/public/tickets/upgrade.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Payments/PaymentController.php`
- Create: `tests/Feature/Tickets/PublicTicketUpgradePageTest.php`

**RED:** Cover opaque-token lookup, no numeric-ID route, masked buyer contact, correct price/balance rendering, payment CTA to existing payment route, completed/expired state rendering, invalid token 404, and no sensitive QR/token material in HTML.

**GREEN:** Add throttled `GET /upgrade/{token}` and a POST/pay action if needed to create/reuse the payment server-side. Extend payment status eager loading so upgrade context can be displayed safely after payment.

**Verification:** `php artisan test tests/Feature/Tickets/PublicTicketUpgradePageTest.php`

## Task 5: Verified, Atomic, Idempotent Upgrade Fulfillment

**Files:**
- Create: `app/Services/Tickets/TicketUpgradeFulfillmentService.php`
- Modify: `app/Services/Payments/PaymentFulfillmentService.php`
- Modify: `app/Services/Payments/PaymentService.php` only where gateway sync lifecycle must mirror upgrade status.
- Create: `tests/Feature/Payments/TicketUpgradeFulfillmentTest.php`

**RED:** Cover:
- only completed verified payment can fulfill.
- amount/currency mismatch rejected.
- ticket, upgrade, and target tier are locked/revalidated.
- used ticket cannot be upgraded even if payment completed later.
- capacity is rechecked at fulfillment.
- successful fulfillment changes same ticket ID to target tier and target price.
- ticket number/public token/QR hashes remain unchanged.
- original order item remains unchanged.
- upgrade becomes completed with timestamp.
- repeated fulfillment is no-op/idempotent.
- capacity-loss case does not oversell and leaves an auditable manual-review condition.

**GREEN:** Route `ticket_upgrade_id` payments before order/attendee fulfillment. Implement transaction-safe fulfillment with deterministic locking and only mark payment `fulfilled_at` after successful upgrade completion.

**Verification:** `php artisan test tests/Feature/Payments/TicketUpgradeFulfillmentTest.php`

## Task 6: Admin Manual Lookup Upgrade Action

**Files:**
- Modify: `app/Filament/Pages/AdminTicketLookup.php`
- Modify: `app/Services/Tickets/AdminTicketLookupService.php`
- Modify: `resources/views/filament/pages/admin-ticket-lookup.blade.php`
- Create: `tests/Feature/Tickets/AdminTicketUpgradeActionTest.php`

**RED:** Cover event-scoped authorization, eligible target list, no action for checked-in/ineligible ticket, server-side reauthorization by ticket ID, creation of upgrade/payment, and return/display of secure customer upgrade URL.

**GREEN:** Add an Upgrade Ticket action/modal to each eligible lookup result. Current/target price and balance are displayed read-only. On confirmation create the upgrade and linked payment and show the secure link for sending/copying to the customer.

**Verification:** `php artisan test tests/Feature/Tickets/AdminTicketUpgradeActionTest.php`

## Task 7: Upgrade Completion Delivery

**Files:**
- Modify: `app/Models/CommunicationLog.php`
- Modify: `app/Services/Tickets/TicketAccessDeliveryService.php`
- Modify: `app/Services/Tickets/TicketAccessMessageFactory.php`
- Modify: `app/Jobs/SendTicketAccessLinkJob.php` if purpose routing is explicit.
- Create: `tests/Feature/Tickets/TicketUpgradeDeliveryTest.php`

**RED:** Cover separate `ticket_upgrade_completed` purpose, exactly-once per channel/upgrade, upgraded tier reflected in message/ticket page, and no collision with original `ticket_access` dedupe.

**GREEN:** Add upgrade-specific delivery purpose and queueing method using the existing buyer contacts and channel queues. Include order/ticket URL and upgraded tier without exposing QR secrets.

**Verification:** `php artisan test tests/Feature/Tickets/TicketUpgradeDeliveryTest.php`

## Task 8: Upgrade Metrics and Finance Visibility

**Files:**
- Modify: `app/Services/Tickets/OrganizerSalesMetricsService.php`
- Modify: `app/Filament/Pages/OrganizerSalesDashboard.php`
- Modify: `resources/views/filament/pages/organizer-sales-dashboard.blade.php`
- Modify finance services/views only if existing gross/net totals are intended to include all completed payment purposes.
- Modify/Create: `tests/Feature/Tickets/OrganizerSalesMetricsServiceTest.php`
- Create: `tests/Feature/Tickets/TicketUpgradeReportingTest.php`

**RED:** Cover completed-upgrade count, pending/processing count, upgrade gross revenue, upgrade path breakdown, and no rewriting/double-counting of original order revenue.

**GREEN:** Add clearly labelled upgrade metrics. Keep original ticket-sale metrics intact and show upgrade revenue separately; where a total-event-revenue figure exists, include upgrade revenue only when label/definition makes that explicit.

**Verification:** `php artisan test tests/Feature/Tickets/OrganizerSalesMetricsServiceTest.php tests/Feature/Tickets/TicketUpgradeReportingTest.php`

## Task 9: Regression and Security Verification

**Files:**
- Existing ticket/payment/check-in tests plus all new upgrade tests.

**Verification commands:**

```bash
php artisan test tests/Feature/Tickets/TicketUpgradeModelTest.php \
  tests/Feature/Tickets/TicketUpgradeServiceTest.php \
  tests/Feature/Tickets/PublicTicketUpgradePageTest.php \
  tests/Feature/Tickets/AdminTicketUpgradeActionTest.php \
  tests/Feature/Tickets/TicketUpgradeDeliveryTest.php \
  tests/Feature/Tickets/TicketUpgradeReportingTest.php \
  tests/Feature/Payments/TicketUpgradePaymentTest.php \
  tests/Feature/Payments/TicketUpgradeFulfillmentTest.php

php artisan test tests/Feature/Tickets tests/Feature/Payments
php artisan test
```

Also run:

```bash
php artisan route:list | grep -E 'upgrade|payments'
php artisan migrate:status
php artisan optimize:clear
```

Confirm manually in staging:

1. Create/purchase a Regular ticket and confirm payment.
2. Find it in Ticketing > Manual Lookup.
3. Create Regular -> VIP upgrade.
4. Open secure upgrade URL and verify masked identity and exact difference.
5. Pay through Pesapal sandbox/staging.
6. Confirm browser redirect alone does not alter ticket before backend verification.
7. Confirm verified payment upgrades the same ticket, VIP capacity decreases logically, QR remains valid, and updated ticket is delivered once.
8. Scan a ticket, then confirm Upgrade Ticket is unavailable and server rejects a forced request.
9. Confirm dashboard reports original sale and upgrade revenue separately.

## Production Promotion Gate

Do not promote to `main` until migrations have been reviewed, all automated tests above pass in the actual Laravel runtime, a Pesapal staging/sandbox upgrade has completed end-to-end, and a post-check-in upgrade attempt has been verified blocked.