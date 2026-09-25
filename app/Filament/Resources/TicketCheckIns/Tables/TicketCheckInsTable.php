<?php

namespace App\Filament\Resources\TicketCheckIns\Tables;

use App\Models\CheckInPoint;
use App\Models\Event;
use App\Models\TicketCheckIn;
use App\Models\TicketType;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TicketCheckInsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('checked_in_at')
                    ->label('Time')
                    ->dateTime('d M Y, H:i:s')
                    ->sortable(),

                TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->limit(35),

                TextColumn::make('ticket.ticket_number')
                    ->label('Ticket Number')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('ticket.order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ticket.holder_name')
                    ->label('Holder / Buyer')
                    ->formatStateUsing(
                        fn ($state, TicketCheckIn $record): string =>
                            (string) ($state ?: $record->ticket?->order?->buyer_name ?: '—')
                    )
                    ->searchable(),

                TextColumn::make('ticket.order.buyer_name')
                    ->label('Buyer Search')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ticket.ticketType.name')
                    ->label('Ticket Type')
                    ->placeholder('—'),

                TextColumn::make('checkInPoint.name')
                    ->label('Check-in Point')
                    ->placeholder('General Entrance'),

                TextColumn::make('checkedInBy.name')
                    ->label('Officer')
                    ->placeholder('System'),

                TextColumn::make('method')
                    ->label('Method')
                    ->badge()
                    ->formatStateUsing(
                        fn ($state, TicketCheckIn $record): string => $record->methodLabel()
                    ),
            ])
            ->filters([
                SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(
                        Event::query()
                            ->accessibleBy(auth()->user())
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('ticket_type')
                    ->label('Ticket Type')
                    ->options(
                        TicketType::query()
                            ->whereHas('event', fn ($query) => $query->accessibleBy(auth()->user()))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        $ticketTypeId = $data['value'] ?? null;

                        return $query->when(
                            $ticketTypeId,
                            fn (Builder $q): Builder => $q->whereHas(
                                'ticket',
                                fn (Builder $ticketQuery): Builder => $ticketQuery->where('ticket_type_id', $ticketTypeId)
                            )
                        );
                    })
                    ->searchable()
                    ->preload(),

                SelectFilter::make('check_in_point_id')
                    ->label('Check-in Point')
                    ->options(
                        CheckInPoint::query()
                            ->whereHas('event', fn ($query) => $query->accessibleBy(auth()->user()))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('checked_in_by')
                    ->label('Officer')
                    ->options(
                        User::query()
                            ->whereHas('assignedEvents', fn ($query) => $query->accessibleBy(auth()->user()))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('method')
                    ->options([
                        'qr' => 'QR Code',
                        'manual' => 'Manual Lookup',
                        'ticket_number' => 'Ticket Number',
                    ]),

                Filter::make('checked_in_at')
                    ->label('Check-in Date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('checked_in_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('checked_in_at', '<=', $date));
                    }),
            ])
            ->defaultSort('checked_in_at', 'desc');
    }
}
