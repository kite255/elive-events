<?php

namespace App\Filament\Resources\DonationPaymentMethods\Pages;

use App\Filament\Resources\DonationPaymentMethods\DonationPaymentMethodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDonationPaymentMethod extends EditRecord
{
    protected static string $resource = DonationPaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
