<?php

namespace App\Filament\Resources\DonationPaymentMethods\Pages;

use App\Filament\Resources\DonationPaymentMethods\DonationPaymentMethodResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDonationPaymentMethod extends CreateRecord
{
    protected static string $resource = DonationPaymentMethodResource::class;
}
