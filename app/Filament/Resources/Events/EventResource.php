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
use App\Filament\Resources\Events\RelationManagers\EventStaffRelationManager;
use App\Filament\Resources\Events\RelationManagers\MerchandiseOrdersRelationManager;
use App\Filament\Resources\Events\RelationManagers\MerchandiseRelationManager;
use App\Filament\Resources\Events\RelationManagers\RegistrationFieldsRelationManager;
use App\Filament\Resources\Events\RelationManagers\SessionsRelationManager;
use App\Filament\Resources\Events\Schemas\EventForm;
use App\Filament\Resources\Events\Tables\EventsTable;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

    /*
    |--------------------------------------------------------------------------
    | Form and table
    |--------------------------------------------------------------------------
    */

    public static function form(Schema $schema): Schema
    {
        return EventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventsTable::configure($table);
    }

    /*
    |--------------------------------------------------------------------------
    | Organization and event-scoped queries
    |--------------------------------------------------------------------------
    */

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('organization')
            ->accessibleBy(auth()->user());
    }

    /*
    |--------------------------------------------------------------------------
    | Resource authorization
    |--------------------------------------------------------------------------
    */

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return static::getEloquentQuery()->exists();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->activeOrganizations()
            ->get()
            ->contains(
                fn (Organization $organization): bool =>
                    $user->canManageOrganization($organization)
                    || $user->hasOrganizationRole(
                        $organization,
                        User::ORGANIZATION_ROLE_EVENT_MANAGER
                    )
            );
    }

    public static function canView(Model $record): bool
    {
        if (! $record instanceof Event) {
            return false;
        }

        return $record->isAccessibleBy(
            auth()->user()
        );
    }

    public static function canEdit(Model $record): bool
    {
        if (! $record instanceof Event) {
            return false;
        }

        return $record->canBeManagedBy(
            auth()->user()
        );
    }

    public static function canDelete(Model $record): bool
    {
        if (! $record instanceof Event) {
            return false;
        }

        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $record->organization_id) {
            return false;
        }

        return $user->canManageOrganization(
            $record->organization_id
        );
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->managedOrganizations()->exists();
    }

    public static function canForceDelete(
        Model $record
    ): bool {
        $user = auth()->user();

        return $user instanceof User
            && $user->isSuperAdmin();
    }

    public static function canForceDeleteAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->isSuperAdmin();
    }

    public static function canRestore(
        Model $record
    ): bool {
        $user = auth()->user();

        return $user instanceof User
            && $user->isSuperAdmin();
    }

    public static function canRestoreAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->isSuperAdmin();
    }

    /*
    |--------------------------------------------------------------------------
    | Navigation visibility
    |--------------------------------------------------------------------------
    */

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $count = static::getEloquentQuery()->count();

        return $count > 0
            ? (string) $count
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Public registration
    |--------------------------------------------------------------------------
    */

    public static function getPublicRegistrationUrl(
        Event $event
    ): string {
        return url(
            '/events/' . $event->slug . '/register'
        );
    }

    public static function canOpenPublicRegistration(
        Event $event
    ): bool {
        return filled($event->slug)
            && (bool) $event->registration_is_open
            && $event->status === Event::STATUS_ACTIVE;
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    |
    | Relation order on the Edit Event page:
    |
    | 0  - Event Staff
    | 1  - Attendees
    | 2  - Event Days
    | 3  - Sessions / Activities
    | 4  - Event Merchandise
    | 5  - Attendee Merchandise Orders
    | 6  - Registration Fields
    | 7  - Badge Templates
    | 8  - Badge Types
    | 9  - Check-in Points
    | 10 - Check-ins
    |
    */

    public static function getRelations(): array
    {
        return [
            EventStaffRelationManager::class,
            AttendeesRelationManager::class,
            DaysRelationManager::class,
            SessionsRelationManager::class,
            MerchandiseRelationManager::class,
            MerchandiseOrdersRelationManager::class,
            RegistrationFieldsRelationManager::class,
            BadgeTemplatesRelationManager::class,
            BadgeTypesRelationManager::class,
            CheckInPointsRelationManager::class,
            CheckInsRelationManager::class,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),

            'create' => CreateEvent::route(
                '/create'
            ),

            'edit' => EditEvent::route(
                '/{record}/edit'
            ),
        ];
    }
}
