# Donations Subsystem Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a multi-tenant Donations subsystem for eLive Events supporting platform payments, client-direct display-only campaigns, client-direct tracked payments with manual verification, hybrid campaigns, optional public progress, donor privacy, reporting, messaging, and auditability.

**Architecture:** Donations are a separate domain from ticketing. New donation models and Filament resources own campaign/donor workflows, while online donation payments reuse the existing `Payment`, Pesapal, callback/IPN, fulfillment, communication, authorization, and audit infrastructure. Client-direct Display Only campaigns never create donation/payment records; Display + Tracking creates donation/manual-submission records and requires explicit authorized verification.

**Tech Stack:** Laravel 12, PHP 8.3, Filament, PostgreSQL, Redis queues, Pesapal API 3.0, existing eLive communication/audit services.

**Spec:** `docs/superpowers/specs/2026-10-06-donations-subsystem-design.md`

## Global Constraints

- Donations remain separate from ticket orders and ticket capacity/issuance logic.
- Multiple organizations and campaigns must be isolated by server-side authorization.
- A campaign belongs to one organization and may optionally belong to one event.
- Payment modes are `platform`, `client_direct`, and `hybrid`.
- Client-direct behavior is `display_only` or `tracked`.
- Display-only campaigns do not collect donor details, create donation records, verify payments, maintain system totals, or show system-derived progress.
- Public progress controls are OFF by default.
- Only `completed` donations count toward raised totals.
- Online donations may become completed only after provider verification; browser redirects are not proof of payment.
- Manual direct payments may become completed only after authorized verification.
- Manual approval and online fulfillment must be idempotent.
- Payment proof files are private and validated for type/size.
- Donors may be publicly anonymous while real contact data remains private.
- Public URLs use opaque tokens/slugs, not numeric donation IDs.
- Gateway credentials and private tokens must never be exposed publicly or written into audit metadata.
- v1 excludes recurring donations, refunds, arbitrary new gateway integrations, donor login, tax certificates, automated reconciliation of arbitrary client accounts, and public leaderboards.

## Review Focus

- A `display_only` campaign submitted to any donation-creation endpoint must create no donation/payment row and return a validation/authorization response appropriate to that route.
- Duplicate manual approval requests must not double-count a donation or emit duplicate completion side effects.
- Duplicate Pesapal callback/IPN deliveries for one donation payment must leave one completed donation and one fulfillment timestamp.
- Event managers must not read or modify campaigns/donations outside their assigned events even when they know numeric IDs.
- Progress/report totals must ignore `pending`, `awaiting_payment`, `awaiting_verification`, `rejected`, `failed`, and `cancelled` donations.

---

### Task 1: Donation Domain Schema and Models

**Files:**
- Create: `database/migrations/2026_10_06_210000_create_donation_campaigns_table.php`
- Create: `database/migrations/2026_10_06_210100_create_donation_payment_methods_table.php`
- Create: `database/migrations/2026_10_06_210200_create_donations_table.php`
- Create: `database/migrations/2026_10_06_210300_create_donation_manual_submissions_table.php`
- Create: `app/Models/DonationCampaign.php`
- Create: `app/Models/DonationPaymentMethod.php`
- Create: `app/Models/Donation.php`
- Create: `app/Models/DonationManualSubmission.php`
- Modify: `app/Models/Organization.php`
- Modify: `app/Models/Event.php`
- Test: `tests/Feature/Donations/DonationSchemaAndModelTest.php`

**Interfaces:**
- Produces: `DonationCampaign::scopePublicActive(Builder $query): Builder`
- Produces: `DonationCampaign::scopeAccessibleBy(Builder $query, ?User $user): Builder`
- Produces: `DonationCampaign::canBeManagedBy(User $user): bool`
- Produces: `Donation::scopeAccessibleBy(Builder $query, ?User $user): Builder`
- Produces: `Donation::isCompleted(): bool`
- Produces: relationships `Organization::donationCampaigns()`, `Event::donationCampaigns()`, `DonationCampaign::donations()`, `DonationCampaign::paymentMethods()`, `Donation::manualSubmission()`

- [ ] **Step 1: Write failing schema/model tests**

Assert the four tables exist with the spec fields; assert campaign lifecycle/payment-mode constants, donation status constants, casts, slugs/public tokens, and relationships. Add coverage that progress booleans default false and a display-only campaign can exist without donation rows.

- [ ] **Step 2: Run the test and verify failure**

