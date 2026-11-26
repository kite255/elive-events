# Initial repository inventory
Source: complete recursive tree at baseline 86185e6; selected files reviewed in prior staging inspection. File presence is not proof of runtime correctness.
| Area | Existing evidence | Next audit |
|---|---|---|
| Event/admission | EventResource, EventAdmissionConfiguration, EventPresetService, EventPublicationReadiness | Wizard persistence, feature visibility, event lifecycle |
| Registration | RegistrationStatusService, RegistrationPaymentFollowUp, registration tests | Cancellation, approval/payment follow-up, capacity |
| Tickets | TicketOrderService, TicketIssuanceService, TicketAvailabilityService, TicketUpgradeService, rendering services | Concurrent purchase, recovery, cancellation/refund |
| Badges | BadgeGenerationService, BadgeDeliveryService, BadgeTemplate, BadgePrintStation | Eligibility, template and printing workflow |
| Attendance | CheckInService, TicketCheckInService, CheckInStation, GateCheckIn, TicketScanner, ManualCheckIn | Permissions and safe consolidation |
| Programme | EventDay, EventSession | Session capacity and attendance |
| Communications | CommunicationCenter, CommunicationCampaignResource, AutomaticCommunicationService, SmsService, WhatsAppService | Queues, delivery and retry idempotency |
| Merchandise | MerchandiseOrder, MerchandiseProduct, MerchandiseVariant | Inventory and fulfillment |
| Donations | DonationCampaign, DonationPaymentService, ManualDonationService | Reconciliation |
| Payments/finance | SelcomService, PesapalService, PaymentReconciliationService, EventFinanceClearanceService, EventPayoutService | Webhooks, ledger, settlements, historical fees |
| Organizations | Organization, OrganizerManagementService | Isolation, roles and onboarding |
| Reports | AttendanceReport, AttendeeReport, EventFinanceReport, DonationReports, BadgePrintReport | Consolidation |
| Certificates | No dedicated named files found in baseline tree | Confirm source absence; proposed module |
| Photo Frames | No dedicated named files found in baseline tree | Confirm source absence; proposed module |
| Monitoring | config/backup.php, Docker configuration, finance-validation CI | Live health, recovery and CI scope |
Counts: 29 Filament page files, 27 resource classes, 52 model files, 70 service files, 135 migration files, 194 test directory files, 8 job files, 2 policy files. No files under resources/views/components/ at baseline; reusable patterns may exist elsewhere.
