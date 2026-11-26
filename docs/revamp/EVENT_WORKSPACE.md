# Event workspace and tenant isolation
## Existing foundation (source reviewed on integration/revamp-baseline)
Event::scopeAccessibleBy, isAccessibleBy, canBeManagedBy and module-specific permission methods; User::activeOrganizations, assignedEvents and event-specific permission helpers; Organization::events; EventResource::getEloquentQuery scopes accessible events and canView/canEdit use record checks. Gate panel restrictions are being reconciled separately. These methods exist but end-to-end isolation is not yet proven.
## Requirements
TEN-01 Reuse existing organizations, users, memberships and event assignments; no duplicate account or tenancy system. One account per person, multiple authorized events; shared PostgreSQL database.
TEN-02 Audit query scope and record authorization consistency, inactive memberships, assignment revocation, cross-organization assignment, event creation and organization reassignment.
TEN-03 Provide authorized event switcher and dedicated workspace with explicit route event context. Reauthorize every request/action, never trust a selected session ID or client state as authorization. Exclude gate-only users from normal admin workspace.
TEN-04 Separate global dashboard (authorized aggregates) from selected-event dashboard (only that event). No unauthorized finance totals or PII.
TEN-05 Contextual navigation based on enabled modules, permission and historical record access; disabling feature must not delete records.
TEN-06 Scope attendees, registration, tickets, badges, attendance, programme, communications, merchandise, donations, payments, finance, reports and future certificates/photo frames directly or via verified parent relationships. Reuse existing resources with explicit event context; no duplicate business logic.
TEN-07 Ensure exports, downloads, bulk actions, APIs, jobs, cache keys, template assets and payment records retain appropriate tenant/event context. Public routes use credential/ownership checks where appropriate, not administrative session context.
TEN-08 Staff roles are permission-specific; gate only assigned scanner access; finance separately authorized. Reuse current roles before adding any.
TEN-09 Platform branding remains eLive (#161943, #007AB2, #FF9800); scoped organization/event branding must not affect other events.
## Acceptance tests
Two organizations, at least two events in one organization, admins, assigned/unassigned staff, suspended membership and gate-only account. Verify event switcher, dashboard aggregates, direct URL tampering, nested record IDs, search, exports, bulk actions, financial data and background jobs. Deny unauthorized read/write access even if navigation is hidden. Confirm same account can switch authorized events without mixing data. Test revocation and feature disable with historical records. No production data or migrations required for initial audit.
## Sequence
Finish compatibility reconciliation and combined regression tests first. Then audit access helpers, implement scoped workspace read-only overview/switcher, add contextual links to existing resources, verify each module and only then add new modules. This document is a target specification, not implemented functionality.
