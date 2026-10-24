<?php

namespace App\Filament\Resources\Payouts\Pages;

use App\Filament\Resources\Payouts\PayoutResource;
use Filament\Resources\Pages\ListRecords;

class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;

    public function getSubheading(): ?string
    {
        return 'Internal payout request history. Listed balances do not guarantee cleared funds.';
    }
}
