# eLive Events production payout release checklist

This branch starts from production `main` (`bd98ef80d5f94f3512ee477d5c831e067fcc853c`) and intentionally does **not** bring the entire diverged `staging` tree into production.

## Scope requested

- Organizer requests against assigned-event unpaid ticket earnings.
- Admin payout balances, approval errors, rejection reasons, audit details.
- Approval only after independent settlement verification.
- Manual transfer with proof, duplicate protection, requester receipt confirmation.
- Existing payout-related notifications; user has deferred email notification setup.

## Source changes requiring controlled integration

- `app/Filament/Resources/Payouts/Pages/ListPayouts.php`
- `app/Filament/Resources/Payouts/PayoutResource.php`
- `app/Filament/Widgets/PayoutBalanceOverview.php`
- `resources/views/filament/widgets/payout-balance-overview.blade.php`
- `resources/views/filament/payouts/request-details.blade.php`
- `app/Services/EventPayoutService.php`
- `app/Services/EventPayoutReviewService.php`
- `app/Models/EventPayoutRequest.php`
- `app/Http/Controllers/PayoutTransferProofController.php`
- `database/migrations/2026_10_30_120000_add_manual_transfer_evidence_to_payout_requests.php`
- Related routes / permissions and payout regression tests.

**Before copying:** compare each file against main, retain production-specific fixes, and verify dependencies and route authorization. Do not replace payment gateway files wholesale.

## Excluded pending separate review

- Selcom gateway and webhook changes, payment controllers/services, `config/services.php`.
- Docker/Nginx, `composer.lock`, frontend/Filament bundled assets.
- Staging payout simulation page, migration, and tests.
- Daily and system email settings and scheduler changes (email deferred).

## Release gate

1. Resolve differences between staging and main for each in-scope file.
2. Validate migration compatibility and backup production PostgreSQL.
3. Run full PHP/Laravel tests, PostgreSQL payout concurrency tests, and authorization tests on a release candidate.
4. Confirm staging role-based UI behavior with real permissions.
5. Audit real production payout balances against actual bank/payment-provider statements; no fabricated financial clearance.
6. Merge to main only after green CI and financial/payment risk review.
7. Deploy via Coolify, run migrations and cache/worker restart, and verify production read-only screens first.

**Status:** release branch prepared, code integration not yet done.