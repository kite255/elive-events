<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource =
        PaymentResource::class;

    public function getTitle(): string
    {
        return 'Payment Transactions';
    }

    public function getSubheading(): ?string
    {
        $query =
            PaymentResource::getEloquentQuery();

        $total =
            (clone $query)->count();

        $completed =
            (clone $query)
                ->where(
                    'status',
                    Payment::STATUS_COMPLETED
                )
                ->count();

        $pending = (clone $query)->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])->count();

        $revenue =
            (clone $query)
                ->where(
                    'status',
                    Payment::STATUS_COMPLETED
                )
                ->sum('amount');

        return
            number_format($total)
            . ' transaction(s) · '
            . number_format($completed)
            . ' completed · '
            . number_format($pending)
            . ' pending/processing · Completed payment volume: '
            . number_format((float) $revenue, 2);
    }
}
