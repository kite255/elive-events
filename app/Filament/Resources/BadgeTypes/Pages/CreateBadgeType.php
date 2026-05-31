<?php

namespace App\Filament\Resources\BadgeTypes\Pages;

use App\Filament\Resources\BadgeTypes\BadgeTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBadgeType extends CreateRecord
{
    protected static string $resource = BadgeTypeResource::class;
}
