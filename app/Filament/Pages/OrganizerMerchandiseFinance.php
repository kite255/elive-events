<?php

namespace App\Filament\Pages;

use UnitEnum;

class OrganizerMerchandiseFinance extends OrganizerFinanceOverview
{
    protected static ?string $navigationLabel = 'Merchandise Finance';
    protected static string|UnitEnum|null $navigationGroup = 'Finance';
    protected static ?string $title = 'Merchandise Finance';
    protected static ?string $slug = 'merchandise-finance';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.organizer-merchandise-finance';
}
