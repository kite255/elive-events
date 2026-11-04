<?php

namespace Tests\Feature\Payments;

use App\Services\Payments\SelcomService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class SelcomWalletFailureDiagnosticsTest extends TestCase
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
        Http::preventStrayRequests();
    }

    public function test_accepted_wallet_request_is_pending_and_does_not_mark_any_payment_paid(): void
    {
        Http::fake(['gateway.example.test/*' => Http::response([
            'resultcode' => '111',
            'result' => 'PENDING',
        ])]);

        $result = app(SelcomService::class)->requestWalletPayment(
            'ELV-PAY-TEST-12345', 'ELVWALLET12345678', '0712345678'
        );

        $this->assertSame(['status' => 'PENDING'], $result);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/v1/checkout/wallet-payment')
            && $request->data()['msisdn'] === '255712345678');
    }

    public function test_provider_rejection_is_logged_without_response_payload(): void
    {
        Log::spy();
        Http::fake(['gateway.example.test/*' => Http::response([
            'resultcode' => '403',
            'result' => 'FAIL',
            'message' => 'PRIVATE PROVIDER MESSAGE',
        ], 403)]);

        try {
            app(SelcomService::class)->requestWalletPayment(
                'ELV-PAY-TEST-12345', 'ELVWALLET12345678', '0712345678'
            );
            $this->fail('Expected a rejected wallet request.');
        } catch (RuntimeException $e) {
            $this->assertSame(
                'Selcom API request failed or returned a non-success response.',
                $e->getMessage()
            );
        }

        Log::shouldHaveReceived('warning')->once()->with(
            'Selcom API request rejected',
            \Mockery::on(fn ($context) => $context['operation'] === '/v1/checkout/wallet-payment'
                && $context['http_status'] === 403
                && $context['result_code'] === '403'
                && $context['failure_type'] === 'http_response'
                && ! array_key_exists('message', $context)
                && ! array_key_exists('msisdn', $context))
        );
    }

    public function test_transport_failure_is_logged_without_sensitive_details(): void
    {
        Log::spy();
        Http::fake(function () {
            throw new ConnectionException('PRIVATE PROVIDER CONNECTION DETAILS');
        });

        $this->expectException(ConnectionException::class);
        try {
            app(SelcomService::class)->requestWalletPayment(
                'ELV-PAY-TEST-12345', 'ELVWALLET12345678', '0712345678'
            );
        } finally {
            Log::shouldHaveReceived('warning')->once()->with(
                'Selcom API transport failure',
                \Mockery::on(fn ($context) => $context['operation'] === '/v1/checkout/wallet-payment'
                    && $context['failure_type'] === 'connection'
                    && $context['exception_class'] === ConnectionException::class
                    && ! array_key_exists('message', $context)
                    && ! array_key_exists('phone', $context))
            );
        }
    }
}
