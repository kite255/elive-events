# Ticket Upgrades Design

## Goal

Add a secure, auditable ticket-upgrade workflow to eLive Events so an authorized administrator can upgrade an already-issued ticket, such as Regular to VIP, while charging only the price difference and preserving the original sale history.

## Product Rules

- Upgrades are initiated by an authorized administrator from an existing ticket.
- Version 1 supports upgrades only to a higher-priced ticket type for the same event.
- Downgrades and refunds are out of scope.
- The original ticket order and order item remain historical records of what was originally purchased.
- The same `Ticket` record is upgraded. Do not issue a second active ticket for the upgrade.
- Keep the existing ticket number, public token, and QR credential so duplicate-entry risk is not introduced.
- An upgrade is blocked if the ticket has already been checked in or has status `used`.
- An upgrade is blocked for cancelled or refunded tickets.
- The parent ticket order must already be paid.
- The target ticket type must belong to the same event, be active, use the same currency, cost more than the ticket's current price, and have capacity available.
- Capacity is checked when the upgrade is created and checked again under database locks when payment is fulfilled. A pending upgrade does not reserve target-tier capacity.
- Only one unresolved upgrade may exist for a ticket at a time.

## Customer Identification and Public Link

The upgrade is linked to the existing ticket, ticket order, and buyer details. The public URL contains only a random opaque token:

`/upgrade/{secure_token}`

Do not put phone number, email address, ticket ID, target ticket type, or amount in URL query parameters.

The public upgrade page displays:

- event name
- current ticket type
- target ticket type
- current ticket price / amount already represented by the ticket
- target ticket price
- upgrade balance due
- masked buyer phone/email when available
- upgrade/payment status
- Pay button when payment can still be made

## Data Model

Create `ticket_upgrades` with immutable snapshots of the upgrade request:

- `event_id`
- `ticket_order_id`
- `ticket_id`
- `from_ticket_type_id`
- `to_ticket_type_id`
- `public_token` unique random token
- `reference` unique human-facing upgrade reference
- `original_price`
- `target_price`
- `upgrade_amount`
- `currency`
- `status`
- `initiated_by`
- `completed_by`
- `initiated_at`
- `completed_at`
- `expires_at`
- financial snapshot fields for gross amount, platform commission, gateway fee, total charges, organizer net, and snapshot time
- `metadata`
- timestamps

Add nullable `ticket_upgrade_id` to `payments`. Upgrade payments must not populate `ticket_order_id`; otherwise the existing order-payment fulfillment path could incorrectly change the already-paid order state or reissue tickets.

`TicketUpgrade` statuses:

- `pending`
- `processing`
- `completed`
- `failed`
- `cancelled`
- `expired`

## Payment Flow

1. Admin selects an eligible ticket and a higher-priced target tier.
2. Backend calculates `target_price - current_ticket_price`; admin cannot override it.
3. Backend creates `TicketUpgrade` and a linked `Payment` with payment purpose `ticket_upgrade`.
4. The customer opens the secure upgrade link and proceeds to the existing payment entry point.
5. `PaymentService` submits the payment to Pesapal.
6. Browser callback is never treated as proof of payment.
7. Existing Pesapal callback/IPN verification calls the provider status API and updates the local payment only after provider verification.
8. A verified completed payment enters the normal idempotent payment-fulfillment pipeline.
9. Upgrade fulfillment locks the upgrade, ticket, and target ticket type, rechecks ticket state and capacity, then upgrades the existing ticket atomically.
10. The upgrade payment is marked fulfilled only after the ticket change and upgrade status are committed successfully.

If payment is pending, failed, cancelled, or expires, the original ticket remains unchanged and usable according to its original entitlement.

## Upgrade Fulfillment

When a linked payment has verified status `completed`:

- verify amount and currency match the immutable upgrade snapshot
- lock the upgrade row
- lock the ticket row
- lock the target ticket-type row
- verify the upgrade has not already completed
- verify the ticket still has the `from_ticket_type_id`
- verify the ticket is still `issued` and `used_at` is null
- verify target capacity is still available
- update the same ticket to `to_ticket_type_id`
- update the ticket's current `price` to `target_price`
- do not modify the historical `TicketOrderItem`
- preserve ticket number, public token, QR encrypted credential, and QR hash
- mark the upgrade completed and store completion time
- freeze upgrade financial values
- queue an updated-ticket delivery notification

The operation must be idempotent so repeated IPN, callback, or reconciliation runs cannot perform the upgrade twice or send duplicate completion notifications.

If target capacity has disappeared by the time a completed payment is fulfilled, do not silently oversell and do not change the ticket. The payment/upgrade must remain auditable and require manual review/refund handling.

## Administration

Use the existing **Ticketing > Manual Lookup** page as the initial administrative entry point because it already finds individual tickets by order number, ticket number, buyer name, phone, or email and already scopes results to events the user may access.

For eligible results add **Upgrade Ticket**. The action must:

- show only valid higher-priced target ticket types
- show current and target prices
- show the calculated balance
- reject used, cancelled, refunded, unpaid, cross-event, sold-out, or otherwise ineligible tickets on the server even if UI validation is bypassed
- create the upgrade and return/copy the secure customer payment link

Authorization follows the existing event-access rules used by manual ticket lookup: super admins may manage all events; ticket organizers may manage assigned ticketing events; other users must satisfy the existing event report/management authorization rule used by the page.

## Delivery

After completion, the customer should receive an updated ticket-access message through the event's available delivery channels. This notification must be separate from the original automatic ticket delivery so duplicate-prevention for the original sale does not suppress an upgrade-completion message.

The existing ticket URL remains valid and should render the new ticket type immediately. If renderer output is cached, the upgrade flow must invalidate the affected rendered output before notification.

## Scanner Behavior

No second QR code is created. The scanner resolves the existing ticket and therefore sees the new `ticket_type_id` after the upgrade. Existing duplicate-entry protection continues to apply.

A ticket that is already checked in cannot be upgraded.

## Reporting and Audit

Do not rewrite the original order total or order item as VIP. Reporting must retain:

- original ticket sale revenue in the original order
- upgrade revenue as a separate completed upgrade payment
- total completed upgrades
- pending/processing upgrades
- upgrade path, such as Regular -> VIP

Each upgrade permanently records original tier, target tier, original price, target price, balance, payment reference through the payment relation, initiating administrator, completion state, and timestamps.

Organizer sales reporting should expose upgrade count and upgrade gross revenue separately from original order sales, while totals may include both when clearly labelled.

## Security

- Public upgrade lookup is by random opaque token only.
- Never trust a browser callback as payment proof.
- Never accept price, target tier, ticket ID, or customer identity from public query-string values as authoritative.
- Recalculate/validate all eligibility server-side.
- Payment amount comes from the stored upgrade snapshot.
- Completion uses database row locks and is idempotent.
- Existing Pesapal provider-reference verification remains mandatory.
- No upgrade after check-in.

## Version 1 Exclusions

- ticket downgrades
- automatic partial refunds
- post-check-in administrator override
- customer-selected arbitrary upgrade targets without an admin-created upgrade request
- ticket transfers
- promo-code recalculation during upgrades
