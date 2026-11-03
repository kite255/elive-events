<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\User;
use App\Services\Finance\OrganizerFinanceMetricsService;
use App\Services\EventPayoutService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class OrganizerFinanceOverview extends Page
{
    protected static ?string $navigationLabel = 'My Finance';

    protected static string|UnitEnum|null $navigationGroup =
        'Finance';

    protected static ?string $title = 'My Finance';

    protected static ?string $slug = 'my-finance';

    protected static ?int $navigationSort = 1;

    protected string $view =
        'filament.pages.organizer-finance-overview';

    public ?int $selectedEventId = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return $user
                ->ticketOrganizerOrganizations()
                ->exists();
        }

        return $user
            ->ownedOrganizations()
            ->exists();
    }

    public function mount(): void
    {
        if ($this->selectedEventId !== null) {
            return;
        }

        $this->selectedEventId = array_key_first(
            $this->eventOptions()
        );
    }

    public function eventOptions(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        if ($user->isTicketOrganizer()) {
            $assignedEventIds =
                $user->assignedTicketingEventIds();

            if ($assignedEventIds->isEmpty()) {
                return [];
            }

            return Event::query()
                ->whereIn(
                    'id',
                    $assignedEventIds
                )
                ->orderBy('name')
                ->pluck('name', 'id')
                ->mapWithKeys(
                    fn ($name, $id) => [
                        (int) $id => $name,
                    ]
                )
                ->toArray();
        }

        $organizationIds = $user
            ->ownedOrganizations()
            ->pluck('organizations.id');

        if ($organizationIds->isEmpty()) {
            return [];
        }

        return Event::query()
            ->whereIn(
                'organization_id',
                $organizationIds
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(
                fn ($name, $id) => [
                    (int) $id => $name,
                ]
            )
            ->toArray();
    }

    public function financeMetrics(): array
    {
        if ($this->selectedEventId === null) {
            return $this->emptyMetrics();
        }

        $event = $this->resolveAccessibleEvent(
            $this->selectedEventId
        );

        if (! $event) {
            return $this->emptyMetrics();
        }

        return app(
            OrganizerFinanceMetricsService::class
        )->forEvent($event);
    }

    /** Unpaid ticket earnings and outstanding payout reservations for the selected authorized event. */
    public function payoutSummary(): ?array
    {
        if ($this->selectedEventId === null) {
            return null;
        }

        $event = $this->resolveAccessibleEvent($this->selectedEventId);
        if (! $event) {
            return null;
        }

        $balance = app(EventPayoutService::class)->ticketBalance((int) $event->getKey());

        $remaining = max(0, round($balance['ticket_net_earnings'] - $balance['paid_out'], 2));

        return [
            'net_earnings' => $balance['ticket_net_earnings'],
            'paid_out' => $balance['paid_out'],
            'remaining_unpaid' => $remaining,
            'reserved_requests' => $balance['reserved_requests'],
            'available_for_request' => max(0, round($remaining - $balance['reserved_requests'], 2)),
        ];
    }

    public function financeMetricDefinitions(): array
    {
        return [
            'gross_sales' => [
                'label' => 'Ticket Gross Sales',
            ],

            'total_charges' => [
                'label' => 'Ticket Charges',
            ],

            'net_payable' => [
                'label' => 'Ticket Net Payable',
            ],
        ];
    }

    protected function resolveAccessibleEvent(
        int $eventId
    ): ?Event {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        if ($user->isTicketOrganizer()) {
            $assignedEventIds =
                $user->assignedTicketingEventIds();

            if ($assignedEventIds->isEmpty()) {
                return null;
            }

            return Event::query()
                ->whereKey($eventId)
                ->whereIn(
                    'id',
                    $assignedEventIds
                )
                ->first();
        }

        $organizationIds = $user
            ->ownedOrganizations()
            ->pluck('organizations.id');

        if ($organizationIds->isEmpty()) {
            return null;
        }

        return Event::query()
            ->whereKey($eventId)
            ->whereIn(
                'organization_id',
                $organizationIds
            )
            ->first();
    }

    private function emptyMetrics(): array
    {
        return [
            'gross_sales' => 0.0,
            'total_charges' => 0.0,
            'net_payable' => 0.0,
            'paid_orders' => 0,
            'completed_merchandise_payments' => 0,
            'merchandise_gross_sales' => 0.0,
            'currency' => 'TZS',
        ];
    }
}
