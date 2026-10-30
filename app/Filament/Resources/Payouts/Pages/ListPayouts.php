<?php

namespace App\Filament\Resources\Payouts\Pages;

use App\Filament\Resources\Payouts\PayoutResource;
use App\Models\Event;
use App\Models\User;
use App\Services\EventPayoutService;
use App\Services\EventFinanceCsvExportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Closure;
use Illuminate\Validation\ValidationException;

class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;

    public function getSubheading(): ?string
    {
        return 'Ticket-based payout requests, subject to manual financial verification before transfer.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadFinanceReport')
                ->label('Export Event Finance CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => PayoutResource::canViewAny())
                ->action(fn () => app(EventFinanceCsvExportService::class)->download(auth()->user())),
            Action::make('requestPayout')
                ->label('Request Payout')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => PayoutResource::canViewAny())
                ->disabled(fn (): bool => ! config('finance.payout_requests_enabled', false))
                ->tooltip(fn (): string => config('finance.payout_requests_enabled', false) ? 'Request funds verified by the platform.' : 'Requests are disabled pending independent settlement review.')
                ->schema([
                    Select::make('event_id')
                        ->label('Event')
                        ->options(fn (): array => $this->eligibleEvents())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (?string $state): void {
                            $eventId = (int) $state;
                            if (! $eventId || ! array_key_exists($eventId, $this->eligibleEvents())) {
                                return;
                            }

                            $balance = app(EventPayoutService::class)->ticketBalance($eventId);
                            if ($balance['refund_exception_count'] > 0) {
                                Notification::make()->title('Payout temporarily unavailable')
                                    ->body('Refund exceptions must be reconciled before requesting funds.')
                                    ->warning()->duration(8000)->send();
                            } elseif ($balance['requestable_ticket_balance'] <= 0) {
                                Notification::make()->title('No verified funds available')
                                    ->body('This event has no requestable payout balance. Contact the administrator for settlement review.')
                                    ->warning()->duration(8000)->send();
                            } else {
                                Notification::make()->title('Verified payout balance available')
                                    ->body('You may request up to TZS '.number_format($balance['requestable_ticket_balance'], 2).'.')
                                    ->success()->duration(5000)->send();
                            }
                        })
                        ->required()
                        ->helperText('Choose the event whose cleared ticket proceeds will fund this request.'),
                    TextInput::make('amount')
                        ->label('Amount (TZS)')
                        ->numeric()
                        ->minValue(0.01)
                        ->live(debounce: 700)
                        ->afterStateUpdated(function (mixed $state, Get $get): void {
                            if (! is_numeric($state) || (float) $state <= 0) {
                                return;
                            }

                            $eventId = (int) ($get('event_id') ?: 0);
                            if (! $eventId || ! array_key_exists($eventId, $this->eligibleEvents())) {
                                return;
                            }

                            $balance = app(EventPayoutService::class)->ticketBalance($eventId);
                            if ((float) $state > (float) $balance['requestable_ticket_balance']) {
                                Notification::make()->title('Amount exceeds available funds')
                                    ->body('Verified available balance: TZS '.number_format($balance['requestable_ticket_balance'], 2).'. Reduce the amount to proceed.')
                                    ->warning()->duration(6000)->send();
                            }
                        })
                        ->required()
                        ->rules([
                            fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                $eventId = (int) ($get('event_id') ?: 0);

                                if (! $eventId || ! array_key_exists($eventId, $this->eligibleEvents())) {
                                    $fail('Select an authorized event before requesting a payout.');
                                    return;
                                }

                                if (! config('finance.payout_requests_enabled', false)) {
                                    $fail('Payout requests are currently disabled.');
                                    return;
                                }

                                $balance = app(EventPayoutService::class)->ticketBalance($eventId);
                                if ($balance['refund_exception_count'] > 0) {
                                    $fail('Refund exceptions must be reconciled before requesting funds.');
                                    return;
                                }

                                $available = (float) $balance['requestable_ticket_balance'];
                                if ($available <= 0) {
                                    $fail('No verified funds are currently available for payout. Contact the administrator.');
                                } elseif (is_numeric($value) && (float) $value > $available) {
                                    $fail('Amount exceeds the verified available balance of TZS '.number_format($available, 2).'.');
                                }
                            },
                        ])
                        ->helperText(function (Get $get): string {
                            $eventId = (int) ($get('event_id') ?: 0);

                            if (! $eventId || ! array_key_exists($eventId, $this->eligibleEvents())) {
                                return 'Select an authorized event to see its reconciled available balance.';
                            }

                            $balance = app(EventPayoutService::class)->ticketBalance($eventId);

                            if ($balance['refund_exception_count'] > 0) {
                                return 'Refund exceptions require reconciliation before requesting funds.';
                            }

                            if ($balance['requestable_ticket_balance'] <= 0) {
                                return 'Verified requestable balance: TZS 0.00. No verified funds are available for payout. Contact the administrator.';
                            }

                            $message = 'Verified requestable balance: TZS '.number_format($balance['requestable_ticket_balance'], 2).
                                '. Pending requests and recorded payouts are already deducted.';

                            $amount = $get('amount');
                            if (is_numeric($amount) && (float) $amount > $balance['requestable_ticket_balance']) {
                                return $message.' Your entered amount exceeds this balance.';
                            }

                            return $message;
                        }),
                    Select::make('destination_type')
                        ->label('Payment destination')
                        ->options(['bank' => 'Bank account', 'mobile_money' => 'Mobile money'])
                        ->required(),
                    Textarea::make('destination_details')
                        ->label('Account name, provider and account/phone number')
                        ->rows(3)
                        ->maxLength(500)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $user = auth()->user();

                    if (! $user instanceof User) {
                        abort(403);
                    }

                    $event = Event::query()->findOrFail((int) $data['event_id']);

                    try {
                        app(EventPayoutService::class)->request(
                        $event,
                        $user,
                        (float) $data['amount'],
                        $data['destination_type'],
                        $data['destination_details'],
                        );
                    } catch (ValidationException $exception) {
                        Notification::make()->title('Payout request not submitted')
                            ->body(collect($exception->errors())->flatten()->first() ?: 'Please verify the requested amount and event balance.')
                            ->danger()->duration(8000)->send();
                        throw $exception;
                    }

                    Notification::make()->title('Payout request submitted')->body('Your request has been sent for administrator review.')->success()->duration(8000)->send();
                }),
        ];
    }

    private function eligibleEvents(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return Event::query()
            ->when(! $user->isSuperAdmin(), function (Builder $query) use ($user): void {
                $query->where(function (Builder $scope) use ($user): void {
                    $scope->whereIn('organization_id', $user->managedOrganizations()->select('organizations.id'))
                        ->orWhereIn('events.id', $user->assignedTicketingEvents()->select('events.id'));
                });
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
