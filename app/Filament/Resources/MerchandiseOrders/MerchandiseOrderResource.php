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
    protected static string|UnitEnum|null $navigationGroup = 'Event Management';
    protected static ?string $navigationLabel = 'Shop Orders';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('event', fn (Builder $q) => $q->accessibleBy(auth()->user()));
    }

    public static function canViewAny(): bool
    {
        return auth()->user() instanceof User
            && Event::query()->accessibleBy(auth()->user())->get()
                ->contains(fn (Event $event) => $event->canBeManagedBy(auth()->user()));
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof MerchandiseOrder
            && $record->event?->canBeManagedBy(auth()->user()) === true;
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
            TextColumn::make('created_at')->dateTime()->sortable(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMerchandiseOrders::route('/')];
    }
}
