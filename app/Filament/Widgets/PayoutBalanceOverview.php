<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Payouts\PayoutResource;
use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\User;
use App\Services\EventPayoutService;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

class PayoutBalanceOverview extends Widget
{
    protected string $view = 'filament.widgets.payout-balance-overview';

    protected int|string|array $columnSpan = 'full';

    public ?string $selectedEventId = null;

    public function mount(): void
    {
        $firstId = array_key_first($this->eventOptions());
        $this->selectedEventId = $firstId === null ? null : (string) $firstId;
    }

    public static function canView(): bool
    {
        return PayoutResource::canViewAny();
    }

    public function eventOptions(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return Event::query()
            ->when(! $user->isSuperAdmin(), function (Builder $query) use ($user): void {
                if ($user->isTicketOrganizer()) {
                    $query->whereIn('events.id', $user->assignedTicketingEvents()->select('events.id'));
                    return;
                }
                $query->where(function (Builder $scope) use ($user): void {
                    $scope->whereIn('organization_id', $user->managedOrganizations()->select('organizations.id'))
                        ->orWhereIn('events.id', $user->assignedTicketingEvents()->select('events.id'));
                });
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function overview(): ?array
    {
        $options = $this->eventOptions();
        $id = (int) ($this->selectedEventId ?: 0);

        if ($id <= 0 || ! array_key_exists($id, $options)) {
            return null;
        }

        $balance = app(EventPayoutService::class)->ticketBalance($id);

        $requests = EventPayoutRequest::query()->where('event_id', $id);

        return [
            ...$balance,
            // Derived from ticket earnings, never from the sum of settlement receipts.
            'remaining_unpaid_earnings' => max(0, round(
                $balance['ticket_net_earnings'] - $balance['paid_out'], 2
            )),
            'unreserved_earnings' => max(0, round(
                $balance['ticket_net_earnings'] - $balance['paid_out'] - $balance['reserved_requests'], 2
            )),
            'event_name' => $options[$id],
            'pending_count' => (clone $requests)->where('status', EventPayoutRequest::REQUESTED)->count(),
            'pending_amount' => (float) (clone $requests)->where('status', EventPayoutRequest::REQUESTED)->sum('amount'),
            'approved_amount' => (float) (clone $requests)->where('status', EventPayoutRequest::APPROVED)->sum('amount'),
            'paid_count' => (clone $requests)->where('status', EventPayoutRequest::PAID)->count(),
            'is_admin' => auth()->user()?->isSuperAdmin() === true,
        ];
    }
}
