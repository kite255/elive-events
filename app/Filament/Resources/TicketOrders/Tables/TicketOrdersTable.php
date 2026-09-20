<?php

namespace App\Filament\Resources\TicketOrders\Tables;

use App\Models\Event;
use App\Models\TicketOrder;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TicketOrdersTable
{
    public static function configure(
        Table $table
    ): Table {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->limit(35),

                TextColumn::make('buyer_name')
                    ->label('Buyer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('buyer_phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('buyer_email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('quantity')
                    ->label('Tickets')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('tickets_count')
                    ->label('Issued')
                    ->counts('tickets')
                    ->sortable(),

                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(
                        fn (
                            $state,
                            TicketOrder $record
                        ): string =>
                            strtoupper(
                                $record->currency
                            )
                            . ' '
                            . number_format(
                                (float) $state,
                                2
                            )
                    )
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (string $state): string =>
                            ucwords(
                                str_replace(
                                    '_',
                                    ' ',
                                    $state
                                )
                            )
                    )
                    ->color(
                        fn (string $state): string =>
                            match ($state) {
                                TicketOrder::STATUS_PAID =>
                                    'success',

                                TicketOrder::STATUS_PENDING =>
                                    'warning',

                                TicketOrder::STATUS_PROCESSING =>
                                    'info',

                                TicketOrder::STATUS_CANCELLED,
                                TicketOrder::STATUS_EXPIRED =>
                                    'danger',

                                TicketOrder::STATUS_REFUNDED,
                                TicketOrder::STATUS_PARTIALLY_REFUNDED =>
                                    'gray',

                                default =>
                                    'gray',
                            }
                    )
                    ->sortable(),

                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime(
                        'd M Y, H:i'
                    )
                    ->placeholder('Not paid')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Expires At')
                    ->dateTime(
                        'd M Y, H:i'
                    )
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(
                        'd M Y, H:i'
                    )
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(
                        Event::query()
                            ->orderBy('name')
                            ->pluck(
                                'name',
                                'id'
                            )
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        TicketOrder::STATUS_PENDING =>
                            'Pending',

                        TicketOrder::STATUS_PROCESSING =>
                            'Processing',

                        TicketOrder::STATUS_PAID =>
                            'Paid',

                        TicketOrder::STATUS_CANCELLED =>
                            'Cancelled',

                        TicketOrder::STATUS_EXPIRED =>
                            'Expired',

                        TicketOrder::STATUS_REFUNDED =>
                            'Refunded',

                        TicketOrder::STATUS_PARTIALLY_REFUNDED =>
                            'Partially Refunded',
                    ]),
            ])
            ->defaultSort(
                'created_at',
                'desc'
            )
            ->recordActions([
                EditAction::make()
                    ->visible(
                        fn (): bool =>
                            ! auth()->user()?->isTicketOrganizer()
                    ),
            ]);
    }
}