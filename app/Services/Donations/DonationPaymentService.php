<?php

namespace App\Services\Donations;

use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DonationPaymentService
{
    public function __construct(
        protected PaymentService $paymentService
    ) {
    }

    public function create(Donation $donation): Payment
    {
        $donation->loadMissing('campaign.organization');

        $campaign = $donation->campaign;

        if (! $campaign) {
            throw new RuntimeException('Donation campaign is missing.');
        }

        if (
            ! in_array(
                $campaign->payment_mode,
                [
                    DonationCampaign::PAYMENT_MODE_PLATFORM,
                    DonationCampaign::PAYMENT_MODE_HYBRID,
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Online payments are not enabled for this donation campaign.'
            );
        }

        if ($donation->isCompleted()) {
            throw new RuntimeException('This donation is already completed.');
        }

        $gateway = $this->defaultGatewayForOrganization(
            (int) $campaign->organization_id
        );

        return DB::transaction(function () use (
            $donation,
            $campaign,
            $gateway
        ): Payment {
            $lockedDonation = Donation::query()
                ->lockForUpdate()
                ->findOrFail($donation->id);

            $existing = Payment::query()
                ->where('donation_id', $lockedDonation->id)
                ->whereIn('status', [
                    Payment::STATUS_PENDING,
                    Payment::STATUS_PROCESSING,
                ])
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            $reference = $lockedDonation->reference . '-PAY';

            if (Payment::query()->where('reference', $reference)->exists()) {
                $reference .= '-' . strtoupper(
                    substr(bin2hex(random_bytes(3)), 0, 6)
                );
            }

            $payment = Payment::query()->create([
                'organization_id' => $campaign->organization_id,
                'event_id' => $campaign->event_id,
                'donation_id' => $lockedDonation->id,
                'payment_gateway_id' => $gateway->id,
                'reference' => $reference,
                'amount' => $lockedDonation->amount,
                'currency' => strtoupper((string) $lockedDonation->currency),
                'status' => Payment::STATUS_PENDING,
                'description' => 'Donation for ' . $campaign->title,
                'initiated_at' => now(),
            ]);

            if ($lockedDonation->status === Donation::STATUS_PENDING) {
                $lockedDonation->forceFill([
                    'status' => Donation::STATUS_AWAITING_PAYMENT,
                ])->save();
            }

            return $payment;
        });
    }

    public function start(Payment $payment): array
    {
        if (! $payment->donation_id) {
            throw new RuntimeException('Payment is not linked to a donation.');
        }

        return $this->paymentService->start($payment);
    }

    private function defaultGatewayForOrganization(
        int $organizationId
    ): PaymentGateway {
        $gateway = PaymentGateway::query()
            ->where('is_enabled', true)
            ->where('is_default', true)
            ->where(function ($query) use ($organizationId): void {
                $query
                    ->where('organization_id', $organizationId)
                    ->orWhereNull('organization_id');
            })
            ->orderByRaw(
                'CASE WHEN organization_id = ? THEN 0 ELSE 1 END',
                [$organizationId]
            )
            ->first();

        if (! $gateway) {
            throw new RuntimeException(
                'No enabled default payment gateway is configured.'
            );
        }

        return $gateway;
    }
}
