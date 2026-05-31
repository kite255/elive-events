<?php

namespace App\Filament\Resources\CheckInPoints\Pages;

use App\Filament\Resources\CheckInPoints\CheckInPointResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCheckInPoint extends CreateRecord
{
    protected static string $resource = CheckInPointResource::class;
}
