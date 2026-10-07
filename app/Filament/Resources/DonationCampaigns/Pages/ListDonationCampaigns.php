<?php

namespace App\Filament\Resources\DonationCampaigns\Pages;

use App\Filament\Resources\DonationCampaigns\DonationCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDonationCampaigns extends ListRecords
{
    protected static string $resource = DonationCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
