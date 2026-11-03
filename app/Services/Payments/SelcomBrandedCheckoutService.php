<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class SelcomBrandedCheckoutService
{
    public function __construct(
        private PaymentService $payments,
        private TicketUpgradePaymentService $upgrades,
        private SelcomService $selcom
    ) {}

    /**
     * Initialize ONE order using the existing PaymentService locking and
     * idempotency rules. Wallet authorization is separately guarded below.
     */
    public function initialize(Payment $payment): Payment
    {
        if ($payment->gateway?->code !== 'selcom') {
            throw new RuntimeException('This checkout is not configured for Selcom.');
        }

        if ($payment->isPending() && blank($payment->provider_tracking_id)) {
            if ($payment->ticket_upgrade_id) {
                $this->upgrades->start($payment);
            } else {
                $this->payments->start($payment);
            }
        }

        return $payment->fresh(['gateway']);
    }

    /**
     * The attempt marker is committed BEFORE contacting Selcom, and remains
     * set on timeout/failure. This intentionally does not offer an automatic
     * retry that might send a second phone authorization prompt.
     */
    public function requestWallet(Payment $payment, string $phone): Payment
    {
        $lock = Cache::lock('payment:wallet:' . $payment->getKey(), 90);
        if (! $lock->get()) {
            throw new RuntimeException('A mobile payment request is already in progress.');
        }

        try {
            $payment = DB::transaction(function () use ($payment): Payment {
                $locked = Payment::query()->with('gateway')->lockForUpdate()->findOrFail($payment->getKey());

                if ($locked->gateway?->code !== 'selcom'
                    || ! $locked->isProcessing()
                    || blank($locked->provider_tracking_id)
                    || filled(data_get($locked->metadata, 'wallet_attempted_at'))) {
                    throw new RuntimeException('Mobile payment request has already been initiated or is unavailable.');
                }

                $locked->update(['metadata' => array_merge($locked->metadata ?? [], [
                    'wallet_attempted_at' => now()->toIso8601String(),
                    'wallet_transaction_id' => 'ELVW' . $locked->getKey() . Str::upper(Str::random(16)),
                ])]);

                return $locked->fresh();
            });

            try {
                $this->selcom->requestWalletPayment(
                    $payment->reference,
                    (string) data_get($payment->metadata, 'wallet_transaction_id'),
                    $phone
                );

                $payment->transactions()->create([
                    'type' => 'wallet_payment',
                    'status' => 'pending',
                    'provider_reference' => $payment->reference,
                ]);
            } catch (Throwable $e) {
                $payment->transactions()->create([
                    'type' => 'wallet_payment',
                    'status' => 'uncertain',
                    'error_message' => 'Wallet request requires reconciliation (' . class_basename($e) . ').',
                ]);
                throw $e;
            }

            return $payment->fresh();
        } finally {
            $lock->release();
        }
    }

    /**
     * Read Selcom's QR image/token returned for this exact order.
     * Encode the exact Selcom EMV QR payload without alteration.
     * Never synthesize a QR from a payment token or local order reference.
     */
    public function qrDetails(Payment $payment): array
    {
        $order = $payment->transactions()
            ->where('type', 'order_created')
            ->where('status', 'success')
            ->latest('id')
            ->first();

        $data = data_get($order?->response_payload, 'selcom_response.data.0', []);
        $token = trim((string) data_get($data, 'payment_token', ''));
        $rawQr = (string) data_get($data, 'qr', '');
        $image = null;

        // Selcom's minimal checkout returns an EMV QR payload (000201...),
        // not always an image. Render the exact provider-supplied payload.
        if (preg_match('/^000201[\x20-\x7E]{20,2040}$/D', $rawQr)) {
            $svg = (string) QrCode::format('svg')
                ->size(320)
                ->margin(2)
                ->errorCorrection('M')
                ->generate($rawQr);

            $image = 'data:image/svg+xml;base64,' . base64_encode($svg);
        } elseif (strlen($rawQr) < 100000) {
            // Keep compatibility with Base64-encoded provider PNG images.
            $bytes = base64_decode($rawQr, true);
            if (is_string($bytes) && strlen($bytes) < 75000
                && str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
                $image = 'data:image/png;base64,' . base64_encode($bytes);
            }
        }

        return ['payment_token' => $token, 'qr_image' => $image];
    }
}
