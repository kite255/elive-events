<?php

namespace App\Filament\Resources\Tickets\Tables;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\Tickets\ManualTicketResendService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Js;
use RuntimeException;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ticket_number')
                    ->label('Ticket Number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->limit(35),

                TextColumn::make('order.buyer_name')
                    ->label('Buyer / Holder')
                    ->formatStateUsing(
                        fn ($state, Ticket $record): string =>
                            (string) ($state ?: $record->holder_name ?: '—')
                    )
                    ->searchable(),

                TextColumn::make('holder_name')
                    ->label('Holder Name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('order.buyer_phone')
                    ->label('Buyer Phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('order.buyer_email')
                    ->label('Buyer Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('holder_phone')
                    ->label('Holder Phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('holder_email')
                    ->label('Holder Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ticketType.name')
                    ->label('Ticket Type')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                TextColumn::make('price')
                    ->label('Price')
                    ->formatStateUsing(
                        fn ($state, Ticket $record): string =>
                            strtoupper((string) ($record->currency ?: 'TZS'))
                            . ' '
                            . number_format((float) $state, 2)
                    )
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            str((string) $state)->replace('_', ' ')->headline()->toString()
                    )
                    ->color(fn (?string $state): string => match ($state) {
                        Ticket::STATUS_ISSUED => 'success',
                        Ticket::STATUS_USED => 'info',
                        Ticket::STATUS_CANCELLED, Ticket::STATUS_REFUNDED => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('check_in_status')
                    ->label('Check-in')
                    ->state(fn (Ticket $record): string => $record->used_at ? 'Checked In' : 'Not Checked In')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Checked In' ? 'success' : 'gray'),

                TextColumn::make('used_at')
                    ->label('Checked In At')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('issued_at')
                    ->label('Issued At')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->sortable(),
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

                SelectFilter::make('ticket_type_id')
                    ->label('Ticket Type')
                    ->options(
                        TicketType::query()
                            ->whereHas('event', fn ($query) => $query->accessibleBy(auth()->user()))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->options([
                        Ticket::STATUS_ISSUED => 'Issued',
                        Ticket::STATUS_USED => 'Used',
                        Ticket::STATUS_CANCELLED => 'Cancelled',
                        Ticket::STATUS_REFUNDED => 'Refunded',
                    ]),

                SelectFilter::make('check_in_state')
                    ->label('Check-in')
                    ->options([
                        'checked_in' => 'Checked In',
                        'not_checked_in' => 'Not Checked In',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'checked_in' => $query->whereNotNull('used_at'),
                            'not_checked_in' => $query->whereNull('used_at'),
                            default => $query,
                        };
                    }),

                Filter::make('issued_at')
                    ->label('Issue Date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('issued_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('issued_at', '<=', $date));
                    }),
            ])
            ->defaultSort('issued_at', 'desc')
            ->recordActions([
                Action::make('view_ticket')
                    ->label('View Ticket')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Ticket $record): string => route('public.tickets.show', ['token' => $record->public_token]))
                    ->openUrlInNewTab()
                    ->visible(fn (Ticket $record): bool => filled($record->public_token)),

                Action::make('view_order')
                    ->label('View Order')
                    ->icon('heroicon-o-shopping-cart')
                    ->url(fn (Ticket $record): string => route('public.ticket-orders.show', ['token' => $record->order?->public_token]))
                    ->openUrlInNewTab()
                    ->visible(fn (Ticket $record): bool => filled($record->order?->public_token)),

                Action::make('copy_ticket_link')
                    ->label('Copy Secure Link')
                    ->icon('heroicon-o-clipboard-document')
                    ->visible(fn (Ticket $record): bool => filled($record->public_token))
                    ->extraAttributes(fn (Ticket $record): array => [
                        'x-on:click' => 'navigator.clipboard.writeText(' . Js::from(route('public.tickets.show', ['token' => $record->public_token])) . ')',
                    ])
                    ->action(function (Ticket $record): void {
                        Notification::make()
                            ->title('Secure ticket link copied')
                            ->body(route('public.tickets.show', ['token' => $record->public_token]))
                            ->success()
                            ->send();
                    }),

                Action::make('resend_ticket')
                    ->label('Resend Ticket')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (Ticket $record): bool => $record->order?->isPaid() ?? false)
                    ->modalHeading('Resend ticket access')
                    ->modalDescription(function (Ticket $record): string {
                        $contact = app(ManualTicketResendService::class)->maskedContact($record->order);

                        return 'Recipient: ' . ($record->order?->buyer_name ?: $record->holder_name ?: 'Customer')
                            . ' · ' . $contact['phone']
                            . ' · ' . $contact['email'];
                    })
                    ->schema([
                        Select::make('channels')
                            ->label('Delivery channels')
                            ->multiple()
                            ->options(fn (Ticket $record): array => app(ManualTicketResendService::class)->availableChannels($record->order))
                            ->required(),
                    ])
                    ->action(function (Ticket $record, array $data): void {
                        $user = auth()->user();

                        if (! $user || ! $record->order) {
                            throw new RuntimeException('Ticket order is unavailable.');
                        }

                        try {
                            $result = app(ManualTicketResendService::class)->queue(
                                $record->order,
                                $data['channels'] ?? [],
                                $user
                            );

                            Notification::make()
                                ->title('Ticket access queued')
                                ->body('Queued via: ' . implode(', ', $result['queued']))
                                ->success()
                                ->send();
                        } catch (RuntimeException $exception) {
                            Notification::make()
                                ->title('Ticket could not be resent')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}
