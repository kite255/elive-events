<?php

namespace App\Filament\Resources\OrganizationTicketTemplates;

use App\Filament\Resources\OrganizationTicketTemplates\Pages\CreateOrganizationTicketTemplate;
use App\Filament\Resources\OrganizationTicketTemplates\Pages\EditOrganizationTicketTemplate;
use App\Filament\Resources\OrganizationTicketTemplates\Pages\ListOrganizationTicketTemplates;
use App\Filament\Resources\OrganizationTicketTemplates\Schemas\OrganizationTicketTemplateForm;
use App\Models\OrganizationTicketTemplate;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OrganizationTicketTemplateResource extends Resource
{
    protected static ?string $model =
        OrganizationTicketTemplate::class;

    protected static ?string $recordTitleAttribute =
        'name';

    public static function form(
        Schema $schema
    ): Schema {
        return OrganizationTicketTemplateForm::configure(
            $schema
        );
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user?->isSuperAdmin()) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                ListOrganizationTicketTemplates::route('/'),

            'create' =>
                CreateOrganizationTicketTemplate::route('/create'),

            'edit' =>
                EditOrganizationTicketTemplate::route(
                    '/{record}/edit'
                ),
        ];
    }
}
