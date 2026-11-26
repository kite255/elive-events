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
