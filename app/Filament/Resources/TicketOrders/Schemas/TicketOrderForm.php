<?php

namespace App\Filament\Resources\TicketOrders\Schemas;

use App\Models\TicketOrder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TicketOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order Information')
                    ->schema([
                        TextInput::make('order_number')
                            ->label('Order Number')
                            ->placeholder('ELV-ORD-...')
                            ->required()
                            ->maxLength(50)
                            ->unique(
                                ignoreRecord: true
                            ),

                        Select::make('event_id')
                            ->label('Event')
                            ->relationship(
                                'event',
                                'name'
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('attendee_id')
                            ->label('Attendee')
                            ->relationship(
                                'attendee',
                                'full_name'
                            )
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make('Buyer Information')
                    ->schema([
                        TextInput::make('buyer_name')
                            ->label('Buyer Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('buyer_phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(30),

                        TextInput::make('buyer_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Order Amount')
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Total Tickets')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),

                        TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),

                        TextInput::make('discount_amount')
                            ->label('Discount')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),

                        TextInput::make('total')
                            ->label('Total')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),

                        Select::make('currency')
                            ->label('Currency')
                            ->options([
                                'TZS' => 'TZS',
                                'USD' => 'USD',
                            ])
                            ->default('TZS')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Status')
                    ->schema([
                        Select::make('status')
                            ->label('Order Status')
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
                            ])
                            ->default(
                                TicketOrder::STATUS_PENDING
                            )
                            ->required(),

                        DateTimePicker::make('paid_at')
                            ->label('Paid At')
                            ->seconds(false),

                        DateTimePicker::make('expires_at')
                            ->label('Expires At')
                            ->seconds(false),
                    ])
                    ->columns(2),
            ]);
    }
}