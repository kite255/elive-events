<?php

namespace App\Filament\Resources\OrganizationTicketTemplates;

use App\Filament\Resources\OrganizationTicketTemplates\Pages\CreateOrganizationTicketTemplate;
use App\Filament\Resources\OrganizationTicketTemplates\Pages\DesignOrganizationTicketTemplate;
use App\Filament\Resources\OrganizationTicketTemplates\Pages\EditOrganizationTicketTemplate;
use App\Filament\Resources\OrganizationTicketTemplates\Pages\ListOrganizationTicketTemplates;
use App\Filament\Resources\OrganizationTicketTemplates\Schemas\OrganizationTicketTemplateForm;
use App\Filament\Resources\OrganizationTicketTemplates\Tables\OrganizationTicketTemplatesTable;
use App\Models\OrganizationTicketTemplate;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OrganizationTicketTemplateResource extends Resource
{
    protected static ?string $model =
        OrganizationTicketTemplate::class;

    protected static ?string $recordTitleAttribute =
        'name';

    protected static string|UnitEnum|null $navigationGroup =
        'Ticketing';

    protected static ?string $navigationLabel =
        'Ticket Template Library';

    protected static ?string $modelLabel =
        'Organization Ticket Template';

    protected static ?string $pluralModelLabel =
        'Ticket Template Library';

    protected static ?int $navigationSort = 30;

    public static function form(
        Schema $schema
    ): Schema {
        return OrganizationTicketTemplateForm::configure(
            $schema
        );
    }

    public static function table(
        Table $table
    ): Table {
        return OrganizationTicketTemplatesTable::configure(
            $table
        );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isSuperAdmin()
            ?? false;
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

            'designer' =>
                DesignOrganizationTicketTemplate::route(
                    '/{record}/designer'
                ),
        ];
    }
}