<?php

namespace App\Filament\Resources\Payouts;

use App\Filament\Resources\Payouts\Pages\ListPayouts;
use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PayoutResource extends Resource
{
    protected static ?string $model = EventPayoutRequest::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;
    protected static string|UnitEnum|null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Payout Requests';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (
            $user->isSuperAdmin()
            || $user->ticketOrganizerOrganizations()->exists()
            || $user->managedOrganizations()->exists()
        );
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery()->with(['event', 'requester']);

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereHas('event', function (Builder $events) use ($user): void {
            $events->where(function (Builder $scope) use ($user): void {
                $scope->whereIn('organization_id', $user->managedOrganizations()->select('organizations.id'))
                    ->orWhereIn('events.id', $user->assignedTicketingEvents()->select('events.id'));
            });
        });
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof EventPayoutRequest
            && static::getEloquentQuery()->whereKey($record->getKey())->exists();
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('reference')->searchable()->copyable(),
            TextColumn::make('event.name')->label('Event')->searchable(),
            TextColumn::make('requester.name')->label('Requested By'),
            TextColumn::make('amount')->formatStateUsing(
                fn ($state, EventPayoutRequest $record): string =>
                    $record->currency.' '.number_format((float) $state, 2)
            ),
            TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                EventPayoutRequest::PAID => 'success',
                EventPayoutRequest::APPROVED => 'info',
                EventPayoutRequest::REJECTED => 'danger',
                default => 'warning',
            }),
            TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
            TextColumn::make('paid_at')->dateTime('d M Y H:i')->placeholder('Not paid'),
        ])->filters([
            SelectFilter::make('status')->options([
                EventPayoutRequest::REQUESTED => 'Requested',
                EventPayoutRequest::APPROVED => 'Approved',
                EventPayoutRequest::REJECTED => 'Rejected',
                EventPayoutRequest::PAID => 'Paid',
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPayouts::route('/')];
    }
}
