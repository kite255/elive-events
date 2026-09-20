<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Payment reference copied')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('attendee.full_name')
                    ->label('Attendee')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No attendee')
                    ->wrap(),

                TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->wrap(),

                TextColumn::make('organization.name')
                    ->label('Organization')
                    ->searchable()
                    ->sortable()
                    ->limit(26)
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(
                        fn ($state, Payment $record): string =>
                            $record->currency
                            . ' '
                            . number_format((float) $state, 2)
                    )
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('status')
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
                    )
                    ->sortable(),

                TextColumn::make('gateway.name')
                    ->label('Gateway')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('payment_method')
                    ->label('Payment Method')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('provider_tracking_id')
                    ->label('Provider Tracking ID')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('paid_at')
                    ->label('Paid At')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),
            ])
            ->filters([
                SelectFilter::make('event_id')
                    ->label('Event')
                    ->options(
                        fn (): array =>
                            static::eventOptions()
                    )
                    ->searchable()
                    ->native(false),

                SelectFilter::make('organization_id')
                    ->label('Organization')
                    ->options(
                        fn (): array =>
                            static::organizationOptions()
                    )
                    ->searchable()
                    ->native(false),

                SelectFilter::make('status')
                    ->options([
                        Payment::STATUS_PENDING => 'Pending',
                        Payment::STATUS_PROCESSING => 'Processing',
                        Payment::STATUS_COMPLETED => 'Completed',
                        Payment::STATUS_FAILED => 'Failed',
                        Payment::STATUS_CANCELLED => 'Cancelled',
                        Payment::STATUS_EXPIRED => 'Expired',
                        Payment::STATUS_REFUNDED => 'Refunded',
                        Payment::STATUS_PARTIALLY_REFUNDED =>
                            'Partially Refunded',
                    ])
                    ->native(false),

                SelectFilter::make('payment_gateway_id')
                    ->label('Gateway')
                    ->options(
                        fn (): array =>
                            static::gatewayOptions()
                    )
                    ->searchable()
                    ->native(false),

                SelectFilter::make('payment_method')
                    ->label('Payment Method')
                    ->options(
                        fn (): array =>
                            Payment::query()
                                ->whereNotNull('payment_method')
                                ->where('payment_method', '!=', '')
                                ->distinct()
                                ->orderBy('payment_method')
                                ->pluck(
                                    'payment_method',
                                    'payment_method'
                                )
                                ->all()
                    )
                    ->native(false),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('View')
                    ->icon('heroicon-o-eye'),
            ]);
    }

    protected static function eventOptions(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return Event::query()
            ->when(
                ! $user->isSuperAdmin(),
                fn (Builder $query): Builder =>
                    $query->accessibleBy($user)
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function organizationOptions(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            return Organization::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        }

        return $user
            ->activeOrganizations()
            ->orderBy('organizations.name')
            ->pluck(
                'organizations.name',
                'organizations.id'
            )
            ->all();
    }

    protected static function gatewayOptions(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $organizationIds =
            $user->isSuperAdmin()
                ? null
                : $user
                    ->activeOrganizations()
                    ->pluck('organizations.id');

        return PaymentGateway::query()
            ->when(
                $organizationIds !== null,
                fn (Builder $query): Builder =>
                    $query->where(
                        function (
                            Builder $query
                        ) use (
                            $organizationIds
                        ): void {
                            $query
                                ->whereNull('organization_id')
                                ->orWhereIn(
                                    'organization_id',
                                    $organizationIds
                                );
                        }
                    )
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