Run:
```bash
docker compose exec app php artisan test tests/Feature/Donations/DonationSchemaAndModelTest.php
```
Expected: FAIL because donation tables/models do not exist.

- [ ] **Step 3: Implement migrations and models**

Use exact constants:
```php
DonationCampaign::STATUS_DRAFT
DonationCampaign::STATUS_ACTIVE
DonationCampaign::STATUS_PAUSED
DonationCampaign::STATUS_COMPLETED
DonationCampaign::STATUS_ARCHIVED

DonationCampaign::PAYMENT_MODE_PLATFORM
DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT
DonationCampaign::PAYMENT_MODE_HYBRID

DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY
DonationCampaign::DIRECT_BEHAVIOR_TRACKED

Donation::STATUS_PENDING
Donation::STATUS_AWAITING_PAYMENT
Donation::STATUS_AWAITING_VERIFICATION
Donation::STATUS_COMPLETED
Donation::STATUS_REJECTED
Donation::STATUS_FAILED
Donation::STATUS_CANCELLED
```

Generate unique campaign slugs inside organization scope and opaque donation `public_token` values on create.

- [ ] **Step 4: Run migration and test**

Run:
```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan test tests/Feature/Donations/DonationSchemaAndModelTest.php
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations app/Models tests/Feature/Donations/DonationSchemaAndModelTest.php
git commit -m "feat: add donation domain models"
```

### Task 2: Donation Authorization and Tenant Scoping

**Files:**
- Create: `app/Services/Donations/DonationAuthorizationService.php`
- Modify: `app/Models/DonationCampaign.php`
- Modify: `app/Models/Donation.php`
- Test: `tests/Feature/Donations/DonationAuthorizationTest.php`

**Interfaces:**
- Produces: `DonationAuthorizationService::canManageCampaign(User $user, DonationCampaign $campaign): bool`
- Produces: `DonationAuthorizationService::canVerifyDonation(User $user, Donation $donation): bool`
- Produces: `DonationAuthorizationService::accessibleCampaignIds(User $user): Collection`

- [ ] **Step 1: Write failing authorization tests**

Cover super admin, organization owner/admin, assigned event manager, unassigned event manager, and unrelated organization user. Include direct numeric-record access attempts, not only navigation visibility.

- [ ] **Step 2: Run and verify failure**

```bash
docker compose exec app php artisan test tests/Feature/Donations/DonationAuthorizationTest.php
```

- [ ] **Step 3: Implement authorization service and model scopes**

Use existing `User::managedOrganizations()`, `User::eventManagerEvents()`, and event assignment rules. Event managers may manage only campaigns whose `event_id` belongs to an event they manage.

- [ ] **Step 4: Run test**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Donations app/Models/DonationCampaign.php app/Models/Donation.php tests/Feature/Donations/DonationAuthorizationTest.php
git commit -m "feat: add donation authorization"
```

### Task 3: Campaign and Client Payment Method Administration

**Files:**
- Create: `app/Filament/Resources/DonationCampaigns/DonationCampaignResource.php`
- Create: `app/Filament/Resources/DonationCampaigns/Pages/ListDonationCampaigns.php`
- Create: `app/Filament/Resources/DonationCampaigns/Pages/CreateDonationCampaign.php`
- Create: `app/Filament/Resources/DonationCampaigns/Pages/EditDonationCampaign.php`
- Create: `app/Filament/Resources/DonationCampaigns/Schemas/DonationCampaignForm.php`
- Create: `app/Filament/Resources/DonationCampaigns/Tables/DonationCampaignsTable.php`
- Create: `app/Filament/Resources/DonationPaymentMethods/DonationPaymentMethodResource.php`
- Create: corresponding Pages/Form/Table classes under `DonationPaymentMethods`
- Test: `tests/Feature/Donations/DonationCampaignFilamentTest.php`

**Interfaces:**
- Consumes: Task 1 models; Task 2 authorization.
- Produces: Filament navigation group `Donations` with `Campaigns` and `Payment Methods`.

- [ ] **Step 1: Write failing Filament tests**

Assert permitted roles see only accessible campaign records; event manager cannot select an unrelated organization/event; payment methods can be added only to manageable campaigns. Assert progress toggles default false and display-only campaigns suppress tracking-only form options.

- [ ] **Step 2: Run and verify failure**

```bash
docker compose exec app php artisan test tests/Feature/Donations/DonationCampaignFilamentTest.php
```

- [ ] **Step 3: Implement campaign resource**

Fields must cover organization, optional event, title/slug, description, banner, lifecycle state, payment mode, direct behavior, currency, minimum/custom/suggested amounts, goal, all public progress toggles, donor wall, notification channel settings, and public visibility.

- [ ] **Step 4: Implement payment-method resource**

Each method supports type, provider name, account name, account number/phone, instructions, enabled state, and sort order. Do not store gateway API secrets here.

- [ ] **Step 5: Run tests**

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/DonationCampaigns app/Filament/Resources/DonationPaymentMethods tests/Feature/Donations/DonationCampaignFilamentTest.php
git commit -m "feat: add donation campaign administration"
```

