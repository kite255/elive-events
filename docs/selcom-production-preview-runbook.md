# Selcom production release — isolated Coolify acceptance runbook

**Release:** `release/selcom-production-safe` (PR #174, target `main`).
**Scope:** Selcom QR and mobile wallet integration on top of production main, without replacing production payout services.
**STOP:** Do not merge or select Selcom as production default solely because CI passed.

## Important container safety finding

The repository's checked-in `docker-compose.yml` is a *development* Compose file. It specifies fixed `container_name` values, host ports (including 8005, 5435, 6381, 5055, 8025, 1025), development PostgreSQL credentials, and a development pgAdmin account. **Never launch this file unchanged as a second deployment on a shared production host.** Use the existing Coolify production Compose template or a distinct preview-safe definition with unique service naming, private internal services, fresh secrets, and no published DB/Redis/pgAdmin ports.

## 1. Create an isolated preview in Coolify

- Make a **new** Coolify resource, selecting repository `kite255/elive-events`, branch `release/selcom-production-safe`; do **not** change the running production/staging branch.
- Use a new preview-only domain with TLS; do not reassign `events.elive.co.tz` or the existing staging domain.
- Fresh `APP_KEY` and preview-only `APP_URL`; application `APP_ENV=staging`, `APP_DEBUG=false`.
- Separate PostgreSQL database and credentials; separate Redis instance/prefix; isolated session/cache/queue namespaces and separate persistent storage. **Never point `DB_HOST` at the production or existing staging database.**
- Configure preview-specific trusted proxy addresses and outbound email routing. For no-charge smoke tests, use an email capture sink (such as Mailpit) and disable customer-facing SMS/WhatsApp sends.
- Copy the existing production compose service architecture **only after** removing shared container names, host port collisions, and development credentials. Start workers and the scheduler only after database and queue isolation is proven.
- Before running migrations, verify the new DB is disposable and contains no production data.

## 2. Configure Selcom (do not expose values in logs/screenshots)

Variables read by `config/services.php`:
```text
SELCOM_ENABLED=false
SELCOM_VENDOR_ID=<preview merchant or vendor ID>
SELCOM_API_KEY=<preview API key>
SELCOM_API_SECRET=<preview API secret>
SELCOM_BASE_URL=<documented environment-specific endpoint>
SELCOM_TIMEOUT=30
```
Use Selcom-approved test credentials if available. Production credentials require explicit controlled live testing and real charges. Keep Selcom disabled until acceptance; a merchant gateway row can be enabled/default only for a specific **preview** organization after confirmation.

## 3. Read-only smoke checks from preview app container

```bash
cd /var/www/html
php artisan about --only=environment
php artisan route:list --path=payments
php artisan list | grep -E 'payments:reconcile|payments:reconcile-expired-selcom'
php artisan schedule:list
php artisan tinker --execute='
dump([
    "selcom_enabled" => config("services.selcom.enabled"),
    "vendor_configured" => filled(config("services.selcom.vendor_id")),
    "api_key_configured" => filled(config("services.selcom.api_key")),
    "secret_configured" => filled(config("services.selcom.api_secret")),
    "app_url" => config("app.url"),
    "db_name" => DB::connection()->getDatabaseName(),
    "queue_default" => config("queue.default"),
]);
'
```
Check that the app URL and DB name are the new preview values. Never print credential values.

## 4. No-charge callback security check

```bash
curl -s -o /dev/null -w '%{http_code}\n' -X POST \
  https://<preview-domain>/payments/selcom/webhook \
  -H 'Content-Type: application/json' \
  -d '{"order_id":"TEST-UNSIGNED-WEBHOOK"}'
```
Expect 401 (not 419/404/500). Inspect sanitized rejection log only; this **does not** establish valid signed callback delivery.

## 5. Acceptance gates before any full live activation

1. CI is green for release PR #174; payout and payment regression tests remain green.
2. Checkout GET/POST render as expected; buyer cannot initiate duplicate provider orders, including uncertain network failures and repeat wallet pushes.
3. One provider-approved real/sandbox QR test: Selcom reports COMPLETED with matching merchant reference, amount and currency; one payment and one ticket fulfillment only.
4. One provider-approved wallet push test: verify account authorization, real handset approval/cancellation, and status transition; a 111/PENDING response alone **does not prove a successful payment**.
5. Genuine **signed** Selcom webhook observed, verified by status API and idempotent on delivery replay. Unsigned callback rejected.
6. Test dropped/missed webhook recovery by scheduler `payments:reconcile`; review `payments:reconcile-expired-selcom` output and ensure late completed/expired payments are manually investigated without duplicate tickets.
7. Verify communications and queue workers; outbound messaging to real users must not be triggered during isolated preview tests.
8. Review cashier/merchant account balance and seller payout constraints. Preserve `main`'s payout settlement, clearance, transfer-evidence and authorization checks.
9. Prepare deployment backup and rollback; never revert paid orders or overwrite production data.

## 6. Production go/no-go

Only after all above gates pass: review PR #174, obtain release approval, merge to main, back up production DB, deploy in a controlled window, configure production Selcom secrets, test routes and worker health, then enable gateway for a limited cohort and monitor payment-status anomalies. Do **not** remove existing gateway until Selcom is proven under production traffic.

**Current policy: Release branch is a candidate; production payments remain unchanged.**
