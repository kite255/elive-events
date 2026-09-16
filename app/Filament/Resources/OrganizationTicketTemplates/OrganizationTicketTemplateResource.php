<?php

namespace App\Filament\Resources\OrganizationTicketTemplates;

use App\Models\OrganizationTicketTemplate;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

class OrganizationTicketTemplateResource extends Resource
{
    protected static ?string $model =
        OrganizationTicketTemplate::class;

    protected static ?string $recordTitleAttribute =
        'name';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user?->isSuperAdmin()) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }
}
