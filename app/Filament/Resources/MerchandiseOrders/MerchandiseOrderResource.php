<?php

namespace App\Filament\Resources\MerchandiseOrders;

use App\Filament\Resources\MerchandiseOrders\Pages\ListMerchandiseOrders;
use App\Models\Event;
use App\Models\MerchandiseOrder;
use App\Models\User;
use BackedEnum;
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
    protected static string|UnitEnum|null $navigationGroup = 'Merchandise Management';
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
            TextColumn::make('quantity')->numeric(),
            TextColumn::make('total_tzs')->label('Total (TZS)')->numeric(),
            TextColumn::make('status')->badge(),
            TextColumn::make('payment.status')->label('Gateway Status')->badge()->placeholder('No payment'),
            TextColumn::make('payment.fulfilled_at')->label('Fulfilled')->dateTime()->placeholder('Pending review')->toggleable(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMerchandiseOrders::route('/')];
    }
}
