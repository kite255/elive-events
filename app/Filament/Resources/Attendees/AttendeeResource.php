<?php

namespace App\Filament\Resources\Attendees;

use App\Filament\Resources\Attendees\Pages\CreateAttendee;
use App\Filament\Resources\Attendees\Pages\EditAttendee;
use App\Filament\Resources\Attendees\Pages\ImportAttendees;
use App\Filament\Resources\Attendees\Pages\ListAttendees;
use App\Filament\Resources\Attendees\Pages\ViewAttendee;
use App\Filament\Resources\Attendees\Pages\ViewAttendeeQrCode;
use App\Filament\Resources\Attendees\Schemas\AttendeeForm;
use App\Filament\Resources\Attendees\Tables\AttendeesTable;
use App\Models\Attendee;
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

class AttendeeResource extends Resource
{
    protected static ?string $model = Attendee::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $navigationLabel = 'Attendees';

    protected static ?string $modelLabel = 'Attendee';

    protected static ?string $pluralModelLabel = 'Attendees';

    protected static string|UnitEnum|null $navigationGroup =
        'Event Management';

    protected static ?int $navigationSort = 3;

    /*
    |--------------------------------------------------------------------------
    | Form and table
    |--------------------------------------------------------------------------
    */

    public static function form(Schema $schema): Schema
    {
        return AttendeeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendeesTable::configure($table);
    }

    /*
    |--------------------------------------------------------------------------
    | Organization-scoped query
    |--------------------------------------------------------------------------
    */

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        $query = parent::getEloquentQuery()
            ->with([
                'event.organization',
                'category',
                'badgeType',
                'eventDays',
            ]);

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereHas(
            'event',
            fn (Builder $eventQuery): Builder =>
                $eventQuery->accessibleBy($user)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | General resource authorization
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

        return $user->activeOrganizations()
            ->get()
            ->contains(
                fn (Organization $organization): bool =>
                    static::userCanViewAttendees(
                        $user,
                        $organization
                    )
            );
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
                    $user->canManageRegistration($organization)
            );
    }

    public static function canView(Model $record): bool
    {
        if (! $record instanceof Attendee) {
            return false;
        }

        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $event = $record->event;

        if (! $event || ! $event->organization_id) {
            return false;
        }

        if (! $event->isAccessibleBy($user)) {
            return false;
        }

        return static::userCanViewAttendees(
            $user,
            $event->organization
        );
    }

    public static function canEdit(Model $record): bool
    {
        if (! $record instanceof Attendee) {
            return false;
        }

        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $event = $record->event;

        if (! $event || ! $event->organization_id) {
            return false;
        }

        return $event->canManageRegistrationBy($user);
    }

    public static function canDelete(Model $record): bool
    {
        if (! $record instanceof Attendee) {
            return false;
        }

        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $event = $record->event;

        if (! $event || ! $event->organization_id) {
            return false;
        }

        /*
         * Permanent attendee removal is restricted to organization
         * owners and organization administrators.
         */
        return $user->canManageOrganization(
            $event->organization_id
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

        return $user->activeOrganizations()
            ->get()
            ->contains(
                fn (Organization $organization): bool =>
                    $user->canManageOrganization($organization)
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Import authorization
    |--------------------------------------------------------------------------
    */

    public static function canImport(): bool
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
                    $user->canManageRegistration($organization)
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Badge and QR authorization
    |--------------------------------------------------------------------------
    */

    public static function canManageBadge(
        Attendee $attendee
    ): bool {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $event = $attendee->event;

        if (! $event || ! $event->organization_id) {
            return false;
        }

        if (! $event->isAccessibleBy($user)) {
            return false;
        }

        return $event->canManageBadgesBy($user)
            || $event->canManageRegistrationBy($user);
    }

    public static function canViewQrCode(
        Attendee $attendee
    ): bool {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $event = $attendee->event;

        if (! $event || ! $event->organization_id) {
            return false;
        }

        if (! $event->isAccessibleBy($user)) {
            return false;
        }

        return $event->canManageRegistrationBy($user)
            || $event->canManageBadgesBy($user)
            || $event->canBeCheckedInBy($user);
    }

    public static function canCheckIn(
        Attendee $attendee
    ): bool {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $event = $attendee->event;

        if (! $event || ! $event->organization_id) {
            return false;
        }

        if (! $event->isAccessibleBy($user)) {
            return false;
        }

        return $event->canBeCheckedInBy($user);
    }

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canViewAny()) {
            return null;
        }

        $count = static::getEloquentQuery()->count();

        return $count > 0
            ? (string) $count
            : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return static::canViewAny()
            ? 'Accessible attendees'
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public static function getRelations(): array
    {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    public static function getPages(): array
    {
        return [
            'index' => ListAttendees::route('/'),

            'create' => CreateAttendee::route('/create'),

            'import' => ImportAttendees::route('/import'),

            'view' => ViewAttendee::route('/{record}'),

            'edit' => EditAttendee::route(
                '/{record}/edit'
            ),

            'qr-code' => ViewAttendeeQrCode::route(
                '/{record}/qr-code'
            ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Internal authorization helpers
    |--------------------------------------------------------------------------
    */

    private static function userCanViewAttendees(
        User $user,
        Organization $organization
    ): bool {
        if (! $user->hasActiveOrganizationAccess($organization)) {
            return false;
        }

        return $user->hasOrganizationRole(
            $organization,
            [
                User::ORGANIZATION_ROLE_OWNER,
                User::ORGANIZATION_ROLE_ADMIN,
                User::ORGANIZATION_ROLE_EVENT_MANAGER,
                User::ORGANIZATION_ROLE_REGISTRATION_OFFICER,
                User::ORGANIZATION_ROLE_CHECK_IN_OFFICER,
                User::ORGANIZATION_ROLE_BADGE_OFFICER,
                User::ORGANIZATION_ROLE_REPORT_VIEWER,
            ]
        );
    }
}