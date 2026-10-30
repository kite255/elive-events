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

    public function test_gateway_error_does_not_count_as_payment_success(): void
    {
        Http::fake(['gateway.example.test/*' => Http::response([
            'result' => 'FAIL', 'resultcode' => '403', 'data' => [],
        ])]);

        $this->expectException(RuntimeException::class);
        app(SelcomService::class)->getPaymentStatus('ELV-123');
    }
}
