<?php

namespace App\Filament\Resources\MerchandiseProducts;

use App\Filament\Resources\MerchandiseProducts\Pages\ListMerchandiseProducts;
use App\Filament\Resources\MerchandiseProducts\Pages\CreateMerchandiseProduct;
use App\Filament\Resources\MerchandiseProducts\Pages\EditMerchandiseProduct;
use App\Models\Event;
use App\Models\MerchandiseProduct;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use BackedEnum;
use UnitEnum;

class MerchandiseProductResource extends Resource
{
    protected static ?string $model = MerchandiseProduct::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string|UnitEnum|null $navigationGroup = 'Event Management';
    protected static ?string $navigationLabel = 'Shop Products';

    public static function accessibleEvents(): Builder
    {
        return Event::query()->accessibleBy(auth()->user());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('event_id')->label('Event')->options(fn () => static::accessibleEvents()->get()->filter(fn (Event $event) => $event->canBeManagedBy(auth()->user()))->pluck('name', 'id')->all())->searchable()->required(),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('slug')->required()->maxLength(255)->helperText('Unique within this event.'),
            Select::make('category')->options([
                'clothing' => 'Clothing', 'accessories' => 'Accessories', 'drinkware' => 'Drinkware',
                'books' => 'Books', 'music' => 'Music', 'other' => 'Other',
            ])->default('other')->required(),
            Textarea::make('description')->columnSpanFull(),
            FileUpload::make('image_path')->image()->disk('public')->directory('merchandise/products')->maxSize(5120),
            TextInput::make('price_tzs')->label('Price (TZS)')->numeric()->minValue(1)->required(),
            TagsInput::make('colors')->label('Available colours')->helperText('Optional; leave blank for products without colour options.'),
            TagsInput::make('sizes')->label('Available sizes / options')->helperText('Optional; leave blank for products without sizes.'),
            TextInput::make('sort_order')->numeric()->default(0)->minValue(0),
            Toggle::make('is_active')->label('Published for ordering')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('event.name')->label('Event')->searchable(),
            TextColumn::make('name')->searchable(),
            TextColumn::make('category')->badge(),
            TextColumn::make('price_tzs')->label('TZS')->numeric(),
            IconColumn::make('is_active')->boolean(),
        ])->recordActions([EditAction::make()])->headerActions([CreateAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('event', fn (Builder $query) => $query->accessibleBy(auth()->user()));
    }

    public static function canViewAny(): bool
    {
        return auth()->user() instanceof User && static::accessibleEvents()->get()->contains(fn (Event $event) => $event->canBeManagedBy(auth()->user()));
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof MerchandiseProduct && $record->event?->canBeManagedBy(auth()->user()) === true;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canView($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMerchandiseProducts::route('/'),
            'create' => CreateMerchandiseProduct::route('/create'),
            'edit' => EditMerchandiseProduct::route('/{record}/edit'),
        ];
    }
}
