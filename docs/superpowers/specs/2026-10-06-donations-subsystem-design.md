# eLive Events Donations Subsystem — Design Specification

**Date:** 2026-10-06  
**Branch:** `feature/donations`  
**Status:** Proposed for user review

## 1. Purpose

Add a Donations subsystem to eLive Events that lets different organizations and event managers publish multiple donation campaigns while choosing how payments are handled.

The subsystem must support clients who:

- only want eLive to publish a donation campaign and their payment details;
- want eLive to record donor submissions and manually verify direct payments;
- want donors to pay online through eLive/Pesapal;
- want to offer both online and client-direct payment options.

Donations must remain a separate business domain from ticket orders while reusing existing payment verification, messaging, authorization, and audit infrastructure where appropriate.

## 2. Product Positioning

Donations are a secondary eLive service, not a primary site function.

Public placement:

- `Services -> Donations` or equivalent secondary navigation;
- direct campaign links that organizers can share;
- optional `Support this event` / `Donate` button on an event page when a campaign is linked to that event.

The system should not require a prominent top-level Donate item.

## 3. Multi-Campaign Model

The system must support many donation campaigns at the same time.

Each campaign belongs to an organization and may optionally be linked to an event.

Example URLs:

- `/donations/church-building-project`
- `/donations/youth-mission-fund`
- `/donations/education-support`

A public campaign listing may exist at `/donations`, showing only active, public campaigns.

Suggested lifecycle states:

- draft
- active
- paused
- completed
- archived

## 4. Campaign Payment Modes

Each campaign chooses one of three payment modes.

### 4.1 Platform Payment

The donor pays online through the payment gateway supported by eLive.

Flow:

1. Donor submits donation details and amount.
2. Donation record is created.
3. Payment record is created and linked to the donation.
4. Donor is redirected to Pesapal.
5. Pesapal callback/IPN is received.
6. Backend verifies payment status with the provider.
7. Only provider-verified completion may mark the donation completed.
8. Confirmation/receipt is sent using enabled communication channels.

A browser success redirect must never be sufficient proof of payment.

### 4.2 Client Direct Payment

Money goes directly to the client's own account, such as M-Pesa, Airtel Money, Mixx by Yas, CRDB, NMB, another bank, or another manually configured method.

Client-direct campaigns must support two behaviors.

#### Display Only

eLive publishes:

- campaign details;
- client's payment methods;
- account/phone number;
- account name;
- instructions;
- optional contact information.

eLive does not:

- collect donor details;
- collect transaction references;
- collect proof;
- verify payment;
- maintain donation totals;
- show progress derived from donations.

This mode is intended for clients who only want eLive to advertise the campaign and payment instructions.

#### Display + Tracking

eLive publishes the client's payment details and also allows the donor to submit:

- donor name;
- phone;
- email where available;
- amount;
- transaction/reference number;
- optional payment proof upload.

The donation enters `awaiting_verification`.

An authorized admin verifies the payment against the client's real account and either approves or rejects it.

Only approved direct-payment donations count as completed.

eLive never receives or holds the money in this mode.

### 4.3 Hybrid

The donor may choose:

- eLive online payment; or
- client direct payment.

The campaign may choose whether direct payment is Display Only or Display + Tracking.

## 5. Donation Amounts

For tracked/platform campaigns, each campaign may configure:

- suggested amounts;
- custom amount;
- minimum amount;
- currency.

Recommended user interface:

- quick-select suggested amounts;
- custom amount input.

## 6. Donor Identity and Privacy

Tracked donations collect private donor details for administration and communication.

Required privacy model:

- donor may choose to appear anonymous publicly;
- eLive still stores real donor identity/contact details privately;
- public donor visibility requires explicit consent;
- transaction references and payment proofs are always private;
- payment proof files are only accessible to authorized admins.

No donor account/login is required for v1.

## 7. Public Progress

Public progress is optional per campaign and OFF by default.

Independent settings:

- show goal;
- show amount raised;
- show percentage;
- show donor count;
- enable donor wall.

Only completed/verified donations may contribute to public totals.

Display-only campaigns have no system-derived progress because eLive does not track their payments.

## 8. Public Donor Wall

If enabled:

- only donors who consent may be shown;
- anonymous donors may display as `Anonymous`;
- the organizer may choose whether donation amounts are shown publicly.

## 9. Public Campaign Experience

