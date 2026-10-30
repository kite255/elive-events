<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
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
        $phone = trim((string) ($buyer?->phone ?: $order?->buyer_phone ?: $donation?->donor_phone ?: $merchandise?->buyer_phone));
        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (! $name || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! preg_match('/^255\d{9}$/', $phone)) {
            throw new RuntimeException('Selcom checkout requires a valid buyer name, email and phone in 255XXXXXXXXX format.');
        }

        if (strtoupper($payment->currency) !== 'TZS') {
            throw new RuntimeException('Selcom is currently configured only for TZS payments.');
        }

        $statusUrl = route('payments.status', ['payment' => $payment->reference]);
        $payload = [
            'vendor' => $this->vendor(),
            'order_id' => $payment->reference,
            'buyer_email' => $email,
            'buyer_name' => $name,
            'buyer_phone' => $phone,
            'amount' => (string) $payment->amount,
            'currency' => 'TZS',
            'payment_methods' => 'ALL',
            'redirect_url' => base64_encode($statusUrl),
            'cancel_url' => base64_encode($statusUrl),
            'webhook' => base64_encode(route('payments.selcom.webhook')),
            'billing.firstname' => $parts[0] ?? $name,
            'billing.lastname' => implode(' ', array_slice($parts, 1)) ?: $name,
            'billing.address_1' => (string) config('services.selcom.billing_address', 'Tanzania'),
            'billing.city' => (string) config('services.selcom.billing_city', 'Dar es Salaam'),
            'billing.state_or_region' => (string) config('services.selcom.billing_region', 'Dar es Salaam'),
            'billing.postcode_or_pobox' => (string) config('services.selcom.billing_postcode', '00000'),
            'billing.country' => 'TZ',
            'billing.phone' => $phone,
            'no_of_items' => '1',
        ];

        $data = $this->request('POST', '/v1/checkout/create-order', $payload);
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
            'payment_account' => (string) ($row['phone'] ?? ''),
            'selcom_response' => $data,
        ];
    }

    public function registerIpn(string $url, string $method = 'POST'): array
    {
        throw new RuntimeException('Selcom uses a per-order webhook URL, not IPN registration.');
    }

    private function vendor(): string
    {
        $vendor = trim((string) config('services.selcom.vendor_id'));
        if ($vendor === '') {
            throw new RuntimeException('SELCOM_VENDOR_ID is missing.');
        }
        return $vendor;
    }

    private function request(string $method, string $path, array $fields): array
    {
        $base = rtrim((string) config('services.selcom.base_url'), '/');
        $key = (string) config('services.selcom.api_key');
        $secret = (string) config('services.selcom.api_secret');
        if (! str_starts_with($base, 'https://') || $key === '' || $secret === '') {
            throw new RuntimeException('Selcom HTTPS endpoint and API credentials must be configured.');
        }

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
            || (string) ($data['resultcode'] ?? '') !== '000'
            || strtoupper((string) ($data['result'] ?? '')) !== 'SUCCESS') {
            throw new RuntimeException('Selcom API request failed or returned a non-success response.');
        }
        return $data;
    }

    private function checkoutUrl(string $encoded): string
    {
        $decoded = base64_decode($encoded, true);
        $url = filter_var($decoded, FILTER_VALIDATE_URL) ? $decoded : $encoded;
        if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return '';
        }
        return $url;
    }
}
