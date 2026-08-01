<?php

namespace App\Filament\Resources\Events;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Filament\Resources\Events\RelationManagers\AttendeesRelationManager;
use App\Filament\Resources\Events\RelationManagers\BadgeTemplatesRelationManager;
use App\Filament\Resources\Events\RelationManagers\BadgeTypesRelationManager;
use App\Filament\Resources\Events\RelationManagers\CheckInPointsRelationManager;
use App\Filament\Resources\Events\RelationManagers\CheckInsRelationManager;
use App\Filament\Resources\Events\RelationManagers\DaysRelationManager;
use App\Filament\Resources\Events\RelationManagers\MerchandiseOrdersRelationManager;
use App\Filament\Resources\Events\RelationManagers\MerchandiseRelationManager;
use App\Filament\Resources\Events\RelationManagers\RegistrationFieldsRelationManager;
use App\Filament\Resources\Events\Schemas\EventForm;
use App\Filament\Resources\Events\Tables\EventsTable;
use App\Models\Event;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Events';

    protected static ?string $modelLabel = 'Event';

    protected static ?string $pluralModelLabel = 'Events';

    protected static string|UnitEnum|null $navigationGroup =
        'Event Management';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return EventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventsTable::configure($table);
    }

    /**
     * Build the public registration URL for an event.
     */
    public static function getPublicRegistrationUrl(Event $event): string
    {
        return url(
            '/events/' . $event->slug . '/register'
        );
    }

    /**
     * Determine whether the public registration page should be available.
     */
    public static function canOpenPublicRegistration(
        Event $event
    ): bool {
        return filled($event->slug)
            && (bool) $event->registration_is_open
            && $event->status === 'active';
    }

    /**
     * Relation order on the Edit Event page:
     *
     * 0 - Attendees
     * 1 - Event Days
     * 2 - Event Merchandise
     * 3 - Attendee Merchandise Orders
     * 4 - Registration Fields
     * 5 - Badge Templates
     * 6 - Badge Types
     * 7 - Check-in Points
     * 8 - Check-ins
     */
    public static function getRelations(): array
    {
        return [
            AttendeesRelationManager::class,
            DaysRelationManager::class,
            MerchandiseRelationManager::class,
            MerchandiseOrdersRelationManager::class,
            RegistrationFieldsRelationManager::class,
            BadgeTemplatesRelationManager::class,
            BadgeTypesRelationManager::class,
            CheckInPointsRelationManager::class,
            CheckInsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }
}