### Task 4: Public Campaign Directory and Display-Only Campaigns

**Files:**
- Create: `app/Http/Controllers/PublicDonationCampaignController.php`
- Create: `resources/views/public/donations/index.blade.php`
- Create: `resources/views/public/donations/show.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/public/events/show.blade.php`
- Test: `tests/Feature/Donations/PublicDonationCampaignTest.php`

**Interfaces:**
- Produces routes:
  - `GET /donations` -> `public.donations.index`
  - `GET /donations/{campaign:slug}` -> `public.donations.show`

- [ ] **Step 1: Write failing public-page tests**

Assert only public active campaigns list; private/draft/paused campaigns return hidden/not-found behavior; display-only campaign renders its payment methods and the disclosure that funds go directly to the organizer; it renders no donor form, proof upload, progress derived from donations, or payment button.

- [ ] **Step 2: Run and verify failure**

```bash
docker compose exec app php artisan test tests/Feature/Donations/PublicDonationCampaignTest.php
```

- [ ] **Step 3: Implement routes/controller/views**

Add optional `Support this event` link on event pages only when a public active campaign is linked to that event. Keep donations secondary rather than adding a mandatory top-level Donate nav item.

- [ ] **Step 4: Run test**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/PublicDonationCampaignController.php resources/views/public/donations resources/views/public/events/show.blade.php routes/web.php tests/Feature/Donations/PublicDonationCampaignTest.php
git commit -m "feat: add public donation campaign pages"
```

### Task 5: Tracked Donation Creation and Secure References

**Files:**
- Create: `app/Services/Donations/DonationReferenceService.php`
- Create: `app/Services/Donations/DonationService.php`
- Create: `app/Http/Requests/StoreDonationRequest.php`
- Create: `app/Http/Controllers/PublicDonationController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/public/donations/show.blade.php`
- Test: `tests/Feature/Donations/DonationCreationTest.php`

**Interfaces:**
- Produces: `DonationReferenceService::generate(): string` returning unique `ELV-DON-XXXXXX`-style references.
- Produces: `DonationService::createTracked(DonationCampaign $campaign, array $data): Donation`
- Produces route: `POST /donations/{campaign:slug}` -> `public.donations.store`

- [ ] **Step 1: Write failing creation tests**

Cover suggested/custom amount, minimum amount, currency, donor fields, anonymity/consent, opaque public token, unique human reference, and rejection of creation for display-only campaigns.

- [ ] **Step 2: Run and verify failure**

```bash
docker compose exec app php artisan test tests/Feature/Donations/DonationCreationTest.php
```

- [ ] **Step 3: Implement request/service/controller**

`DonationService::createTracked()` must set:
- platform choice -> `awaiting_payment`;
- tracked client-direct choice -> `awaiting_verification` only after manual submission is attached in Task 6;
- initial record before manual details -> `pending`.

- [ ] **Step 4: Run tests**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Donations app/Http/Requests/StoreDonationRequest.php app/Http/Controllers/PublicDonationController.php resources/views/public/donations/show.blade.php routes/web.php tests/Feature/Donations/DonationCreationTest.php
git commit -m "feat: add tracked donation creation"
```

### Task 6: Client-Direct Tracking, Private Proof Upload, and Verification

**Files:**
- Create: `app/Services/Donations/ManualDonationService.php`
- Create: `app/Http/Requests/StoreManualDonationSubmissionRequest.php`
- Create: `app/Http/Controllers/ManualDonationSubmissionController.php`
- Create: `app/Http/Controllers/DonationProofController.php`
- Create: `app/Filament/Resources/Donations/DonationResource.php`
- Create: `app/Filament/Resources/Donations/Pages/ListDonations.php`
- Create: `app/Filament/Resources/Donations/Tables/DonationsTable.php`
- Create: `app/Filament/Pages/ManualDonationVerification.php`
- Create: `resources/views/filament/pages/manual-donation-verification.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Donations/ManualDonationVerificationTest.php`

