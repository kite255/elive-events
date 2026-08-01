<?php

namespace App\Filament\Resources\BadgeTemplateElements\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BadgeTemplateElementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('badgeTemplate.name')
                    ->label('Template')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('label')
                    ->label('Element')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('field_key')
                    ->label('Field')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('x')
                    ->label('X')
                    ->suffix('px')
                    ->sortable(),

                TextColumn::make('y')
                    ->label('Y')
                    ->suffix('px')
                    ->sortable(),

                TextColumn::make('width')
                    ->label('W')
                    ->suffix('px')
                    ->toggleable(),

                TextColumn::make('height')
                    ->label('H')
                    ->suffix('px')
                    ->toggleable(),

                TextColumn::make('font_size')
                    ->label('Font')
                    ->suffix('px')
                    ->sortable(),

                ColorColumn::make('color')
                    ->label('Color'),

                TextColumn::make('text_align')
                    ->label('Align')
                    ->badge(),

                IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('badge_template_id')
                    ->label('Badge Template')
                    ->relationship('badgeTemplate', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('type')
                    ->options([
                        'text' => 'Text',
                        'image' => 'Image',
                        'qr' => 'QR Code',
                        'shape' => 'Shape',
                    ]),

                SelectFilter::make('field_key')
                    ->label('Field')
                    ->options([
                        'event_name' => 'Event Name',
                        'full_name' => 'Full Name',
                        'category' => 'Category',
                        'badge_type' => 'Badge Type',
                        'badge_number' => 'Badge Number',
                        'organization_name' => 'Organization',
                        'position' => 'Position',
                        'qr_code' => 'QR Code',
                        'logo' => 'Logo',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }
}