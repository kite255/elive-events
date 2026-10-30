<?php

namespace Tests\Feature\Payments;

use App\Services\Payments\SelcomService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SelcomServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.selcom', [
            'enabled' => true,
            'vendor_id' => 'TESTVENDOR',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'base_url' => 'https://gateway.example.test',
            'timeout' => 5,
        ]);
    }

    public function test_get_status_signs_request_and_normalizes_completed_payment(): void
    {
        Http::fake([
            'gateway.example.test/*' => Http::response([
                'result' => 'SUCCESS',
                'resultcode' => '000',
                'data' => [[
                    'order_id' => 'ELV-123',
                    'amount' => '25000',
                    'payment_status' => 'COMPLETED',
                    'transid' => 'TX123',
                    'channel' => 'MPESA-TZ',
                ]],
            ]),
        ]);

        $status = app(SelcomService::class)->getPaymentStatus('ELV-123');

        $this->assertSame('ELV-123', $status['merchant_reference']);
        $this->assertSame('COMPLETED', $status['payment_status_description']);
        $this->assertSame('TZS', $status['currency']);
        $this->assertSame('TX123', $status['confirmation_code']);

        Http::assertSent(fn ($request) =>
            $request->method() === 'GET'
            && $request->url() === 'https://gateway.example.test/v1/checkout/order-status?order_id=ELV-123'
            && $request->hasHeader('Authorization', 'SELCOM ' . base64_encode('test-key'))
            && $request->hasHeader('Digest-Method', 'HS256')
            && $request->hasHeader('Signed-Fields', 'order_id')
            && filled($request->header('Digest')[0] ?? null)
        );
    }

    public function test_selcom_digest_is_signed_using_exact_timestamp_and_field_order(): void
    {
        Http::fake(['gateway.example.test/*' => Http::response([
            'result' => 'SUCCESS',
            'resultcode' => '000',
            'data' => [[
                'order_id' => 'ELV-123',
                'amount' => '25000',
                'payment_status' => 'PENDING',
            ]],
        ])]);

        app(SelcomService::class)->getPaymentStatus('ELV-123');

        Http::assertSent(function ($request): bool {
            $timestamp = $request->header('Timestamp')[0] ?? '';
            $signedFields = $request->header('Signed-Fields')[0] ?? '';
            $digest = $request->header('Digest')[0] ?? '';

            return $signedFields === 'order_id'
                && $timestamp !== ''
                && hash_equals(
                    base64_encode(hash_hmac('sha256', 'timestamp=' . $timestamp . '&order_id=ELV-123', 'test-secret', true)),
                    $digest
                );
        });
    }

    public function test_invalid_or_missing_selcom_credentials_fail_before_any_http_request(): void
    {
        config()->set('services.selcom.api_secret', '');
        Http::fake();

        try {
            app(SelcomService::class)->getPaymentStatus('ELV-123');
            $this->fail('Expected missing credentials to prevent API requests.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('credentials', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_unexpected_order_id_fails_closed(): void
    {
        Http::fake(['gateway.example.test/*' => Http::response([
            'result' => 'SUCCESS',
            'resultcode' => '000',
            'data' => [['order_id' => 'OTHER', 'amount' => '25000', 'payment_status' => 'COMPLETED']],
        ])]);

        $this->expectException(RuntimeException::class);
        app(SelcomService::class)->getPaymentStatus('ELV-123');
    }

    public function test_unknown_gateway_payment_status_is_not_treated_as_paid(): void
    {
        Http::fake(['gateway.example.test/*' => Http::response([
            'result' => 'SUCCESS',
            'resultcode' => '000',
            'data' => [['order_id' => 'ELV-123', 'amount' => '25000', 'payment_status' => 'UNKNOWN']],
        ])]);

        $this->expectException(RuntimeException::class);
        app(SelcomService::class)->getPaymentStatus('ELV-123');
    }

    public function test_completed_status_without_transaction_id_is_rejected(): void
    {
        Http::fake(['gateway.example.test/*' => Http::response([
            'result' => 'SUCCESS',
            'resultcode' => '000',
            'data' => [[
                'order_id' => 'ELV-123',
                'amount' => '25000',
                'payment_status' => 'COMPLETED',
                'transid' => null,
            ]],
        ])]);

        $this->expectException(RuntimeException::class);
        app(SelcomService::class)->getPaymentStatus('ELV-123');
    }

    public function test_gateway_error_does_not_count_as_payment_success(): void
    {
        Http::fake(['gateway.example.test/*' => Http::response([
            'result' => 'FAIL', 'resultcode' => '403', 'data' => [],
        ])]);

        $this->expectException(RuntimeException::class);
        app(SelcomService::class)->getPaymentStatus('ELV-123');
    }
}