A normal tracked campaign page may include:

- banner/image;
- title;
- organization name;
- optional linked event;
- description;
- optional progress;
- suggested/custom amount selection;
- donor details;
- anonymous/public-display choices;
- payment method selection;
- donate/submit action.

For client-direct Display Only campaigns, the page may contain only:

- campaign content;
- payment instructions;
- optional organizer contact;
- no donor form.

## 10. Donor Status and Receipt

Tracked donations receive:

- human-friendly reference, e.g. `ELV-DON-7K4M9Q`;
- opaque public token;
- secure donor-facing status URL such as `/donations/status/{token}`.

The status page may show:

- campaign;
- amount;
- donation reference;
- payment status;
- receipt/confirmation state.

It must not expose internal database IDs or payment secrets.

## 11. Donation Statuses

Suggested statuses:

- pending
- awaiting_payment
- awaiting_verification
- completed
- rejected
- failed
- cancelled

Transitions must be validated server-side.

Manual verification must be idempotent so the same donation cannot be approved twice.

## 12. Data Model

### donation_campaigns

Core fields:

- id
- organization_id
- event_id nullable
- title
- slug
- description
- banner/image
- status
- payment_mode
- direct_payment_behavior nullable
- currency
- minimum_amount nullable
- suggested_amounts
- allow_custom_amount
- goal_amount nullable
- show_goal
- show_amount_raised
- show_percentage
- show_donor_count
- donor_wall_enabled
- online_payment_enabled
- manual_payment_enabled
- notification settings
- public visibility
- timestamps

### donations

Used only for tracked/platform flows.

Core fields:

- id
- campaign_id
- public_token
- reference
- donor_name
- donor_phone
- donor_email
- amount
- currency
- is_anonymous
- public_display_consent
- payment_type
- status
- completed_at
- timestamps

### donation_payment_methods

Used for client-direct instructions.

Core fields:

- id
- campaign_id
- type
- provider_name
- account_name
- account_number_or_phone
- instructions
- enabled
- sort_order
- timestamps

### donation_manual_submissions

For client-direct Display + Tracking.

Core fields:

- donation_id
- payment_method_id
- transaction_reference
- proof_path nullable
- submitted_at
- verified_by nullable
- verified_at nullable
- rejection_reason nullable
- timestamps

Exact table naming may be adjusted during implementation for consistency with existing repository conventions.

## 13. Payment Integration

Online donations should reuse the existing payment architecture rather than create a second gateway stack.

The implementation should reuse:

- Payment model where compatible;
- Pesapal provider integration;
- provider verification rules;
- callback/IPN handling patterns;
- reconciliation behavior;
- fulfillment/idempotency patterns.

A donation payment must have an explicit relationship to its donation so ticket, upgrade, registration, and donation payments remain distinguishable.

Client-specific gateway credentials may be supported architecturally, but v1 does not require arbitrary client gateway integration beyond already-supported safe infrastructure.

Gateway credentials must:

- be encrypted at rest;
- never be displayed back in full;
- never be included in audit metadata;
- never be exposed publicly;
- be managed only by authorized organization-level roles.

## 14. Manual Verification

For Display + Tracking direct payments:

1. Donor selects a client payment method.
2. Donor pays directly to client.
3. Donor submits transaction reference.
4. Donor may optionally upload payment proof.
5. Donation becomes `awaiting_verification`.
6. Authorized admin reviews it.
7. Approve -> donation becomes `completed`.
8. Reject -> donation becomes `rejected` with reason.

Approving must not move any money.

The admin is confirming that the client received funds externally.

## 15. Permissions

### Super Admin

- all campaigns;
- all donations;
- all manual verification;
- all reports;
- gateway/payment settings;
- all donation-related audit entries.

### Organization Owner/Admin

Within their organization:

- create/edit campaigns;
- view donations;
- configure client payment methods;
- verify direct payments;
- view reports;
- manage permitted organization payment settings.

### Event Manager

Only for campaigns linked to events they already manage:

- view/manage campaign;
- view tracked donations;
- approve/reject direct-payment submissions;
- view campaign reports.

Event Manager does not receive organization-wide gateway-secret management by default.

### Other Roles

No donation administration by default.

## 16. Admin Navigation

Recommended Filament group:

```text
Donations
├── Campaigns
├── Donations
├── Manual Verification
├── Donation Payments
└── Reports
```

