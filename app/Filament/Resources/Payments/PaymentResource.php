<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Models\Event;
use App\Models\Payment;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model =
        Payment::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute =
        'reference';

    protected static ?string $navigationLabel =
        'Payments';

    protected static ?string $modelLabel =
        'Payment';

    protected static ?string $pluralModelLabel =
        'Payments';

    protected static string|UnitEnum|null $navigationGroup =
        'Finance';

    protected static ?int $navigationSort = 1;

    public static function table(
        Table $table
    ): Table {
        return PaymentsTable::configure(
            $table
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query scoping
    |--------------------------------------------------------------------------
    */

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        $query = parent::getEloquentQuery()
            ->with([
                'organization',
                'event',
                'attendee',
                'gateway',
            ]);

        if (! $user instanceof User) {
            return $query->whereRaw(
                '1 = 0'
            );
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isTicketOrganizer()) {
            $assignedEventIds =
                $user->assignedTicketingEventIds();

            if ($assignedEventIds->isEmpty()) {
                return $query->whereRaw(
                    '1 = 0'
                );
            }

            return $query->whereIn(
                'event_id',
                $assignedEventIds
            );
        }

        return $query->whereHas(
            'event',
            fn (Builder $eventQuery): Builder =>
                $eventQuery->accessibleBy(
                    $user
                )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
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

        if ($user->isTicketOrganizer()) {
            return $user
                ->ticketOrganizerOrganizations()
                ->exists();
        }

        return Event::query()
            ->accessibleBy($user)
            ->exists();
    }

    public static function canView(
        Model $record
    ): bool {
        $user = auth()->user();

        if (
            ! $user instanceof User
            || ! $record instanceof Payment
        ) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isTicketOrganizer()) {
            if ($record->event_id === null) {
                return false;
            }

            return $user
                ->assignedTicketingEvents()
                ->where(
                    'events.id',
                    $record->event_id
                )
                ->exists();
        }

        return $record
            ->event
            ?->isAccessibleBy($user)
            ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(
        Model $record
    ): bool {
        return false;
    }

    public static function canDelete(
        Model $record
    ): bool {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
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

        $pending = static::getEloquentQuery()
            ->whereIn(
                'status',
                [
                    Payment::STATUS_PENDING,
                    Payment::STATUS_PROCESSING,
                ]
            )
            ->count();

        return $pending > 0
            ? (string) $pending
            : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return static::canViewAny()
            ? 'Pending and processing payments'
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    public static function getPages(): array
    {
        return [
            'index' =>
                ListPayments::route('/'),

            'view' =>
                ViewPayment::route(
                    '/{record}'
                ),
        ];
    }
}