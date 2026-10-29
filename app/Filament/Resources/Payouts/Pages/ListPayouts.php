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
                        ->required()
                        ->helperText('Choose the event whose cleared ticket proceeds will fund this request.'),
                    TextInput::make('amount')
                        ->label('Amount (TZS)')
                        ->numeric()
                        ->minValue(0.01)
                        ->required()
                        ->helperText(function (Get $get): string {
                            $eventId = (int) ($get('event_id') ?: 0);

                            if (! $eventId || ! array_key_exists($eventId, $this->eligibleEvents())) {
                                return 'Select an authorized event to see its reconciled available balance.';
                            }

                            $balance = app(EventPayoutService::class)->ticketBalance($eventId);

                            if ($balance['refund_exception_count'] > 0) {
                                return 'Refund exceptions require reconciliation before requesting funds.';
                            }

                            return 'Verified requestable balance: TZS '.number_format($balance['requestable_ticket_balance'], 2).
                                '. Pending requests and recorded payouts are already deducted.';
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

                    app(EventPayoutService::class)->request(
                        $event,
                        $user,
                        (float) $data['amount'],
                        $data['destination_type'],
                        $data['destination_details'],
                    );

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
