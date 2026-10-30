# Selcom Checkout integration — staging acceptance

This work is isolated on `feature/selcom-payment-gateway`. **Do not deploy to production or switch the default gateway yet.**

## Configuration

Set in Coolify for the test deployment (never commit secrets):

```dotenv
SELCOM_ENABLED=false
SELCOM_VENDOR_ID=
SELCOM_API_KEY=
SELCOM_API_SECRET=
SELCOM_BASE_URL=
SELCOM_TIMEOUT=30
```

Selcom's API base URL and merchant credentials must come from Selcom onboarding. Do not assume the production endpoint is suitable for testing.

The `payment_gateways` table contains the gateway selected for each payment. Adding the service does not automatically add an enabled/default Selcom row. Existing Pesapal payments retain their gateway linkage.

## Implemented in this branch

- Selcom HMAC-SHA256 request signing and hosted full Checkout order creation
- API response order URL normalization (base64 encoded HTTPS URLs)
- Per-order webhook URL `POST /payments/selcom/webhook`
- Signed webhook validation and independent server-to-server status lookup
- Existing PaymentService dispatch / ticket fulfillment integration
- Fail-closed order ID, amount, tracking ID and TZS checks
- Provider routing by saved `payment_gateway_id`, preserving Pesapal records
- Initial mocked API status unit tests

## Before enabling Selcom

1. Obtain actual test-capable Vendor ID, API Key, API Secret, base URL and merchant Checkout access.
2. Run `php artisan test --filter=SelcomServiceTest` and payment/merchandise/upgrade/fulfillment tests.
3. Confirm order creation and webhook signature with Selcom; their documentation contains some example inconsistencies.
4. Verify the API accepts billing details for your merchant account and all buyer flows supply name/email/255-formatted phone.
5. Validate the redirect URL and webhook URL from an internet-accessible staging domain.
6. Confirm callback may be repeated and ticket fulfillment still remains idempotent.
7. Test pending, completed, rejected, cancelled, missing/altered reference, altered amount, expired ticket and late settlement.
8. Review request-response logging for personal information and never log keys or signature secrets.
9. Add an intentionally **disabled** `selcom` payment gateway row and test with a dedicated test event before changing the organization/platform default.
10. Independently validate Selcom settlements and reconciliation before any payout release.

**Note:** This is not yet merchant-certified. No real Selcom transaction has been executed and automated tests have not been run by the GitHub file editor. Do not set `SELCOM_ENABLED=true` on production.
