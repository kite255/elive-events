<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Filament\Resources\Attendees\AttendeeResource;
use App\Models\AttendeeMerchandise;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema as DbSchema;

class MerchandiseOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'attendeeMerchandiseOrders';

    protected static ?string $title = 'Attendee Merchandise Orders';

    protected static ?string $modelLabel = 'Merchandise Order';

    protected static ?string $pluralModelLabel = 'Merchandise Orders';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->description(
                'View and manage merchandise orders submitted by attendees for this event.'
            )
            ->emptyStateHeading('No merchandise orders')
            ->emptyStateDescription(
                'Orders submitted through this event registration form will appear here.'
            )
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->columns([
                TextColumn::make('attendee.full_name')
                    ->label('Attendee')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('attendee.phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('attendee.email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('attendee.category.name')
                    ->label('Category')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('attendee.status')
                    ->label('Attendee Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pending_approval', 'waitlisted' => 'warning',
                        'registered' => 'gray',
                        'confirmed' => 'success',
                        'checked_in' => 'info',
                        'cancelled', 'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            ucfirst(str_replace('_', ' ', $state ?: 'unknown'))
                    )
                    ->toggleable(),

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
                            self::formatMoney(
                                $state,
                                $record->currency
                            )
                    )
                    ->sortable(),

                TextColumn::make('total_price')
                    ->label('Total')
                    ->formatStateUsing(
                        fn ($state, AttendeeMerchandise $record): string =>
                            self::formatMoney(
                                $state,
                                $record->currency
                            )
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

                TextColumn::make('payment_method')
                    ->label('Payment Method')
                    ->badge()
                    ->placeholder('Not recorded')
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            self::paymentMethodLabel($state)
                    )
                    ->color(fn (?string $state): string => match ($state) {
                        'cash' => 'success',
                        'mobile_money' => 'warning',
                        'bank_transfer' => 'info',
                        'card' => 'primary',
                        'complimentary' => 'gray',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('payment_reference')
                    ->label('Payment Reference')
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Ordered At')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->visible(
                        fn (): bool => DbSchema::hasColumn(
                            'attendee_merchandises',
                            'paid_at'
                        )
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('distributed_at')
                    ->label('Distributed At')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->visible(
                        fn (): bool => DbSchema::hasColumn(
                            'attendee_merchandises',
                            'distributed_at'
                        )
                    )
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event_merchandise_id')
                    ->label('Merchandise')
                    ->relationship('merchandise', 'name')
                    ->searchable()
                    ->preload(),

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

                SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options(self::paymentMethodOptions()),

                SelectFilter::make('attendee_status')
                    ->label('Attendee Status')
                    ->options([
                        'pending_approval' => 'Pending Approval',
                        'waitlisted' => 'Waitlisted',
                        'registered' => 'Registered',
                        'confirmed' => 'Confirmed',
                        'checked_in' => 'Checked In',
                        'cancelled' => 'Cancelled',
                        'rejected' => 'Rejected',
                    ])
                    ->query(
                        fn (Builder $query, array $data): Builder =>
                            $query->when(
                                filled($data['value'] ?? null),
                                fn (Builder $query): Builder =>
                                    $query->whereHas(
                                        'attendee',
                                        fn (Builder $attendeeQuery): Builder =>
                                            $attendeeQuery->where(
                                                'status',
                                                $data['value']
                                            )
                                    )
                            )
                    ),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('view_attendee')
                        ->label('View Attendee Profile')
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
                                && $record->status !== 'cancelled'
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Mark this order as paid?')
                        ->modalDescription(
                            'The payment status will be changed to paid.'
                        )
                        ->modalSubmitActionLabel('Mark Paid')
                        ->schema([
                            Select::make('payment_method')
                                ->label('Mode of Payment')
                                ->options(self::paymentMethodOptions())
                                ->required()
                                ->native(false),

                            TextInput::make('payment_reference')
                                ->label('Payment Reference')
                                ->placeholder(
                                    'Transaction ID, receipt number or bank reference'
                                )
                                ->maxLength(255),
                        ])
                        ->action(
                            fn (
                                AttendeeMerchandise $record,
                                array $data
                            ): bool => self::markPaid($record, $data)
                        ),

                    Action::make('mark_distributed')
                        ->label('Mark as Distributed')
                        ->icon('heroicon-o-check-badge')
                        ->color('info')
                        ->visible(
                            fn (AttendeeMerchandise $record): bool =>
                                ! in_array(
                                    $record->status,
                                    ['distributed', 'cancelled'],
                                    true
                                )
                        )
                        ->disabled(
                            fn (AttendeeMerchandise $record): bool =>
                                ! self::canDistribute($record)
                        )
                        ->tooltip(
                            fn (AttendeeMerchandise $record): ?string =>
                                self::canDistribute($record)
                                    ? null
                                    : 'Payment must be paid or not required before distribution.'
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Mark merchandise as distributed?')
                        ->modalDescription(
                            'This confirms that the attendee has received the merchandise.'
                        )
                        ->modalSubmitActionLabel('Mark Distributed')
                        ->action(
                            fn (AttendeeMerchandise $record): bool =>
                                self::markDistributed($record)
                        ),

                    Action::make('cancel_order')
                        ->label('Cancel Reservation')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(
                            fn (AttendeeMerchandise $record): bool =>
                                in_array(
                                    $record->status,
                                    ['selected', 'reserved'],
                                    true
                                )
                                && $record->payment_status !== 'paid'
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Cancel this merchandise order?')
                        ->modalDescription(
                            'The reservation will be cancelled and excluded from reserved stock totals.'
                        )
                        ->modalSubmitActionLabel('Cancel Order')
                        ->action(
                            fn (AttendeeMerchandise $record): bool =>
                                self::cancelOrder($record)
                        ),
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
                        ->modalHeading('Mark selected payments as paid?')
                        ->schema([
                            Select::make('payment_method')
                                ->label('Mode of Payment')
                                ->options(self::paymentMethodOptions())
                                ->required()
                                ->native(false),

                            TextInput::make('payment_reference')
                                ->label('Shared Payment Reference')
                                ->placeholder(
                                    'Optional reference applied to all selected orders'
                                )
                                ->maxLength(255),
                        ])
                        ->action(function (
                            Collection $records,
                            array $data
                        ): void {
                            $updated = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                if (
                                    $record->payment_status !== 'pending'
                                    || $record->status === 'cancelled'
                                ) {
                                    $skipped++;

                                    continue;
                                }

                                self::updatePaidState($record, $data);
                                $updated++;
                            }

                            self::sendResultNotification(
                                'Payments updated',
                                "Updated: {$updated}. Skipped: {$skipped}.",
                                $updated
                            );
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('mark_selected_distributed')
                        ->label('Mark as Distributed')
                        ->icon('heroicon-o-check-badge')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading(
                            'Mark selected merchandise as distributed?'
                        )
                        ->action(function (Collection $records): void {
                            $updated = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                if (
                                    in_array(
                                        $record->status,
                                        ['distributed', 'cancelled'],
                                        true
                                    )
                                    || ! self::canDistribute($record)
                                ) {
                                    $skipped++;

                                    continue;
                                }

                                self::updateDistributedState($record);
                                $updated++;
                            }

                            self::sendResultNotification(
                                'Distribution updated',
                                "Updated: {$updated}. Skipped: {$skipped}.",
                                $updated
                            );
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('cancel_selected_orders')
                        ->label('Cancel Reservations')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Cancel selected reservations?')
                        ->action(function (Collection $records): void {
                            $updated = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                if (
                                    ! in_array(
                                        $record->status,
                                        ['selected', 'reserved'],
                                        true
                                    )
                                    || $record->payment_status === 'paid'
                                ) {
                                    $skipped++;

                                    continue;
                                }

                                $record->update([
                                    'status' => 'cancelled',
                                ]);

                                $updated++;
                            }

                            self::sendResultNotification(
                                'Reservations cancelled',
                                "Cancelled: {$updated}. Skipped: {$skipped}.",
                                $updated
                            );
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with([
                    'attendee.category',
                    'merchandise',
                    'variant',
                ])
            )
            ->defaultSort('created_at', 'desc');
    }

    protected static function formatMoney(
        mixed $amount,
        ?string $currency
    ): string {
        return ($currency ?: 'TZS') . ' ' .
            number_format((float) $amount, 2);
    }

    protected static function canDistribute(
        AttendeeMerchandise $record
    ): bool {
        return in_array(
            $record->payment_status,
            ['paid', 'not_required'],
            true
        );
    }

    protected static function markPaid(
        AttendeeMerchandise $record,
        array $data
    ): bool {
        self::updatePaidState($record, $data);

        Notification::make()
            ->title('Payment marked as paid')
            ->success()
            ->send();

        return true;
    }

    protected static function updatePaidState(
        AttendeeMerchandise $record,
        array $paymentData
    ): void {
        $data = [
            'payment_status' => 'paid',
            'payment_method' => $paymentData['payment_method'],
            'payment_reference' =>
                $paymentData['payment_reference'] ?? null,
        ];

        if ($record->status === 'reserved') {
            $data['status'] = 'paid';
        }

        if (
            DbSchema::hasColumn(
                'attendee_merchandises',
                'paid_at'
            )
        ) {
            $data['paid_at'] = now();
        }

        $record->update($data);
    }

    protected static function markDistributed(
        AttendeeMerchandise $record
    ): bool {
        if (! self::canDistribute($record)) {
            Notification::make()
                ->title('Cannot distribute merchandise')
                ->body(
                    'Payment must be paid or marked as not required first.'
                )
                ->warning()
                ->send();

            return false;
        }

        self::updateDistributedState($record);

        Notification::make()
            ->title('Merchandise marked as distributed')
            ->success()
            ->send();

        return true;
    }

    protected static function updateDistributedState(
        AttendeeMerchandise $record
    ): void {
        $data = [
            'status' => 'distributed',
        ];

        if (
            DbSchema::hasColumn(
                'attendee_merchandises',
                'distributed_at'
            )
        ) {
            $data['distributed_at'] = now();
        }

        if (
            DbSchema::hasColumn(
                'attendee_merchandises',
                'distributed_by'
            )
        ) {
            $data['distributed_by'] = auth()->id();
        }

        $record->update($data);
    }

    protected static function cancelOrder(
        AttendeeMerchandise $record
    ): bool {
        if ($record->payment_status === 'paid') {
            Notification::make()
                ->title('Paid order cannot be cancelled here')
                ->body(
                    'Handle the refund before cancelling a paid order.'
                )
                ->warning()
                ->send();

            return false;
        }

        $record->update([
            'status' => 'cancelled',
        ]);

        Notification::make()
            ->title('Merchandise order cancelled')
            ->success()
            ->send();

        return true;
    }

    protected static function paymentMethodOptions(): array
    {
        return [
            'cash' => 'Cash',
            'mobile_money' => 'Mobile Money',
            'bank_transfer' => 'Bank Transfer',
            'card' => 'Card / POS',
            'complimentary' => 'Complimentary / Free',
        ];
    }

    protected static function paymentMethodLabel(
        ?string $method
    ): string {
        if (blank($method)) {
            return 'Not recorded';
        }

        return self::paymentMethodOptions()[$method]
            ?? ucfirst(str_replace('_', ' ', $method));
    }

    protected static function sendResultNotification(
        string $title,
        string $body,
        int $updated
    ): void {
        $notification = Notification::make()
            ->title($title)
            ->body($body);

        if ($updated > 0) {
            $notification->success();
        } else {
            $notification->warning();
        }

        $notification->send();
    }
}
