<?php

namespace App\Filament\Resources\EventTicketTemplates;

use App\Filament\Resources\EventTicketTemplates\Pages\CreateEventTicketTemplate;
use App\Filament\Resources\EventTicketTemplates\Pages\EditEventTicketTemplate;
use App\Filament\Resources\EventTicketTemplates\Pages\ListEventTicketTemplates;
use App\Filament\Resources\EventTicketTemplates\Schemas\EventTicketTemplateForm;
use App\Models\EventTicketTemplate;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class EventTicketTemplateResource extends Resource
{
    protected static ?string $model =
        EventTicketTemplate::class;

    protected static ?string $recordTitleAttribute =
        'name';

    public static function form(
        Schema $schema
    ): Schema {
        return EventTicketTemplateForm::configure(
            $schema
        );
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
        ];
    }
}
