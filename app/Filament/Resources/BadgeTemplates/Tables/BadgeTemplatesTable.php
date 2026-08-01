<?php

namespace App\Filament\Resources\BadgeTemplates\Tables;

use App\Filament\Resources\BadgeTemplateElements\BadgeTemplateElementResource;
use App\Filament\Resources\BadgeTemplates\BadgeTemplateResource;
use App\Models\BadgeTemplate;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BadgeTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Template Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('event.name')
                    ->label('Event')
                    ->placeholder('Global Template')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->placeholder('Any Category')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('badgeType.name')
                    ->label('Badge Type')
                    ->placeholder('Any Badge Type')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('width')
                    ->label('Width')
                    ->suffix('px')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('height')
                    ->label('Height')
                    ->suffix('px')
                    ->sortable()
                    ->toggleable(),

                ColorColumn::make('background_color')
                    ->label('Background')
                    ->toggleable(),

                ColorColumn::make('header_color')
                    ->label('Header')
                    ->toggleable(),

                ColorColumn::make('accent_color')
                    ->label('Accent')
                    ->toggleable(),

                ColorColumn::make('text_color')
                    ->label('Text')
                    ->toggleable(),

                ColorColumn::make('footer_color')
                    ->label('Footer')
                    ->toggleable(),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('elements_count')
                    ->label('Elements')
                    ->counts('elements')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->relationship('event', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('badgeType')
                    ->label('Badge Type')
                    ->relationship('badgeType', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('is_default')
                    ->label('Default Template')
                    ->options([
                        '1' => 'Default',
                        '0' => 'Not Default',
                    ]),

                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                Action::make('design')
                    ->label('Design')
                    ->icon('heroicon-o-paint-brush')
                    ->color('warning')
                    ->url(fn ($record): string => BadgeTemplateResource::getUrl('design', [
                        'record' => $record,
                    ])),

                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->url(fn ($record): string => BadgeTemplateResource::getUrl('preview', [
                        'record' => $record,
                    ])),

                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Duplicate Badge Template')
                    ->modalDescription('This will copy the template and all its badge elements.')
                    ->action(function (BadgeTemplate $record): void {
                        $record->load('elements');

                        $newTemplate = $record->replicate([
                            'elements_count',
                        ]);

                        unset($newTemplate->elements_count);

                        $newTemplate->name = $record->name . ' Copy';
                        $newTemplate->is_default = false;
                        $newTemplate->is_active = true;
                        $newTemplate->save();

                        foreach ($record->elements as $element) {
                            $newElement = $element->replicate();
                            $newElement->badge_template_id = $newTemplate->id;
                            $newElement->save();
                        }

                        Notification::make()
                            ->title('Template duplicated')
                            ->body('A new template named "' . $newTemplate->name . '" has been created.')
                            ->success()
                            ->send();
                    }),

                Action::make('edit_elements')
                    ->label('Elements')
                    ->icon('heroicon-o-squares-2x2')
                    ->color('info')
                    ->url(fn (): string => BadgeTemplateElementResource::getUrl('index')),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}