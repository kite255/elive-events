<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SelcomService implements PaymentGateway
{
    public function code(): string
    {
        return 'selcom';
    }

    public function createPayment(Payment $payment): array
    {
        if (! config('services.selcom.enabled', false)) {
            throw new RuntimeException('Selcom checkout is not enabled.');
        }

        $payment->loadMissing(['attendee', 'ticketOrder', 'ticketUpgrade.order', 'donation', 'merchandiseOrder']);
        $buyer = $payment->attendee;
        $order = $payment->ticketOrder ?: $payment->ticketUpgrade?->order;
        $donation = $payment->donation;
        $merchandise = $payment->merchandiseOrder;
        $name = trim((string) ($buyer?->full_name ?: $order?->buyer_name ?: $donation?->donor_name ?: $merchandise?->buyer_name));
        $email = trim((string) ($buyer?->email ?: $order?->buyer_email ?: $donation?->donor_email ?: $merchandise?->buyer_email));
        $phone = $this->normalizeTanzanianPhone((string) ($buyer?->phone ?: $order?->buyer_phone ?: $donation?->donor_phone ?: $merchandise?->buyer_phone));
        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (! $name || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! preg_match('/^255\d{9}$/', $phone)) {
            throw new RuntimeException('Selcom checkout requires a valid buyer name, email and phone in 255XXXXXXXXX format.');
        }

        if (strtoupper($payment->currency) !== 'TZS') {
            throw new RuntimeException('Selcom is currently configured only for TZS payments.');
        }

        $statusUrl = route('payments.status', ['payment' => $payment->reference]);
        // Selcom Support specifically requested Create Order - Minimal.
        // This endpoint does not support card checkout; retain the redirect,
        // cancellation and webhook URLs for the supported payment methods.
        $payload = [
            'vendor' => $this->vendor(),
            'order_id' => $payment->reference,
            'buyer_email' => $email,
            'buyer_name' => $name,
            'buyer_phone' => $phone,
            'amount' => (string) $payment->amount,
            'currency' => 'TZS',
            'redirect_url' => base64_encode($statusUrl),
            'cancel_url' => base64_encode($statusUrl),
            'webhook' => base64_encode(route('payments.selcom.webhook')),
            'no_of_items' => '1',
        ];

        $data = $this->request('POST', '/v1/checkout/create-order-minimal', $payload);
        $urlValue = data_get($data, 'data.0.payment_gateway_url', '');
        $url = $this->checkoutUrl((string) $urlValue);
        if ($url === '') {
            throw new RuntimeException('Selcom did not return a valid HTTPS checkout URL.');
        }

        return [
            'merchant_reference' => $payment->reference,
            'order_tracking_id' => $payment->reference,
            'redirect_url' => $url,
            'selcom_response' => $data,
        ];
    }

    /**
     * Request a wallet pull on an existing minimal checkout order.
     * Selcom resultcode 111/PENDING means the phone prompt was requested,
     * NOT that money was received.
     */
    public function requestWalletPayment(string $orderId, string $transactionId, string $phone): array
    {
        if (! config('services.selcom.enabled', false)) {
            throw new RuntimeException('Selcom checkout is not enabled.');
        }

        $phone = $this->normalizeTanzanianPhone($phone);
        if (! preg_match('/^255\d{9}$/', $phone)
            || ! preg_match('/^[A-Za-z0-9-]{8,64}$/', $orderId)
            || ! preg_match('/^[A-Za-z0-9-]{8,64}$/', $transactionId)) {
            throw new RuntimeException('Valid order, transaction and Tanzanian mobile number are required.');
        }

        $response = $this->request('POST', '/v1/checkout/wallet-payment', [
            'transid' => $transactionId,
            'order_id' => $orderId,
            'msisdn' => $phone,
        ], allowPending: true);

        if ((string) ($response['resultcode'] ?? '') !== '111'
            || strtoupper((string) ($response['result'] ?? '')) !== 'PENDING') {
            // Selcom may eventually document other accepted initiation codes.
            // Fail closed until they have been verified for our merchant.
            throw new RuntimeException('Selcom did not confirm the wallet authorization request as pending.');
        }

        return ['status' => 'PENDING'];
    }

    public function getPaymentStatus(string $providerTrackingId): array
    {
        if (trim($providerTrackingId) === '') {
            throw new RuntimeException('Selcom order ID is required.');
        }

        $data = $this->request('GET', '/v1/checkout/order-status', ['order_id' => $providerTrackingId]);
        $row = data_get($data, 'data.0');
        if (! is_array($row) || (string) ($row['order_id'] ?? '') !== $providerTrackingId) {
            throw new RuntimeException('Selcom returned an unexpected order ID.');
        }

        $status = strtoupper(trim((string) ($row['payment_status'] ?? '')));
        if (! in_array($status, ['PENDING', 'INPROGRESS', 'COMPLETED', 'REJECTED', 'CANCELLED', 'USERCANCELLED'], true)) {
            throw new RuntimeException('Selcom returned an unknown payment status.');
        }

        if ($status === 'COMPLETED' && trim((string) ($row['transid'] ?? '')) === '') {
            throw new RuntimeException('Selcom completed payment is missing its transaction ID.');
        }

        return [
            'merchant_reference' => $providerTrackingId,
            'order_tracking_id' => $providerTrackingId,
            // Checkout's status response does not include currency. Our
            // create-order guard restricts Selcom orders to TZS only.
            'currency' => 'TZS',
            'amount' => $row['amount'] ?? null,
            'payment_status_description' => $status,
            'payment_method' => (string) ($row['channel'] ?? ''),
            'confirmation_code' => (string) ($row['transid'] ?? ''),
            'payment_account' => (string) ($row['msisdn'] ?? $row['phone'] ?? ''),
            'selcom_response' => $data,
        ];
    }

    public function registerIpn(string $url, string $method = 'POST'): array
    {
        throw new RuntimeException('Selcom uses a per-order webhook URL, not IPN registration.');
    }

    private function normalizeTanzanianPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone)) ?? '';
        if (preg_match('/^0\d{9}$/', $digits)) {
            return '255' . substr($digits, 1);
        }
        if (preg_match('/^\d{9}$/', $digits)) {
            return '255' . $digits;
        }
        if (preg_match('/^255\d{9}$/', $digits)) {
            return $digits;
        }
        return '';
    }

    private function vendor(): string
    {
        $vendor = trim((string) config('services.selcom.vendor_id'));
        if ($vendor === '') {
            throw new RuntimeException('SELCOM_VENDOR_ID is missing.');
        }
        return $vendor;
    }

    private function request(string $method, string $path, array $fields, bool $allowPending = false): array
    {
        $base = rtrim((string) config('services.selcom.base_url'), '/');
        $key = (string) config('services.selcom.api_key');
        $secret = (string) config('services.selcom.api_secret');
        if (! str_starts_with($base, 'https://') || $key === '' || $secret === '') {
            throw new RuntimeException('Selcom HTTPS endpoint and API credentials must be configured.');
        }

        // Selcom signs the literal timestamp followed by fields in Signed-Fields order.
        $timestamp = now()->format('Y-m-d\TH:i:sP');
        $signed = array_keys($fields);
        $message = 'timestamp=' . $timestamp;
        foreach ($fields as $field => $value) {
            $message .= '&' . $field . '=' . $value;
        }

        $headers = [
            'Authorization' => 'SELCOM ' . base64_encode($key),
            'Timestamp' => $timestamp,
            'Digest-Method' => 'HS256',
            'Digest' => base64_encode(hash_hmac('sha256', $message, $secret, true)),
            'Signed-Fields' => implode(',', $signed),
        ];

        $http = Http::acceptJson()->asJson()->withHeaders($headers)
            ->timeout((int) config('services.selcom.timeout', 30));
        $response = $method === 'GET'
            ? $http->get($base . $path, $fields)
            : $http->post($base . $path, $fields);

        $data = $response->json();
        if (! $response->successful()
            || ! is_array($data)
            || ! ((string) ($data['resultcode'] ?? '') === '000'
                && strtoupper((string) ($data['result'] ?? '')) === 'SUCCESS')
                && ! ($allowPending
                    && (string) ($data['resultcode'] ?? '') === '111'
                    && strtoupper((string) ($data['result'] ?? '')) === 'PENDING')) {
            // Only whitelist non-sensitive protocol fields; never log the body,
            // request payload, customer details, credentials or provider message.
            $code = data_get($data, 'resultcode');
            $result = data_get($data, 'result');
            Log::warning('Selcom API request rejected', [
                'operation' => $path,
                'http_status' => $response->status(),
                'result_code' => is_scalar($code) ? substr((string) $code, 0, 32) : null,
                'result' => in_array($result, ['SUCCESS', 'FAIL', 'FAILED', 'ERROR'], true) ? $result : 'unknown',
            ]);
            throw new RuntimeException('Selcom API request failed or returned a non-success response.');
        }
        return $data;
    }

    private function checkoutUrl(string $encoded): string
    {
        $decoded = base64_decode($encoded, true);
        $url = is_string($decoded) && filter_var($decoded, FILTER_VALIDATE_URL) ? $decoded : $encoded;
        if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return '';
        }
        return $url;
    }
}
