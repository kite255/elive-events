<?php

namespace Tests\Feature\Payments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
    public function test_unsigned_webhook_is_logged_once_per_minute_without_request_data(): void
    {
        config()->set('cache.default', 'array');
        config()->set('services.selcom.api_key', 'test-api-key');
        config()->set('services.selcom.api_secret', 'test-api-secret');
        Http::preventStrayRequests();
        Http::fake();
        Log::spy();

        foreach (range(1, 3) as $attempt) {
            $this->postJson(route('payments.selcom.webhook'), [
                'order_id' => 'PRIVATE-ORDER-REFERENCE',
                'buyer_phone' => '255712345678',
            ])->assertUnauthorized();
        }

        Log::shouldHaveReceived('notice')->once()->with(
            'Selcom webhook authentication rejected.',
            \Mockery::on(fn ($context) => $context === ['reason' => 'missing_headers'])
        );
        Http::assertNothingSent();
    }

    public function test_bad_signed_digest_is_classified_without_exposing_signature(): void
    {
        config()->set('cache.default', 'array');
        config()->set('services.selcom.api_key', 'test-api-key');
        config()->set('services.selcom.api_secret', 'test-api-secret');
        Http::preventStrayRequests();
        Http::fake();
        Log::spy();

        $this->withHeaders([
            'Authorization' => 'SELCOM ' . base64_encode('test-api-key'),
            'Timestamp' => now()->toIso8601String(),
            'Digest-Method' => 'HS256',
            'Signed-Fields' => 'order_id',
            'Digest' => 'SECRET-INCORRECT-DIGEST',
        ])->postJson(route('payments.selcom.webhook'), [
            'order_id' => 'PRIVATE-ORDER-REFERENCE',
        ])->assertUnauthorized();

        Log::shouldHaveReceived('notice')->once()->with(
            'Selcom webhook authentication rejected.',
            \Mockery::on(fn ($context) => $context === ['reason' => 'invalid_signature'])
        );
        Http::assertNothingSent();
    }

    public function test_old_signed_timestamp_is_classified_before_provider_lookup(): void
    {
        config()->set('cache.default', 'array');
        config()->set('services.selcom.api_key', 'test-api-key');
        config()->set('services.selcom.api_secret', 'test-api-secret');
        Http::preventStrayRequests();
        Http::fake();
        Log::spy();

        $timestamp = now()->subMinutes(15)->toIso8601String();
        $digest = base64_encode(hash_hmac(
            'sha256',
            'timestamp=' . $timestamp . '&order_id=PRIVATE-ORDER',
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
            'order_id' => 'PRIVATE-ORDER',
        ])->assertUnauthorized();

        Log::shouldHaveReceived('notice')->once()->with(
            'Selcom webhook authentication rejected.',
            \Mockery::on(fn ($context) => $context === ['reason' => 'invalid_timestamp'])
        );
        Http::assertNothingSent();
    }

}