**Interfaces:**
- Produces: `ManualDonationService::submit(Donation $donation, DonationPaymentMethod $method, string $reference, ?UploadedFile $proof): DonationManualSubmission`
- Produces: `ManualDonationService::approve(Donation $donation, User $actor): Donation`
- Produces: `ManualDonationService::reject(Donation $donation, User $actor, string $reason): Donation`

- [ ] **Step 1: Write failing manual-payment tests**

Assert reference is required; proof is optional; accepted proof types/sizes are validated; proof is not publicly accessible; approval requires authorization; approval changes status to completed exactly once; duplicate approval does not double-run completion; rejection stores reason; unrelated event manager receives 403.

- [ ] **Step 2: Run and verify failure**

```bash
docker compose exec app php artisan test tests/Feature/Donations/ManualDonationVerificationTest.php
```

- [ ] **Step 3: Implement private proof storage and submission**

Store proofs on a non-public disk/path. Serve proof only through `DonationProofController` after authorization.

- [ ] **Step 4: Implement manual verification and Filament UI**

Navigation: `Donations -> Donations` and `Donations -> Manual Verification`. No destructive edit/delete of completed records in v1.

- [ ] **Step 5: Run tests**

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Donations/ManualDonationService.php app/Http app/Filament/Resources/Donations app/Filament/Pages/ManualDonationVerification.php resources/views/filament/pages/manual-donation-verification.blade.php routes/web.php tests/Feature/Donations/ManualDonationVerificationTest.php
git commit -m "feat: add manual donation verification"
```

### Task 7: Link Donations to Existing Payment/Pesapal Infrastructure

**Files:**
- Create: `database/migrations/2026_10_06_210400_add_donation_id_to_payments_table.php`
- Modify: `app/Models/Payment.php`
- Create: `app/Services/Payments/DonationPaymentService.php`
- Modify: `app/Services/Payments/PesapalService.php`
- Modify: `app/Services/Payments/PaymentFulfillmentService.php`
- Modify: `app/Http/Controllers/Payments/PaymentController.php`
- Modify: `app/Http/Controllers/Payments/PesapalCallbackController.php`
- Test: `tests/Feature/Payments/DonationPaymentTest.php`
- Test: `tests/Feature/Payments/PesapalDonationBillingTest.php`

**Interfaces:**
- Produces: `Payment::donation(): BelongsTo`
- Produces: `DonationPaymentService::create(Donation $donation): Payment`
- Produces: `DonationPaymentService::start(Payment $payment): array`
- Extends: `PaymentFulfillmentService::fulfill(Payment $payment): Payment` to support `donation_id`

- [ ] **Step 1: Write failing online-payment tests**

Assert only platform/hybrid campaigns can create online payments; amount/currency come from the donation record; one unresolved payment is reused rather than duplicated; Pesapal billing name/email/phone come from donor fields; callback alone does not bypass provider verification.

- [ ] **Step 2: Run and verify failure**

```bash
docker compose exec app php artisan test tests/Feature/Payments/DonationPaymentTest.php tests/Feature/Payments/PesapalDonationBillingTest.php
```

- [ ] **Step 3: Add `donation_id` to payments and Payment relationship**

Foreign key nullable, indexed, null-on-delete or restrict according to existing payment-history conventions; retain historical integrity.

- [ ] **Step 4: Implement DonationPaymentService and Pesapal donor billing fallback**

`DonationPaymentService::create()` must use existing default organization gateway selection and existing `Payment` status semantics.

- [ ] **Step 5: Extend fulfillment**

For a completed donation payment, lock donation, verify amount/currency association, transition donation to `completed` if not already completed, set `completed_at`, then mark payment fulfilled. Duplicate callback/IPN execution must be a no-op after first fulfillment.

- [ ] **Step 6: Extend payment redirects/status**

Completed donation payments redirect to the secure donation status route rather than generic ticket/registration status.

- [ ] **Step 7: Run tests**

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_10_06_210400_add_donation_id_to_payments_table.php app/Models/Payment.php app/Services/Payments app/Http/Controllers/Payments tests/Feature/Payments/DonationPaymentTest.php tests/Feature/Payments/PesapalDonationBillingTest.php
git commit -m "feat: add online donation payments"
```

### Task 8: Secure Donor Status Page and Public Progress Metrics

