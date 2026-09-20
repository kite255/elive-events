<?php

namespace App\Contracts;

use App\Models\Payment;

interface PaymentGateway
{
    /**
     * Gateway code used internally by eLive Events.
     */
    public function code(): string;

    /**
     * Submit a payment/order to the gateway and return the gateway response.
     */
    public function createPayment(
        Payment $payment
    ): array;

    /**
     * Fetch the latest transaction status directly from the gateway.
     */
    public function getPaymentStatus(
        string $providerTrackingId
    ): array;

    /**
     * Register the public IPN/webhook endpoint with the gateway.
     */
    public function registerIpn(
        string $url,
        string $method = 'POST'
    ): array;
}
