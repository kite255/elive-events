<?php

namespace App\Filament\Resources\AttendeeMerchandises\Tables;

use App\Filament\Resources\Attendees\AttendeeResource;
use App\Models\AttendeeMerchandise;
use App\Models\Event;
use App\Models\EventMerchandise;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema as DbSchema;

class AttendeeMerchandisesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attendee.full_name')
                    ->label('Attendee')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('attendee.phone')
                    ->label('Phone')
                    ->icon('heroicon-o-phone')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Phone number copied')
                    ->copyMessageDuration(1500)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('attendee.event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('merchandise.name')
                    ->label('Merchandise')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('variant.name')
                    ->label('Variant')
                    ->searchable()
                    ->placeholder('No variant')
                    ->wrap(),

                TextColumn::make('variant.size')
                    ->label('Size')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('variant.color_name')
                    ->label('Color')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->formatStateUsing(
                        fn ($state, AttendeeMerchandise $record): string =>
                            ($record->currency ?: 'TZS') . ' ' .
                            number_format((float) $state, 2)
                    )
                    ->sortable(),

                TextColumn::make('total_price')
                    ->label('Total')
                    ->formatStateUsing(
                        fn ($state, AttendeeMerchandise $record): string =>
                            ($record->currency ?: 'TZS') . ' ' .
                            number_format((float) $state, 2)
                    )
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Order Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'selected' => 'gray',
                        'reserved' => 'warning',
                        'paid' => 'success',
                        'distributed' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            ucfirst(str_replace('_', ' ', $state ?: 'unknown'))
                    )
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'paid', 'not_required' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'refunded' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            ucfirst(str_replace('_', ' ', $state ?: 'unknown'))
                    )
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Ordered At')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(
                        fn (): array => Event::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()
                    )
                    ->searchable()
                    ->query(
                        fn (Builder $query, array $data): Builder =>
                            $query->when(
                                filled($data['value'] ?? null),
                                fn (Builder $query): Builder =>
                                    $query->whereHas(
                                        'attendee',
                                        fn (Builder $attendeeQuery): Builder =>
                                            $attendeeQuery->where(
                                                'event_id',
                                                $data['value']
                                            )
                                    )
                            )
                    ),

                SelectFilter::make('event_merchandise_id')
                    ->label('Merchandise')
                    ->options(
                        fn (): array => EventMerchandise::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()
                    )
                    ->searchable(),

                SelectFilter::make('status')
                    ->label('Order Status')
                    ->options([
                        'selected' => 'Selected',
                        'reserved' => 'Reserved',
                        'paid' => 'Paid',
                        'distributed' => 'Distributed',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'not_required' => 'Not Required',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('view_attendee')
                        ->label('View Attendee')
                        ->icon('heroicon-o-user-circle')
                        ->color('info')
                        ->url(
                            fn (AttendeeMerchandise $record): string =>
                                AttendeeResource::getUrl('view', [
                                    'record' => $record->attendee_id,
                                ])
                        ),

                    Action::make('mark_paid')
                        ->label('Mark Payment Paid')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(
                            fn (AttendeeMerchandise $record): bool =>
                                $record->payment_status === 'pending'
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Mark this order as paid?')
                        ->action(function (
                            AttendeeMerchandise $record
                        ): void {
                            $data = [
                                'payment_status' => 'paid',
                            ];

                            if (DbSchema::hasColumn(
                                'attendee_merchandises',
                                'paid_at'
                            )) {
                                $data['paid_at'] = now();
                            }

                            if ($record->status === 'reserved') {
                                $data['status'] = 'paid';
                            }

                            $record->update($data);

                            Notification::make()
                                ->title('Payment marked as paid')
                                ->success()
                                ->send();
                        }),

                    Action::make('mark_distributed')
                        ->label('Mark as Distributed')
                        ->icon('heroicon-o-check-badge')
                        ->color('info')
                        ->visible(
                            fn (AttendeeMerchandise $record): bool =>
                                $record->status !== 'distributed'
                                && $record->status !== 'cancelled'
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Mark merchandise as distributed?')
                        ->action(function (
                            AttendeeMerchandise $record
                        ): void {
                            $data = [
                                'status' => 'distributed',
                            ];

                            if (DbSchema::hasColumn(
                                'attendee_merchandises',
                                'distributed_at'
                            )) {
                                $data['distributed_at'] = now();
                            }

                            if (DbSchema::hasColumn(
                                'attendee_merchandises',
                                'distributed_by'
                            )) {
                                $data['distributed_by'] = auth()->id();
                            }

                            $record->update($data);

                            Notification::make()
                                ->title('Merchandise marked as distributed')
                                ->success()
                                ->send();
                        }),

                    Action::make('cancel_order')
                        ->label('Cancel Reservation')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(
                            fn (AttendeeMerchandise $record): bool =>
                                $record->status !== 'distributed'
                                && $record->status !== 'cancelled'
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Cancel this merchandise order?')
                        ->modalDescription(
                            'The reservation will be cancelled.'
                        )
                        ->action(function (
                            AttendeeMerchandise $record
                        ): void {
                            $record->update([
                                'status' => 'cancelled',
                            ]);

                            Notification::make()
                                ->title('Merchandise order cancelled')
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button()
                    ->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('mark_selected_paid')
                        ->label('Mark Payments Paid')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $updated = 0;

                            foreach ($records as $record) {
                                if ($record->payment_status !== 'pending') {
                                    continue;
                                }

                                $data = [
                                    'payment_status' => 'paid',
                                ];

                                if (DbSchema::hasColumn(
                                    'attendee_merchandises',
                                    'paid_at'
                                )) {
                                    $data['paid_at'] = now();
                                }

                                if ($record->status === 'reserved') {
                                    $data['status'] = 'paid';
                                }

                                $record->update($data);
                                $updated++;
                            }

                            Notification::make()
                                ->title('Payments updated')
                                ->body($updated . ' order(s) marked as paid.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('mark_selected_distributed')
                        ->label('Mark as Distributed')
                        ->icon('heroicon-o-check-badge')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $updated = 0;

                            foreach ($records as $record) {
                                if (in_array(
                                    $record->status,
                                    ['distributed', 'cancelled'],
                                    true
                                )) {
                                    continue;
                                }

                                $data = [
                                    'status' => 'distributed',
                                ];

                                if (DbSchema::hasColumn(
                                    'attendee_merchandises',
                                    'distributed_at'
                                )) {
                                    $data['distributed_at'] = now();
                                }

                                if (DbSchema::hasColumn(
                                    'attendee_merchandises',
                                    'distributed_by'
                                )) {
                                    $data['distributed_by'] = auth()->id();
                                }

                                $record->update($data);
                                $updated++;
                            }

                            Notification::make()
                                ->title('Distribution updated')
                                ->body(
                                    $updated .
                                    ' order(s) marked as distributed.'
                                )
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with([
                    'attendee.event',
                    'merchandise',
                    'variant',
                ])
            )
            ->defaultSort('created_at', 'desc');
    }
}
