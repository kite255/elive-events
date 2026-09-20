<?php

namespace App\Filament\Resources\CommunicationCampaigns\Pages;

use App\Filament\Resources\CommunicationCampaigns\CommunicationCampaignResource;
use Filament\Resources\Pages\ListRecords;

class ListCommunicationCampaigns extends ListRecords
{
    protected static string $resource =
        CommunicationCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}