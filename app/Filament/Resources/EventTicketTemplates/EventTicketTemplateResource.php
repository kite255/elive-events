<?php

namespace App\Filament\Resources\EventTicketTemplates;

use App\Filament\Resources\EventTicketTemplates\Pages\CreateEventTicketTemplate;
use App\Filament\Resources\EventTicketTemplates\Pages\DesignEventTicketTemplate;
use App\Filament\Resources\EventTicketTemplates\Pages\EditEventTicketTemplate;
use App\Filament\Resources\EventTicketTemplates\Pages\ListEventTicketTemplates;
use App\Filament\Resources\EventTicketTemplates\Schemas\EventTicketTemplateForm;
use App\Filament\Resources\EventTicketTemplates\Tables\EventTicketTemplatesTable;
use App\Models\EventTicketTemplate;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class EventTicketTemplateResource extends Resource
{
    protected static ?string $model =
        EventTicketTemplate::class;

    protected static ?string $recordTitleAttribute =
        'name';

    protected static string|UnitEnum|null $navigationGroup =
        'Ticketing';

    protected static ?string $navigationLabel =
        'Event Ticket Templates';

    protected static ?string $modelLabel =
        'Event Ticket Template';

    protected static ?string $pluralModelLabel =
        'Event Ticket Templates';

    protected static ?int $navigationSort = 100;

    public static function form(
        Schema $schema
    ): Schema {
        return EventTicketTemplateForm::configure(
            $schema
        );
    }

    public static function table(
        Table $table
    ): Table {
        return EventTicketTemplatesTable::configure(
            $table
        );
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $organizationIds = $user->organizations()
            ->wherePivot(
                'status',
                User::ORGANIZATION_STATUS_ACTIVE
            )
            ->wherePivot(
                'role',
                User::ORGANIZATION_ROLE_EVENT_MANAGER
            )
            ->pluck('organizations.id');

        if ($organizationIds->isEmpty()) {
            return false;
        }

        return $user->eventManagerEvents()
            ->whereIn(
                'events.organization_id',
                $organizationIds
            )
            ->exists();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        $organizationIds = $user->organizations()
            ->wherePivot(
                'status',
                User::ORGANIZATION_STATUS_ACTIVE
            )
            ->wherePivot(
                'role',
                User::ORGANIZATION_ROLE_EVENT_MANAGER
            )
            ->pluck('organizations.id');

        if ($organizationIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $assignedEventIds = $user->eventManagerEvents()
            ->whereIn(
                'events.organization_id',
                $organizationIds
            )
            ->pluck('events.id');

        if ($assignedEventIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn(
            'event_id',
            $assignedEventIds
        );
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                ListEventTicketTemplates::route('/'),

            'create' =>
                CreateEventTicketTemplate::route('/create'),

            'edit' =>
                EditEventTicketTemplate::route(
                    '/{record}/edit'
                ),

            'designer' =>
                DesignEventTicketTemplate::route(
                    '/{record}/designer'
                ),
        ];
    }
}