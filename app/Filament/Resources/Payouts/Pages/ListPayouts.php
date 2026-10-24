<?php

namespace App\Filament\Resources\Payouts\Pages;

use App\Filament\Resources\Payouts\PayoutResource;
use App\Models\Event;
use App\Models\User;
use App\Services\EventPayoutService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
            Action::make('requestPayout')
                ->label('Request Payout (Unavailable)')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => PayoutResource::canViewAny())
                ->disabled()
                ->tooltip('Requests are disabled until reconciled funds are available.')
                ->schema([
                    Select::make('event_id')
                        ->label('Event')
                        ->options(fn (): array => $this->eligibleEvents())
                        ->searchable()
                        ->required(),
                    TextInput::make('amount')
                        ->label('Amount (TZS)')
                        ->numeric()
                        ->minValue(0.01)
                        ->required(),
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

                    Notification::make()->title('Payout request submitted for review')->success()->send();
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
