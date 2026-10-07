<?php

namespace App\Filament\Resources\DonationPaymentMethods\Pages;

use App\Filament\Resources\DonationPaymentMethods\DonationPaymentMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDonationPaymentMethods extends ListRecords
{
    protected static string $resource = DonationPaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
