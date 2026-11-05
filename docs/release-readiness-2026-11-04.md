# eLive Events — release reconciliation checklist (2026-11-04)

## Decision
**Do not merge `staging` wholesale into `main`.** The histories diverge (at inspection: staging 204 commits ahead of main and main 127 commits ahead of staging). These counts describe commit history, **not** the number of missing features. Keep `main` as the release base and integrate only reviewed, tested changes.

## Verified source-level inventory
- **Selcom branded checkout**: `app/Services/Payments/SelcomService.php` and `app/Http/Controllers/Payments/PaymentController.php` have the same blob SHA in both branches. QR display and paid-ticket fulfillment still require a real end-to-end acceptance test.
- **Terms & Conditions**: `app/Support/TicketPurchaseTerms.php` is identical; both branches have the terms route and the required checkbox in `resources/views/public/tickets/buy.blade.php`.
- **Daily ticket email summaries**: `app/Console/Commands/EmailDailyTicketSummary.php` is identical. The schedules for daily summaries are present in `main/routes/console.php` (00:10 and 00:20 Africa/Dar_es_Salaam). Check actual scheduler and email delivery before sign-off.
- **Organizer payout event assignment**: `app/Services/EventPayoutService.php` enforces assigned event access for ticket organizers in both branches. Test all admin/page/API paths separately.
- **Finance controls added on main**: `main` has single-super-admin self-attestation, void audit and revised payout settlement logic in `app/Services/EventFinanceClearanceService.php`, `app/Services/EventPayoutService.php`, and `app/Services/EventPayoutReviewService.php`. `staging` lacks some of these additions. Preserve the `main` controls; do not overwrite them with staging copies. The single-admin exception must be reviewed against segregation-of-duties requirements; the code documents that self-attestation is not independent verification.
- **Different public ticket behavior**: `main` checkout says email is optional; staging requires email and references `public/js/public-ticket-checkout.js`, which is absent from `main`. Do not replace one implementation with the other without confirming the buyer-contact policy and testing checkout.
- **Staging-only simulation**: `app/Filament/Pages/StagingPayoutSimulation.php`, its migration and feature test exist only in staging. Do not promote a staging-only financial simulator to production without an explicit operational need and robust environment/authorization guard.
- **Historical staging Selcom document**: `docs/selcom-staging-acceptance.md` describes older pre-go-live conditions and should not be treated as current production state without revision.

## Required pre-release checks
1. **Working tree and branch audit**: `git status --short`; `git fetch origin`; `git log --oneline origin/main..origin/staging`; `git log --oneline origin/staging..origin/main`. Reconcile the actual feature diffs, not commit counts.
2. **Unit and feature tests**: run `php artisan test --filter=Finance`, `php artisan test --filter=Selcom`, `php artisan test --filter=PublicTicketCheckout`, `php artisan test --filter=Payout`, plus the full suite with PostgreSQL test DB. Record actual results.
3. **Selcom sandbox/controlled checkout**: verify QR image is displayed; wallet and QR flow; signed webhook/idempotency; completed, failed, expired and delayed payments; ticket issuance and email only once after verified payment.
4. **Finance acceptance**: verify real gateway settlement against actual bank/mobile-money statements; event-specific net ticket earnings and fee deductions; payout request cap; admin approval; independent verification (or documented exception); bank/mobile-money transfer; attached transfer proof; organizer receipt; audit trail. **Do not mark money independently verified merely because it is recorded in the application.**
5. **Access control**: test organizer access for assigned/unassigned events, payout proof downloads, email settings, finance dashboard, payout review and QR/check-in endpoints.
6. **Messaging and schedules**: investigate 64 previously reported failed-job records, distinguish historical from current failures, verify general/payments workers, scheduler, and delivery of daily summaries, ticket-sale and payout emails.
7. **Frontend acceptance**: test checkout on small mobile screens, logo single-row treatment, eLive capitalization, mandatory terms acceptance, and accessible feedback/notifications.
8. **Operations**: record backup restore point, migration list, queue status and release SHA. Deploy to staging and verify; only then promote reviewed changes to `main`. Perform production smoke tests and monitor logs.

## Release gate
No automated tests or production checks were executed during this GitHub source comparison. **This document does not authorize production deployment.** Final release requires test evidence and an explicit review of the changed files and migration safety.

## Safe integration workflow
1. Branch from current `origin/main` (never overwrite `main` with `staging`).
2. Selectively cherry-pick or implement the *specific missing feature*, keeping main's newer finance policies intact.
3. Resolve conflicts file-by-file; create focused tests and a draft PR.
4. Require passing CI/feature tests and staging acceptance.
5. Merge reviewed PR, deploy with backup/rollback plan, verify with production smoke checks.
