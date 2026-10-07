<?php

namespace App\Services\Donations;

use App\Models\Donation;
use App\Models\DonationManualSubmission;
use App\Models\DonationPaymentMethod;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualDonationService
{
    public function __construct(
        protected DonationAuthorizationService $authorizationService
    ) {
    }

    public function submit(
        Donation $donation,
        DonationPaymentMethod $paymentMethod,
        string $reference,
        ?UploadedFile $proof = null
    ): DonationManualSubmission {
        $donation->loadMissing('campaign');

        if (
            $paymentMethod->donation_campaign_id
            !== $donation->donation_campaign_id
        ) {
            throw ValidationException::withMessages([
                'donation_payment_method_id' =>
                    'The selected payment method does not belong to this campaign.',
            ]);
        }

        $campaign = $donation->campaign;

        if (
            $campaign === null
            || $campaign->direct_payment_behavior
                !== \App\Models\DonationCampaign::DIRECT_BEHAVIOR_TRACKED
            || ! in_array(
                $campaign->payment_mode,
                [
                    \App\Models\DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
                    \App\Models\DonationCampaign::PAYMENT_MODE_HYBRID,
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'donation' => 'This campaign does not accept tracked direct-payment submissions.',
            ]);
        }

        return DB::transaction(function () use (
            $donation,
            $paymentMethod,
            $reference,
            $proof
        ): DonationManualSubmission {
            $proofPath = null;

            if ($proof) {
                $proofPath = $proof->store(
                    'donations/proofs',
                    'local'
                );
            }

            $submission = DonationManualSubmission::query()
                ->updateOrCreate(
                    ['donation_id' => $donation->id],
                    [
                        'donation_payment_method_id' => $paymentMethod->id,
                        'transaction_reference' => $reference,
                        'proof_path' => $proofPath,
                        'submitted_at' => now(),
                        'verified_by' => null,
                        'verified_at' => null,
                        'rejection_reason' => null,
                    ]
                );

            $donation->forceFill([
                'status' => Donation::STATUS_AWAITING_VERIFICATION,
                'completed_at' => null,
            ])->save();

            return $submission;
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function approve(
        Donation $donation,
        User $user
    ): Donation {
        if (! $this->authorizationService->canVerifyDonation($user, $donation)) {
            throw new AuthorizationException(
                'You are not allowed to verify this donation.'
            );
        }

        return DB::transaction(function () use ($donation, $user): Donation {
            $lockedDonation = Donation::query()
                ->whereKey($donation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $submission = DonationManualSubmission::query()
                ->where('donation_id', $lockedDonation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedDonation->status === Donation::STATUS_COMPLETED) {
                return $lockedDonation;
            }

            if (
                $lockedDonation->status
                !== Donation::STATUS_AWAITING_VERIFICATION
                && $lockedDonation->status
                !== Donation::STATUS_PENDING
            ) {
                throw ValidationException::withMessages([
                    'donation' => 'This donation cannot be approved from its current status.',
                ]);
            }

            $now = now();

            $lockedDonation->forceFill([
                'status' => Donation::STATUS_COMPLETED,
                'completed_at' => $lockedDonation->completed_at ?? $now,
            ])->save();

            $submission->forceFill([
                'verified_by' => $submission->verified_by ?? $user->id,
                'verified_at' => $submission->verified_at ?? $now,
                'rejection_reason' => null,
            ])->save();

            return $lockedDonation->fresh();
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function reject(
        Donation $donation,
        User $user,
        string $reason
    ): Donation {
        if (! $this->authorizationService->canVerifyDonation($user, $donation)) {
            throw new AuthorizationException(
                'You are not allowed to verify this donation.'
            );
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'A rejection reason is required.',
            ]);
        }

        return DB::transaction(function () use (
            $donation,
            $user,
            $reason
        ): Donation {
            $lockedDonation = Donation::query()
                ->whereKey($donation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $submission = DonationManualSubmission::query()
                ->where('donation_id', $lockedDonation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedDonation->status === Donation::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'donation' => 'A completed donation cannot be rejected.',
                ]);
            }

            $lockedDonation->forceFill([
                'status' => Donation::STATUS_REJECTED,
                'completed_at' => null,
            ])->save();

            $submission->forceFill([
                'verified_by' => $user->id,
                'verified_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            return $lockedDonation->fresh();
        });
    }
}
