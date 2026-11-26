# eLive Events revamp — master plan
Baseline: 86185e69706b9e866abeabff571b164de460954d. User-reported fresh local tests: 704 passed, 4 skipped, 2814 assertions, 43.39s.
## Scope
Incrementally improve the existing Laravel 12 / Filament 5 platform. Preserve existing logo, eLive capitalization, public links, credentials, financial records, organization isolation and production data. Maintain Selcom primary payment integration and manual approved organizer settlements. No automatic payouts. No destructive migrations.
## Modules
Events and registration; attendees; tickets; badges; programme; attendance; communications; merchandise; donations; payments; finance; organizations; reporting; administration. Audit and refine before adding functionality. New proposed optional modules: certificates (recognition) and photo frames (promotion), neither an admission credential. Maintain six admission modes: manual, free_registration, paid_registration, tickets, combined, paid_combined. Event types recommend defaults; feature settings and permissions determine navigation. Keep historical records accessible to authorized users.
## Roadmap
1. Inventory and detailed audit; identify risks and staging/main divergence.
2. Address critical security, payment, queue and backup risks immediately.
3. Centralized brand/design foundation; no wholesale visual redesign yet.
4. Simplified event creation, feature configuration, contextual navigation.
5. Registration, ticket, badge and attendance lifecycle; programme and communications.
6. Reconciliation, refunds and manual settlements.
7. Shared template primitives; optional certificates and photo frames.
8. Organizer/public experience and reports.
9. Consistent visual revamp, incremental staging verification and controlled production release.
## Acceptance
Each task must identify source evidence, existing behavior, gap, permissions, migration impact, tests and rollback. Automated tests are required throughout. Never merge divergent branches blindly or deploy this branch directly to production. Keep reserved seating, native apps, automatic payouts and other unrelated expansion out of current scope.
