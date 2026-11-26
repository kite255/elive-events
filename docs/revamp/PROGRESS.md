# Revamp progress
2026-11-26: revamp branch created by user from staging 86185e6; clean working tree and remote tracking confirmed by user terminal output. Fresh user-run suite: 704 passed, 4 skipped, 2814 assertions, 43.39s. Repository inventory complete at baseline; selected navigation and branding inspected. Full source audit remains pending.
## Open items
- Compare origin/main and baseline staging in both directions; connector compare endpoint rejected. Obtain local git rev-list and diff.
- Identify skipped tests and reasons, run required PostgreSQL/concurrency coverage in isolated test environment.
- Audit scanner page access and controller/service event authorization; broad page guards observed, exploitability not established.
- Review fee-write authorization, admission persistence transaction boundaries, paid registration validation and organization selection.
- Verify payment/webhook reconciliation, worker/scheduler health and backups/restores in staging; no live verification yet.
- Complete detailed feature-level audit, architecture, design-system, security, testing and deployment documents.
## Safety
Documentation-only initial phase. No business logic, migrations, deployments or production changes authorized by this phase.

## 2026-11-27 — Integration security checkpoint
- Working branch: `integration/revamp-baseline`; user confirmed a clean checkout at 8106fee before the latest resource hardening.
- User-run SQLite suite: 813 passed, 4 skipped, 3477 assertions, 43.60s. PostgreSQL payout concurrency: 1 passed, 4 assertions. Three PostgreSQL merchandise concurrency tests remain unverified. These are separate runs, not a single 817-test successful run.
- Security changes cover event/organization access, gate role restrictions, finance authorization, ticket upgrades and access recovery, campaign authorization and duplicate prevention, uncertain provider-send handling, template asset isolation, rendering limits, canvas validation and explicit unsaved layout recovery.
- Local asset audit: 2 pages, 2 legacy background references, no missing or foreign paths, no unreferenced candidates, no invalid image headers or sizes; one Regular ticket page remains invalid because its elements are outside the canvas. Recovery must be reviewed and saved explicitly. No asset deletion authorized.
- Latest resource hardening: gate staff excluded from both ticket template resource queries and navigation, including accounts also marked super administrator (b8e5e16, 922c26a). Regression test added at 8f485f0; execution pending.
- Existing active membership and event assignment checks verified in User model and event ticket template policy. This is targeted inspection, not a complete authorization audit.

### Remaining release gates
- Run the new resource access test and full regression; confirm all three merchandise PostgreSQL concurrency tests against the isolated database.
- Finish background-job, payment, public endpoint and cross-organization authorization review; verify worker/scheduler and production-like PostgreSQL/Redis behavior in staging.
- Browser-test designer recovery and resolve the invalid local Regular template; audit staging template assets separately.
- Record staging acceptance results and rollback/backup readiness before any production deployment.
- Phase 3 remains open. No staging or production deployment performed as part of this checkpoint.

## 2026-11-27 — Registration fulfillment and local PostgreSQL checkpoint
- Original EVENT_WORKSPACE.md restored byte-for-byte in 9191116.
- Registration fulfillment now checks event organization and approval (2761141), serializes attempts by attendee using a 300-second cache lock and reuses an existing badge (85d00c2). Local security tests passed; shared-cache multi-worker and lock expiry behavior remain unverified. This is not a durable database claim.
- Read-only completed-unfulfilled audit added (97fdb28); explicit single-registration recovery with historical provider evidence, conflict checks and independent operator verification added (0f7ee06). No automatic retries or live provider verification by recovery command.
- User confirmed 30 local PostgreSQL migrations applied, no pending migrations reported, three background containers restarted, and default 15-minute audit returned zero matching completed unfulfilled payments. Worker execution and backup restoration not yet verified.
- User-run suite at 644da31: 832 passed, 4 skipped, 3561 assertions, 46.08s; targeted recovery tests 4 passed, 14 assertions. New confirmed recovery execution/replay test at 3ecff17 remains unexecuted.
- Main/staging divergence was inspected locally: at earlier c4ffffd checkpoint main-only 201 and integration-only 460 commits; staging-only 0 and integration-only 160. Recheck before release; do not merge blindly.
- Remaining gates: isolated PostgreSQL merchandise concurrency tests, shared Redis locking, durable recovery and dispatch gaps, worker/scheduler and backup restore validation, designer browser checks and staging acceptance. Original Phase 3 design foundation remains pending; no production or staging deployment performed here.
