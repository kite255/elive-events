# Finance staging acceptance checklist

This PR is reporting-first. Do **not** enable organizer payout requests in staging or production without an approved reconciliation procedure.

## Deployment configuration
- [ ] Confirm target branch is `staging` and environment is non-production.
- [ ] Set `FINANCE_PAYOUT_REQUESTS_ENABLED=false`.
- [ ] Back up the staging database before migrations.
- [ ] Apply `php artisan migrate --force` and `php artisan optimize:clear` in staging only.
- [ ] Confirm queue/mail configuration and error logs.
- [ ] Verify no administrator-attested clearance entry has been mistaken for bank/provider confirmation.

## Role-based acceptance
- [ ] Super admin can access Platform Finance, Event Finance, Payments and Payout Requests.
- [ ] Owner of organization A sees only A's events and payouts.
- [ ] Ticket organizer sees only assigned ticketing events.
- [ ] Check-in and unrelated roles cannot see another organization's finance.
- [ ] Unauthenticated users cannot access finance routes.

## Calculations and reporting
- [ ] Compare paid-order gross, platform commission, gateway fees and organizer net with database records.
- [ ] Compare per-event dashboard and CSV figures for the same event.
- [ ] Enter an administrator-attested settlement with real external evidence (staging sample only).
- [ ] Record an adjustment for a refund or chargeback; check displayed reversals and warnings.
- [ ] Check that imported/exported CSV event names cannot create spreadsheet formulas.
- [ ] Validate a fully refunded and a partially refunded ticket order.
- [ ] Ensure payout request button remains disabled.

## Payout history and notifications
- [ ] Audit history shows creator, reviewer, status transitions, time and reference without exposing destination details.
- [ ] Verify email delivery via staging mail capture, including approval/rejection and recorded transfer.
- [ ] Verify no email includes sensitive account information.
- [ ] Verify notifications are not sent on rolled-back transactions.

## UI and operations
- [ ] Open Event Finance on desktop and a narrow mobile viewport.
- [ ] Change event filter; verify figures refresh without cross-org data leakage.
- [ ] Check loading, empty states, dark mode and long event names.
- [ ] Confirm exports download and are readable in Excel.
- [ ] Review application logs, job failures and migration rollback procedure.

## Exit criteria
- [ ] CI is green on the exact merged commit.
- [ ] Staging screenshots and acceptance results recorded.
- [ ] Independent finance owner signs off on settlement controls.
- [ ] Production rollout and rollback plan approved.

Production payout activation is **not** part of this staging release.
