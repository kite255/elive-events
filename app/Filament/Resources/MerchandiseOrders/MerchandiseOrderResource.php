<?php

namespace App\Filament\Resources\MerchandiseOrders;

use App\Filament\Resources\MerchandiseOrders\Pages\ListMerchandiseOrders;
use App\Models\Event;
use App\Models\MerchandiseOrder;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class MerchandiseOrderResource extends Resource
{
    protected static ?string $model = MerchandiseOrder::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string|UnitEnum|null $navigationGroup = 'Merchandise';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Shop Orders';

    private static function canManageMerchandiseFor(Event $event): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $event->canBeManagedBy($user)
            && ($user->isSuperAdmin() || ! ($user->isOrganizationOwner($event->organization_id)
                || $user->hasOrganizationRole($event->organization_id, User::ORGANIZATION_ROLE_OWNER)));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('event_id', Event::query()->accessibleBy(auth()->user())->get()->filter(fn (Event $event) => static::canManageMerchandiseFor($event))->pluck('id'));
    }

    public static function canViewAny(): bool
    {
        return auth()->user() instanceof User
            && Event::query()->accessibleBy(auth()->user())->get()
                ->contains(fn (Event $event) => static::canManageMerchandiseFor($event));
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof MerchandiseOrder
            && ($record->event && static::canManageMerchandiseFor($record->event));
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order_number')->label('Reference')->searchable()->copyable(),
            TextColumn::make('event.name')->label('Event'),
            TextColumn::make('product.name')->label('Product'),
            TextColumn::make('buyer_name')->label('Customer')->searchable(),
            TextColumn::make('buyer_phone')->label('Phone')->copyable(),
            TextColumn::make('color')->label('Colour')->placeholder('—'),
            TextColumn::make('size')->placeholder('—'),
            TextColumn::make('quantity')->numeric(),
            TextColumn::make('total_tzs')->label('Total (TZS)')->numeric(),
            TextColumn::make('status')->label('Payment status')->badge(),
            TextColumn::make('fulfillment_status')->label('Collection')->badge()->color(fn (?string $state) => match ($state) {
                'collected' => 'success', 'ready_for_pickup' => 'info', default => 'warning',
            })->formatStateUsing(fn (?string $state) => match ($state) {
                'collected' => 'Collected', 'ready_for_pickup' => 'Ready for pickup', default => 'Not ready',
            }),
            TextColumn::make('payment.status')->label('Gateway Status')->badge()->placeholder('No payment'),
            TextColumn::make('payment.fulfilled_at')->label('Fulfilled')->dateTime()->placeholder('Pending review')->toggleable(),
            TextColumn::make('collected_at')->label('Collected at')->dateTime()->placeholder('—')->toggleable(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])
        ->defaultSort('created_at', 'desc')
        ->filters([
            SelectFilter::make('status')->label('Payment status')->options([
                'paid' => 'Paid', 'pending_payment' => 'Pending payment',
                'expired' => 'Expired',
            ]),
            SelectFilter::make('fulfillment_status')->label('Collection status')->options([
                'not_ready' => 'Not ready', 'ready_for_pickup' => 'Ready for pickup',
                'collected' => 'Collected',
            ]),
        ])
        ->recordActions([
            Action::make('ready_for_pickup')->label('Mark ready for pickup')
                ->icon('heroicon-o-check-circle')->requiresConfirmation()
                ->visible(fn (MerchandiseOrder $record): bool => $record->status === 'paid'
                    && $record->fulfillment_status === 'not_ready')
                ->action(fn (MerchandiseOrder $record) => $record->newQuery()
                    ->whereKey($record->id)->where('status', 'paid')
                    ->where('fulfillment_status', 'not_ready')
                    ->update(['fulfillment_status' => 'ready_for_pickup'])),
            Action::make('collected')->label('Mark collected')
                ->icon('heroicon-o-shopping-bag')->requiresConfirmation()
                ->visible(fn (MerchandiseOrder $record): bool => $record->status === 'paid'
                    && $record->fulfillment_status === 'ready_for_pickup')
                ->action(fn (MerchandiseOrder $record) => $record->newQuery()
                    ->whereKey($record->id)->where('status', 'paid')
                    ->where('fulfillment_status', 'ready_for_pickup')
                    ->update(['fulfillment_status' => 'collected', 'collected_at' => now()])),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMerchandiseOrders::route('/')];
    }
}
