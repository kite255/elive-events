<?php

namespace Tests\Feature\Payments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SelcomWebhookObservabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsigned_webhook_is_rejected_without_contacting_selcom(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->postJson(route('payments.selcom.webhook'), [
            'order_id' => 'UNKNOWN-ORDER',
            'payment_status' => 'COMPLETED',
        ])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_authenticated_unknown_order_is_rejected_without_contacting_selcom(): void
    {
        config()->set('services.selcom.api_key', 'test-api-key');
        config()->set('services.selcom.api_secret', 'test-api-secret');
        Http::preventStrayRequests();
        Http::fake();

        $timestamp = now()->toIso8601String();
        $digest = base64_encode(hash_hmac(
            'sha256',
            'timestamp=' . $timestamp . '&order_id=UNKNOWN-ORDER',
            'test-api-secret',
            true
        ));

        $this->withHeaders([
            'Authorization' => 'SELCOM ' . base64_encode('test-api-key'),
            'Timestamp' => $timestamp,
            'Digest-Method' => 'HS256',
            'Signed-Fields' => 'order_id',
            'Digest' => $digest,
        ])->postJson(route('payments.selcom.webhook'), [
            'order_id' => 'UNKNOWN-ORDER',
        ])->assertNotFound();

        Http::assertNothingSent();
    }
}