Donation actions should use the existing reusable system-wide Audit Logs instead of creating a second audit-log menu.

## 17. Campaign Administration

Campaign admins can configure:

- organization;
- optional event;
- title/slug;
- description;
- banner;
- lifecycle status;
- payment mode;
- direct-payment behavior;
- suggested amounts;
- custom/minimum amount;
- goal;
- public progress visibility;
- donor wall;
- payment methods;
- notification channels;
- public visibility.

## 18. Reports

Tracked/platform campaign reports may include:

- total confirmed donations;
- completed donation count;
- pending donations;
- awaiting verification;
- rejected/failed donations;
- total by campaign;
- total by payment type;
- total by payment method;
- anonymous donation count;
- date ranges.

Filters may include:

- organization;
- event;
- campaign;
- payment mode/method;
- status;
- date.

Display-only campaigns must not show system-derived donation totals because eLive does not receive tracking data for those payments.

Exports are optional and may be deferred if not required for v1.

## 19. Communications

Per campaign, confirmation channels are configurable based on available infrastructure:

- on-screen;
- email;
- WhatsApp;
- SMS.

Suggested events:

- donation completed;
- manual submission received;
- manual donation approved;
- manual donation rejected;
- confirmation resent.

The implementation should reuse existing messaging and communication-log infrastructure where practical.

## 20. Audit

Reuse the generic system-wide audit system.

Suggested action keys:

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

Audit payloads must exclude:

- gateway secrets;
- raw credentials;
- payment proof contents;
- private access tokens.

## 21. Security and Integrity Rules

- Browser redirects do not prove online payment.
- Provider verification is mandatory before online completion.
- Only completed donations count toward totals.
- Direct-payment proof uploads are private.
- Manual approval is idempotent.
- Duplicate callbacks must not double-count donations.
- Public tokens must be opaque.
- Internal IDs should not be required in public URLs.
- Authorization must be enforced server-side, not only in Filament navigation.
- Uploaded proof type/size must be validated.
- Audit metadata must be sanitized.
- Client-direct Display Only campaigns must not imply that eLive verified or processed payments.

## 22. Public Messaging for Client-Direct Payments

The UI should clearly state when funds go directly to the campaign organizer.

Example wording:

> Payment is made directly to the campaign organizer using the details below. eLive does not receive these funds.

For Display + Tracking:

> After paying, submit your transaction reference and optional proof so the organizer can verify your donation.

This avoids misleading donors about who processes or holds the funds.

## 23. Version 1 Scope

Included:

- multiple campaigns;
- organization ownership;
- optional event linkage;
- platform online payments;
- client direct Display Only;
- client direct Display + Tracking;
- hybrid campaigns;
- suggested/custom amounts;
- optional anonymous/public donor visibility;
- manual payment instructions;
- reference + optional proof upload;
- manual verification;
- optional progress;
- donor status page;
- confirmations;
- permissions;
- reports;
- auditing.

Deferred:

- recurring/subscription donations;
- refunds;
- automated reconciliation of arbitrary client bank/mobile-money accounts;
- donor accounts/login;
- tax certificates;
- public leaderboards;
- arbitrary new gateway integrations;
- advanced exports unless specifically requested.

## 24. Recommended Implementation Sequence

1. Database schema, models, policies/scopes.
2. Campaign administration.
3. Client-direct payment-method administration.
4. Public campaign/listing pages.
5. Display Only flow.
6. Tracked donation form and secure references.
7. Manual submission/proof workflow.
8. Manual verification.
9. Online Pesapal donation integration.
10. Donor status/confirmation.
11. Messaging.
12. Reports and audit wiring.
13. Full automated tests.
14. Staging deployment and smoke test.
15. Production promotion after verification.

## 25. Acceptance Criteria

The feature is ready for production when:

- multiple organizations can operate separate campaigns without data leakage;
- event managers cannot access unrelated campaigns;
- display-only campaigns publish payment details without generating false payment records;
- tracked direct payments remain unconfirmed until authorized verification;
- online payments complete only after provider verification;
- duplicate callbacks/approvals do not duplicate totals;
- public progress uses completed donations only;
- progress can be completely hidden;
- donors can remain publicly anonymous while private records remain available to authorized admins;
- payment proofs are private;
- audit logs contain no secrets;
- the full existing test suite plus new donation tests pass;
- staging smoke testing succeeds before promotion to main.