**Files:**
- Create: `app/Services/Donations/DonationMetricsService.php`
- Create: `app/Http/Controllers/PublicDonationStatusController.php`
- Create: `resources/views/public/donations/status.blade.php`
- Modify: `resources/views/public/donations/show.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Donations/DonationStatusAndProgressTest.php`

**Interfaces:**
- Produces: `DonationMetricsService::forCampaign(DonationCampaign $campaign): array`
- Produces route: `GET /donations/status/{token}` -> `public.donations.status`

- [ ] **Step 1: Write failing status/progress tests**

Assert secure token lookup, no numeric-ID dependence, no private proof/reference/contact exposure, completed-only totals, optional goal/amount/percentage/donor count switches, no system progress for display-only campaigns, and anonymous donor display rules.

- [ ] **Step 2: Run and verify failure**

```bash
docker compose exec app php artisan test tests/Feature/Donations/DonationStatusAndProgressTest.php
```

- [ ] **Step 3: Implement metrics/status controller/views**

Metrics return at minimum:
```php
[
    'completed_amount' => float,
    'completed_count' => int,
    'goal_amount' => ?float,
    'percentage' => ?float,
]
```

- [ ] **Step 4: Run tests**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Donations/DonationMetricsService.php app/Http/Controllers/PublicDonationStatusController.php resources/views/public/donations routes/web.php tests/Feature/Donations/DonationStatusAndProgressTest.php
git commit -m "feat: add donation status and progress"
```

### Task 9: Donation Communications and Confirmation Delivery

**Files:**
- Create: `app/Services/Donations/DonationConfirmationService.php`
- Create: `app/Jobs/SendDonationConfirmationJob.php`
- Modify: `app/Models/CommunicationLog.php`
- Modify: existing communication template/config files used by ticket access/registration delivery as required
- Test: `tests/Feature/Donations/DonationConfirmationTest.php`

**Interfaces:**
- Produces: `DonationConfirmationService::availableChannels(Donation $donation): array`
- Produces: `DonationConfirmationService::queue(Donation $donation, array $channels, ?User $actor = null): array`
- Communication purpose constant: `donation_confirmation`

- [ ] **Step 1: Write failing communication tests**

Assert only enabled campaign/organization channels are available; incomplete donations are not sent completion receipts; completed donations can send Email/SMS/WhatsApp where configured; retries create auditable attempts without mutating donation/payment identity.

- [ ] **Step 2: Run and verify failure**

```bash
docker compose exec app php artisan test tests/Feature/Donations/DonationConfirmationTest.php
```

- [ ] **Step 3: Implement confirmation service/job using existing communication providers/logging**

Do not create a new messaging transport stack.

- [ ] **Step 4: Run tests**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Donations/DonationConfirmationService.php app/Jobs/SendDonationConfirmationJob.php app/Models/CommunicationLog.php tests/Feature/Donations/DonationConfirmationTest.php
git commit -m "feat: add donation confirmations"
```

### Task 10: Donation Audit Wiring

**Files:**
- Modify: `app/Services/Donations/ManualDonationService.php`
- Modify: campaign Filament create/edit pages or observers used by the resource
- Modify: `app/Services/Payments/DonationPaymentService.php`
- Modify: `app/Services/Donations/DonationConfirmationService.php`
- Test: `tests/Feature/Donations/DonationAuditTest.php`

**Interfaces:**
- Consumes: `AuditLogService::record(string $action, Model $subject, ?User $actor = null, array $metadata = [], ?array $before = null, ?array $after = null): AuditLog`
- Required action keys:
  - `donation.campaign.created`
  - `donation.campaign.updated`
  - `donation.manual.submitted`
  - `donation.manual.approved`
  - `donation.manual.rejected`
  - `donation.payment.verified`
  - `donation.payment.failed`
  - `donation.confirmation.resent`
  - `donation.payment_method.created`
  - `donation.payment_method.updated`

- [ ] **Step 1: Write failing audit tests**

Assert action, actor, subject, event/reference metadata and secret sanitization. Explicitly assert proof bytes/path secrets, gateway credentials, public/private access tokens, and raw provider secrets are absent from audit metadata.

- [ ] **Step 2: Run and verify failure**

```bash
docker compose exec app php artisan test tests/Feature/Donations/DonationAuditTest.php
```

- [ ] **Step 3: Wire audit calls at state-changing boundaries**

Keep audit events in services/actions that own the mutation, not Blade views.

