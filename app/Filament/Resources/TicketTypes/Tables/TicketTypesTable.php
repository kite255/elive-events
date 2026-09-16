<?php

namespace App\Filament\Resources\TicketTypes\Tables;

use App\Models\Event;
use App\Models\Ticket;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Ticket Type')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->badge(),

                TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->limit(35),

                TextColumn::make('price')
                    ->label('Price')
                    ->formatStateUsing(
                        fn ($state, $record): string =>
                            strtoupper($record->currency)
                            . ' '
                            . number_format(
                                (float) $state,
                                2
                            )
                    )
                    ->sortable(),

                TextColumn::make('capacity')
                    ->label('Capacity')
                    ->formatStateUsing(
                        fn ($state): string =>
                            blank($state)
                                ? 'Unlimited'
                                : number_format(
                                    (int) $state
                                )
                    )
                    ->sortable(),

                TextColumn::make('tickets_count')
                    ->label('Sold')
                    ->counts([
                        'tickets' => fn (Builder $query): Builder =>
                            $query->whereNotIn(
                                'status',
                                [
                                    Ticket::STATUS_CANCELLED,
                                    Ticket::STATUS_REFUNDED,
                                ]
                            ),
                    ])
                    ->sortable(),

                TextColumn::make('remaining')
                    ->label('Remaining')
                    ->state(
                        function ($record): string {
                            $remaining =
                                $record->remainingCapacity();

                            return $remaining === null
                                ? 'Unlimited'
                                : number_format($remaining);
                        }
                    ),

                TextColumn::make('sales_start_at')
                    ->label('Sales Start')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Immediately')
                    ->sortable(),

                TextColumn::make('sales_end_at')
                    ->label('Sales End')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('No end date')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(
                        Event::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label('Active'),

                TernaryFilter::make('is_public')
                    ->label('Public'),

                Filter::make('on_sale')
                    ->label('On Sale Now')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query
                                ->where('is_active', true)
                                ->where('is_public', true)
                                ->where(
                                    function (
                                        Builder $query
                                    ): void {
                                        $query
                                            ->whereNull(
                                                'sales_start_at'
                                            )
                                            ->orWhere(
                                                'sales_start_at',
                                                '<=',
                                                now()
                                            );
                                    }
                                )
                                ->where(
                                    function (
                                        Builder $query
                                    ): void {
                                        $query
                                            ->whereNull(
                                                'sales_end_at'
                                            )
                                            ->orWhere(
                                                'sales_end_at',
                                                '>=',
                                                now()
                                            );
                                    }
                                )
                    ),

                Filter::make('sold_out')
                    ->label('Sold Out')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query
                                ->whereNotNull('capacity')
                                ->where('capacity', '>', 0)
                                ->whereRaw(
                                    '(
                                        SELECT COUNT(*)
                                        FROM tickets
                                        WHERE tickets.ticket_type_id = ticket_types.id
                                        AND tickets.status NOT IN (?, ?)
                                    ) >= ticket_types.capacity',
                                    [
                                        Ticket::STATUS_CANCELLED,
                                        Ticket::STATUS_REFUNDED,
                                    ]
                                )
                    ),
            ])
            ->defaultSort(
                'sort_order'
            )
            ->recordActions([
                EditAction::make()
                    ->visible(
                        fn (): bool =>
                            ! auth()->user()?->isTicketOrganizer()
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}