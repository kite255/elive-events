<?php

namespace App\Filament\Resources\AttendeeCategories\Pages;

use App\Filament\Resources\AttendeeCategories\AttendeeCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendeeCategory extends CreateRecord
{
    protected static string $resource = AttendeeCategoryResource::class;
}
