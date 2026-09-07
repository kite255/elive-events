<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewPayment extends ViewRecord
{
    protected static string $resource =
        PaymentResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->getRecord()->loadMissing([
            'organization',
            'event',
            'attendee',
            'gateway',
            'transactions',
        ]);
    }

    public function getTitle(): string
    {
        return 'Payment ' . $this->getRecord()->reference;
    }

    public function getSubheading(): ?string
    {
        /** @var Payment $payment */
        $payment = $this->getRecord();

        return collect([
            $payment->event?->name,
            $payment->attendee?->full_name,
        ])->filter()->implode(' • ') ?: null;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transaction')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('reference')
                            ->label('eLive Reference')
                            ->copyable(),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(
                                fn (?string $state): string =>
                                    match ($state) {
                                        Payment::STATUS_COMPLETED =>
                                            'success',

                                        Payment::STATUS_PENDING,
                                        Payment::STATUS_PROCESSING =>
                                            'warning',

                                        Payment::STATUS_FAILED,
                                        Payment::STATUS_CANCELLED,
                                        Payment::STATUS_EXPIRED =>
                                            'danger',

                                        Payment::STATUS_REFUNDED,
                                        Payment::STATUS_PARTIALLY_REFUNDED =>
                                            'info',

                                        default =>
                                            'gray',
                                    }
                            )
                            ->formatStateUsing(
                                fn (?string $state): string =>
                                    str((string) $state)
                                        ->replace('_', ' ')
                                        ->headline()
                                        ->toString()
                            ),

                        TextEntry::make('amount')
                            ->label('Amount')
                            ->formatStateUsing(
                                fn ($state, Payment $record): string =>
                                    $record->currency
                                    . ' '
                                    . number_format(
                                        (float) $state,
                                        2
                                    )
                            ),

                        TextEntry::make('event.name')
                            ->label('Event')
                            ->placeholder('—'),

                        TextEntry::make('organization.name')
                            ->label('Organization')
                            ->placeholder('—'),

                        TextEntry::make('attendee.full_name')
                            ->label('Attendee')
                            ->placeholder('No attendee linked'),

                        TextEntry::make('attendee.phone')
                            ->label('Phone')
                            ->copyable()
                            ->placeholder('—'),

                        TextEntry::make('attendee.email')
                            ->label('Email')
                            ->copyable()
                            ->placeholder('—'),

                        TextEntry::make('payment_method')
                            ->label('Payment Method')
                            ->placeholder('—'),

                        TextEntry::make('gateway.name')
                            ->label('Gateway')
                            ->placeholder('—'),

                        TextEntry::make('provider_reference')
                            ->label('Provider Reference')
                            ->copyable()
                            ->placeholder('—'),

                        TextEntry::make('provider_tracking_id')
                            ->label('Provider Tracking ID')
                            ->copyable()
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Timing')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('initiated_at')
                            ->label('Initiated At')
                            ->dateTime('d M Y, H:i:s')
                            ->placeholder('—'),

                        TextEntry::make('paid_at')
                            ->label('Paid At')
                            ->dateTime('d M Y, H:i:s')
                            ->placeholder('—'),

                        TextEntry::make('failed_at')
                            ->label('Failed At')
                            ->dateTime('d M Y, H:i:s')
                            ->placeholder('—'),

                        TextEntry::make('cancelled_at')
                            ->label('Cancelled At')
                            ->dateTime('d M Y, H:i:s')
                            ->placeholder('—'),

                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime('d M Y, H:i:s')
                            ->placeholder('—'),

                        TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime('d M Y, H:i:s')
                            ->placeholder('—'),
                    ]),

                Section::make('Gateway Activity')
                    ->description(
                        'Gateway requests, callbacks, IPN messages, and verification activity will be recorded against this payment.'
                    )
                    ->schema([
                        TextEntry::make('transactions_count')
                            ->label('Recorded Gateway Transactions')
                            ->state(
                                fn (Payment $record): int =>
                                    $record->transactions->count()
                            )
                            ->badge(),

                        TextEntry::make('metadata')
                            ->label('Metadata')
                            ->state(
                                function (
                                    Payment $record
                                ): string {
                                    if (empty($record->metadata)) {
                                        return '—';
                                    }

                                    return json_encode(
                                        $record->metadata,
                                        JSON_PRETTY_PRINT
                                        | JSON_UNESCAPED_SLASHES
                                    ) ?: '—';
                                }
                            )
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
