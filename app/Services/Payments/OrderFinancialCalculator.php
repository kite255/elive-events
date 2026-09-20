<?php

namespace App\Services\Payments;

use App\Models\EventPaymentSetting;
use App\Models\TicketOrder;
use InvalidArgumentException;

class OrderFinancialCalculator
{
    public function calculate(
        TicketOrder $order,
        ?EventPaymentSetting $settings
    ): array {
        $grossAmount = round(
            (float) $order->total,
            2
        );

        $platformCommissionRate = round(
            (float) (
                $settings?->platform_commission_rate
                ?? 0
            ),
            2
        );

        $gatewayFeeRate = round(
            (float) (
                $settings?->gateway_fee_rate
                ?? 0
            ),
            2
        );

        $gatewayFeeBearer =
            (string) (
                $settings?->gateway_fee_bearer
                ?? 'organizer'
            );

        if (
            $platformCommissionRate < 0
            || $platformCommissionRate > 100
        ) {
            throw new InvalidArgumentException(
                'Platform commission rate must be between 0 and 100.'
            );
        }

        if (
            $gatewayFeeRate < 0
            || $gatewayFeeRate > 100
        ) {
            throw new InvalidArgumentException(
                'Gateway fee rate must be between 0 and 100.'
            );
        }

        if (
            ! in_array(
                $gatewayFeeBearer,
                [
                    'organizer',
                    'elive',
                    'customer',
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Unsupported gateway fee bearer.'
            );
        }

        $platformCommissionAmount = round(
            $grossAmount
            * (
                $platformCommissionRate
                / 100
            ),
            2
        );

        $gatewayFeeAmount = round(
            $grossAmount
            * (
                $gatewayFeeRate
                / 100
            ),
            2
        );

        $gatewayDeductionFromOrganizer =
            $gatewayFeeBearer === 'organizer'
                ? $gatewayFeeAmount
                : 0.00;

        $totalCharges = round(
            $platformCommissionAmount
            + $gatewayDeductionFromOrganizer,
            2
        );

        $organizerNetAmount = round(
            max(
                0,
                $grossAmount
                - $totalCharges
            ),
            2
        );

        return [
            'gross_amount' =>
                $grossAmount,

            'platform_commission_rate' =>
                $platformCommissionRate,

            'platform_commission_amount' =>
                $platformCommissionAmount,

            'gateway_fee_rate' =>
                $gatewayFeeRate,

            'gateway_fee_amount' =>
                $gatewayFeeAmount,

            'total_charges' =>
                $totalCharges,

            'organizer_net_amount' =>
                $organizerNetAmount,

            'financial_snapshot_at' =>
                now(),
        ];
    }
}