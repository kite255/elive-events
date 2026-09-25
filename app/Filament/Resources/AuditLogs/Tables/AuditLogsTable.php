<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('d M Y, H:i:s')
                    ->sortable(),
                TextColumn::make('actor.name')
                    ->label('User')
                    ->placeholder('System')
                    ->searchable(),
                TextColumn::make('action')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('event.name')
                    ->label('Event')
                    ->placeholder('Global')
                    ->searchable(),
                TextColumn::make('subject_type')
                    ->label('Resource')
                    ->formatStateUsing(fn (?string $state): string => class_basename((string) $state)),
                TextColumn::make('reference')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('metadata')
                    ->label('Details')
                    ->formatStateUsing(fn ($state): string => $state ? json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—')
                    ->wrap()
                    ->limit(120),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options(fn (): array => \App\Models\AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all())
                    ->searchable(),
                SelectFilter::make('event_id')
                    ->label('Event')
                    ->relationship('event', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('actor_id')
                    ->label('Actor')
                    ->relationship('actor', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