- [ ] **Step 4: Run tests**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Donations app/Services/Payments/DonationPaymentService.php app/Filament/Resources/DonationCampaigns app/Filament/Resources/DonationPaymentMethods tests/Feature/Donations/DonationAuditTest.php
git commit -m "feat: audit donation operations"
```

### Task 11: Donation Reports and Payment Reconciliation

**Files:**
- Create: `app/Services/Donations/DonationReportService.php`
- Create: `app/Filament/Pages/DonationReports.php`
- Create: `resources/views/filament/pages/donation-reports.blade.php`
- Create: `app/Filament/Pages/DonationPaymentReconciliation.php`
- Create: `resources/views/filament/pages/donation-payment-reconciliation.blade.php`
- Modify: `app/Services/Payments/PaymentReconciliationService.php` only if shared provider checks can be cleanly reused without weakening ticket reconciliation
- Test: `tests/Feature/Donations/DonationReportsTest.php`
- Test: `tests/Feature/Payments/DonationPaymentReconciliationTest.php`

**Interfaces:**
- Produces: `DonationReportService::summaryForCampaign(DonationCampaign $campaign): array`
- Produces: `DonationReportService::summaryForOrganization(Organization $organization, array $filters = []): array`

- [ ] **Step 1: Write failing report/reconciliation tests**

Assert totals by completed status/payment mode/method, awaiting verification count, organization/event scoping, display-only campaigns excluded from tracked totals, and online completed-but-unfulfilled donation payments surfaced for reconciliation.

- [ ] **Step 2: Run and verify failure**

```bash
docker compose exec app php artisan test tests/Feature/Donations/DonationReportsTest.php tests/Feature/Payments/DonationPaymentReconciliationTest.php
```

- [ ] **Step 3: Implement reporting service and Filament reports page**

Navigation: `Donations -> Reports`.

- [ ] **Step 4: Implement donation payment reconciliation page**

Navigation: `Donations -> Donation Payments`. Re-sync must call existing provider verification; retry fulfillment only for provider-verified completed payments. No manual `Mark Paid` or `Force Success`.

- [ ] **Step 5: Run tests**

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Donations/DonationReportService.php app/Filament/Pages/DonationReports.php app/Filament/Pages/DonationPaymentReconciliation.php resources/views/filament/pages tests/Feature/Donations/DonationReportsTest.php tests/Feature/Payments/DonationPaymentReconciliationTest.php
git commit -m "feat: add donation reporting and reconciliation"
```

### Task 12: Navigation, End-to-End Security, Regression, and Staging Readiness

**Files:**
- Create: `tests/Feature/Donations/DonationNavigationTest.php`
- Create: `tests/Feature/Donations/DonationEndToEndTest.php`
- Modify: donation Filament resources/pages as required by test failures
- Modify: `routes/web.php` only if route ordering/security corrections are required

**Interfaces:**
- Consumes all previous tasks.
- Produces a release candidate on `feature/donations`.

- [ ] **Step 1: Write end-to-end role/navigation tests**

Assert:
- Super Admin sees all donation admin pages.
- Organization Owner/Admin see only organization-scoped records.
- Assigned Event Manager sees linked campaigns only.
- Other roles see no donation administration by default.
- Display-only public campaign has no tracking form.
- Tracked direct donation reaches awaiting verification then completed after approval.
- Platform donation reaches completed only after provider-verified payment fulfillment.

- [ ] **Step 2: Run donation test suite**

```bash
docker compose exec app php artisan test tests/Feature/Donations tests/Feature/Payments/DonationPaymentTest.php tests/Feature/Payments/PesapalDonationBillingTest.php tests/Feature/Payments/DonationPaymentReconciliationTest.php
```
Expected: all PASS.

- [ ] **Step 3: Run complete application test suite**

```bash
docker compose exec app php artisan test
```
Expected: all PASS, zero failures.

- [ ] **Step 4: Run structural checks**

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan route:list > /dev/null
git status --short
git diff --check
```
Expected: clean working tree after commits and no whitespace errors.

- [ ] **Step 5: Commit any final test-driven corrections**

```bash
git add .
git commit -m "test: verify donations subsystem end to end"
```

- [ ] **Step 6: Prepare staging smoke-test checklist**

Verify on staging:
- public active campaign listing;
- display-only client campaign;
- tracked client-direct submission and private proof;
- manual approve/reject permissions;
- platform Pesapal test payment;
- secure donor status page;
- optional progress OFF by default;
- completed-only totals;
- donation confirmations;
- reports/reconciliation;
- audit entries;
- unrelated event-manager isolation.

Do not promote to `main` until the merged staging tree passes the full suite and smoke tests.
