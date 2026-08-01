<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Models\EventMerchandise;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class MerchandiseRelationManager extends RelationManager
{
    protected static string $relationship = 'merchandise';

    protected static ?string $title = 'Event Merchandise';

    protected static ?string $modelLabel = 'Merchandise Item';

    protected static ?string $pluralModelLabel = 'Event Merchandise';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Item name')
                    ->placeholder('Event T-shirt')
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('Description')
                    ->placeholder(
                        'Example: Official event T-shirt available in different sizes and colors.'
                    )
                    ->rows(3)
                    ->columnSpanFull(),

                FileUpload::make('image_path')
                    ->label('Merchandise image')
                    ->image()
                    ->disk('public')
                    ->directory('event-merchandise')
                    ->imageEditor()
                    ->maxSize(3072)
                    ->helperText(
                        'Upload JPG, PNG or WebP. Maximum size: 3 MB.'
                    )
                    ->columnSpanFull(),

                Toggle::make('show_image')
                    ->label('Show this image')
                    ->helperText(
                        'The image appears only when the event-level merchandise image setting is also enabled.'
                    )
                    ->default(true),

                Select::make('selection_type')
                    ->label('Selection type')
                    ->options([
                        'optional' => 'Optional',
                        'required' => 'Required',
                        'automatic' => 'Automatically allocated',
                        'admin_only' => 'Admin only',
                    ])
                    ->default('optional')
                    ->required()
                    ->native(false),

                TextInput::make('maximum_per_attendee')
                    ->label('Maximum quantity per attendee')
                    ->helperText(
                        'Example: Enter 2 to allow an attendee to select up to two T-shirts.'
                    )
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->required(),

                DateTimePicker::make('selection_opens_at')
                    ->label('Selection opens')
                    ->native(false)
                    ->seconds(false),

                DateTimePicker::make('selection_closes_at')
                    ->label('Selection closes')
                    ->native(false)
                    ->seconds(false)
                    ->after('selection_opens_at'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText(
                        'Inactive merchandise is hidden from attendee selection.'
                    )
                    ->default(true),

                TextInput::make('display_order')
                    ->label('Display order')
                    ->helperText('Lower numbers appear first.')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),

                Repeater::make('variants')
                    ->label('Size, Color, Stock and Price Variants')
                    ->relationship()
                    ->schema([
                        TextInput::make('name')
                            ->label('Variant name')
                            ->placeholder('Large / Black')
                            ->helperText(
                                'Use a clear name that combines the size and color.'
                            )
                            ->required()
                            ->maxLength(255),

                        Select::make('size')
                            ->label('Size')
                            ->options([
                                'XS' => 'Extra Small (XS)',
                                'S' => 'Small (S)',
                                'M' => 'Medium (M)',
                                'L' => 'Large (L)',
                                'XL' => 'Extra Large (XL)',
                                'XXL' => '2XL',
                                'XXXL' => '3XL',
                                'standard' => 'Standard / No size',
                            ])
                            ->searchable()
                            ->native(false)
                            ->placeholder('Select size'),

                        TextInput::make('color_name')
                            ->label('Color name')
                            ->placeholder('Black')
                            ->maxLength(100),

                        ColorPicker::make('color_code')
                            ->label('Color')
                            ->helperText(
                                'Select the visual color for this variant.'
                            ),

                        TextInput::make('sku')
                            ->label('SKU')
                            ->placeholder('TSHIRT-L-BLK')
                            ->helperText(
                                'Optional unique stock code for this variant.'
                            )
                            ->maxLength(255),

                        TextInput::make('stock_quantity')
                            ->label('Stock quantity')
                            ->helperText(
                                'Total available physical stock for this exact size and color.'
                            )
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),

                        TextInput::make('price')
                            ->label('Unit price')
                            ->helperText(
                                'Enter 0 when this merchandise is free.'
                            )
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),

                        Select::make('currency')
                            ->label('Currency')
                            ->options([
                                'TZS' => 'Tanzanian Shilling (TZS)',
                                'USD' => 'US Dollar (USD)',
                            ])
                            ->default('TZS')
                            ->required()
                            ->native(false),

                        Toggle::make('is_active')
                            ->label('Available for selection')
                            ->helperText(
                                'Inactive variants are hidden from attendees.'
                            )
                            ->default(true),

                        TextInput::make('display_order')
                            ->label('Display order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Add Size or Color Variant')
                    ->reorderable('display_order')
                    ->collapsible()
                    ->cloneable()
                    ->itemLabel(function (array $state): string {
                        if (filled($state['name'] ?? null)) {
                            return $state['name'];
                        }

                        $parts = array_filter([
                            $state['size'] ?? null,
                            $state['color_name'] ?? null,
                        ]);

                        return filled($parts)
                            ? implode(' / ', $parts)
                            : 'New Variant';
                    })
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('display_order')
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Image')
                    ->disk('public')
                    ->square()
                    ->defaultImageUrl(null)
                    ->visibility(
                        fn (EventMerchandise $record): bool =>
                            (bool) $record->show_image
                            && (bool) $record->event?->show_merchandise_images
                            && filled($record->image_path)
                    ),

                TextColumn::make('name')
                    ->label('Merchandise')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('selection_type')
                    ->label('Selection')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'optional' => 'Optional',
                            'required' => 'Required',
                            'automatic' => 'Automatic',
                            'admin_only' => 'Admin only',
                            default => ucfirst($state ?: 'Unknown'),
                        }
                    )
                    ->sortable(),

                TextColumn::make('maximum_per_attendee')
                    ->label('Maximum per attendee')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('variants_count')
                    ->label('Variants')
                    ->counts('variants')
                    ->badge(),

                TextColumn::make('expected_quantity')
                    ->label('Expected')
                    ->getStateUsing(
                        fn (EventMerchandise $record): int => (int) $record
                            ->selections()
                            ->whereIn('status', [
                                'selected',
                                'reserved',
                            ])
                            ->sum('quantity')
                    )
                    ->badge(),

                TextColumn::make('distributed_quantity')
                    ->label('Distributed')
                    ->getStateUsing(
                        fn (EventMerchandise $record): int => (int) $record
                            ->selections()
                            ->where('status', 'distributed')
                            ->sum('quantity')
                    )
                    ->badge()
                    ->toggleable(),

                TextColumn::make('total_stock')
                    ->label('Stock')
                    ->getStateUsing(
                        fn (EventMerchandise $record): int => (int) $record
                            ->variants()
                            ->sum('stock_quantity')
                    )
                    ->badge(),

                TextColumn::make('remaining_stock')
                    ->label('Remaining')
                    ->getStateUsing(function (
                        EventMerchandise $record
                    ): int {
                        $stock = (int) $record
                            ->variants()
                            ->sum('stock_quantity');

                        $reserved = (int) $record
                            ->selections()
                            ->whereIn('status', [
                                'selected',
                                'reserved',
                            ])
                            ->sum('quantity');

                        $distributed = (int) $record
                            ->selections()
                            ->where('status', 'distributed')
                            ->sum('quantity');

                        return max(
                            0,
                            $stock - $reserved - $distributed
                        );
                    })
                    ->badge(),

                TextColumn::make('price_range')
                    ->label('Price')
                    ->getStateUsing(function (
                        EventMerchandise $record
                    ): string {
                        $minimum = $record
                            ->variants()
                            ->where('is_active', true)
                            ->min('price');

                        $maximum = $record
                            ->variants()
                            ->where('is_active', true)
                            ->max('price');

                        $currency = $record
                            ->variants()
                            ->where('is_active', true)
                            ->value('currency') ?: 'TZS';

                        if ($minimum === null) {
                            return 'No variants';
                        }

                        if ((float) $minimum <= 0 && (float) $maximum <= 0) {
                            return 'Free';
                        }

                        if ((float) $minimum === (float) $maximum) {
                            return sprintf(
                                '%s %s',
                                $currency,
                                number_format((float) $minimum, 2)
                            );
                        }

                        return sprintf(
                            '%s %s – %s',
                            $currency,
                            number_format((float) $minimum, 2),
                            number_format((float) $maximum, 2)
                        );
                    })
                    ->badge(),

                IconColumn::make('show_image')
                    ->label('Image visible')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('selection_closes_at')
                    ->label('Selection deadline')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('No deadline')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('display_order')
                    ->label('Order')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('selection_type')
                    ->label('Selection type')
                    ->options([
                        'optional' => 'Optional',
                        'required' => 'Required',
                        'automatic' => 'Automatic',
                        'admin_only' => 'Admin only',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Active status')
                    ->trueLabel('Active items')
                    ->falseLabel('Inactive items')
                    ->native(false),

                TernaryFilter::make('show_image')
                    ->label('Image visibility')
                    ->trueLabel('Images shown')
                    ->falseLabel('Images hidden')
                    ->native(false),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Merchandise')
                    ->icon('heroicon-o-plus'),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate_selected')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Activate selected merchandise?')
                        ->modalDescription(
                            'The selected merchandise items will become active.'
                        )
                        ->modalSubmitActionLabel('Activate')
                        ->action(function (Collection $records): void {
                            $records->each(
                                fn (EventMerchandise $record) => $record->update([
                                    'is_active' => true,
                                ])
                            );
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('deactivate_selected')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate selected merchandise?')
                        ->modalDescription(
                            'The selected merchandise items will be hidden from attendee registration.'
                        )
                        ->modalSubmitActionLabel('Deactivate')
                        ->action(function (Collection $records): void {
                            $records->each(
                                fn (EventMerchandise $record) => $record->update([
                                    'is_active' => false,
                                ])
                            );
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('show_images_selected')
                        ->label('Show Images')
                        ->icon('heroicon-o-photo')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Show images for selected merchandise?')
                        ->modalDescription(
                            'Images will be shown when the event-level merchandise image setting is enabled.'
                        )
                        ->modalSubmitActionLabel('Show Images')
                        ->action(function (Collection $records): void {
                            $records->each(
                                fn (EventMerchandise $record) => $record->update([
                                    'show_image' => true,
                                ])
                            );
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('hide_images_selected')
                        ->label('Hide Images')
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Hide images for selected merchandise?')
                        ->modalDescription(
                            'The selected merchandise images will no longer appear on public registration forms.'
                        )
                        ->modalSubmitActionLabel('Hide Images')
                        ->action(function (Collection $records): void {
                            $records->each(
                                fn (EventMerchandise $record) => $record->update([
                                    'show_image' => false,
                                ])
                            );
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query
                    ->withCount('variants')
                    ->orderBy('display_order')
                    ->orderBy('id')
            );
    }
}