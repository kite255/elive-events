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
        return 'Request against unpaid ticket earnings. Approval requires independently verified settlement; transfers remain manual.';
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
                ->tooltip(fn (): string => config('finance.payout_requests_enabled', false) ? 'Submit a request against unpaid ticket earnings. Verification is required before approval.' : 'Requests are disabled pending independent settlement review.')
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
                            } elseif ($balance['requestable_pending_verification_balance'] <= 0) {
                                Notification::make()->title('No unpaid earnings available')
                                    ->body('This event has no unpaid earnings available for another request.')
                                    ->warning()->duration(8000)->send();
                            } else {
                                Notification::make()->title('Payout request balance available')
                                    ->body('You may request up to TZS '.number_format($balance['requestable_pending_verification_balance'], 2).'.')
                                    ->success()->duration(5000)->send();
                            }
                        })
                        ->required()
                        ->helperText('Choose the event whose unpaid ticket earnings will support this request. Settlement verification happens before approval.'),
                    TextInput::make('amount')
                        ->label('Amount (TZS)')
                        ->numeric()
                        ->minValue(0.01)
                        ->live(debounce: 700)
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

                                $available = (float) $balance['requestable_pending_verification_balance'];
                                if ($available <= 0) {
                                    $fail('No unpaid ticket earnings are available for a new request.');
                                } elseif (is_numeric($value) && (float) $value > $available) {
                                    $fail('Amount exceeds the available unpaid earnings of TZS '.number_format($available, 2).'.');
                                }
                            },
                        ])
                        ->helperText(function (Get $get): string {
                            $eventId = (int) ($get('event_id') ?: 0);

                            if (! $eventId || ! array_key_exists($eventId, $this->eligibleEvents())) {
                                return 'Select an authorized event to see its unpaid earnings available for a request.';
                            }

                            $balance = app(EventPayoutService::class)->ticketBalance($eventId);

                            if ($balance['refund_exception_count'] > 0) {
                                return 'Refund exceptions require reconciliation before requesting funds.';
                            }

                            if ($balance['requestable_pending_verification_balance'] <= 0) {
                                return 'Unpaid ticket earnings available for new requests: TZS 0.00.';
                            }

                            $message = 'Unpaid ticket earnings available for requests: TZS '.number_format($balance['requestable_pending_verification_balance'], 2).
                                '. Existing requests and completed payouts are already deducted. Approval requires separately verified settlement.';

                            $amount = $get('amount');
                            if (is_numeric($amount) && (float) $amount > $balance['requestable_pending_verification_balance']) {
                                return $message.' Your entered amount exceeds this balance.';
                            }

                            return $message;
                        }),
                    Select::make('destination_type')
                        ->label('Payment destination')
                        ->options(['bank' => 'Bank account', 'mobile_money' => 'Mobile money'])
                        ->live()
                        ->required(),
                    Textarea::make('destination_details')
                        ->label('Account name, provider and account/phone number')
                        ->rows(3)
                        ->maxLength(500)
                        ->live(debounce: 500)
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

                    Notification::make()->title('Payout request submitted')->body('Your request is pending administrator review and independent settlement verification.')->success()->duration(8000)->send();
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
