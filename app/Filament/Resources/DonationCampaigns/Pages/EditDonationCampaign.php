<?php

namespace App\Filament\Resources\DonationCampaigns\Pages;

use App\Filament\Resources\DonationCampaigns\DonationCampaignResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDonationCampaign extends EditRecord
{
    protected static string $resource = DonationCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
