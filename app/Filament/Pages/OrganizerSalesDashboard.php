<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\User;
use App\Services\Tickets\OrganizerSalesMetricsService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class OrganizerSalesDashboard extends Page
{
    protected static ?string $navigationLabel = 'Sales Dashboard';

    protected static string|UnitEnum|null $navigationGroup =
        'Ticketing';

    protected static ?string $title =
        'Organizer Sales Dashboard';

    protected static ?string $slug =
        'organizer-sales-dashboard';

    protected string $view =
        'filament.pages.organizer-sales-dashboard';

    public ?int $selectedEventId = null;

    public function mount(): void
    {
        $events = $this->eventOptions();

        if (
            $this->selectedEventId === null
            && count($events) > 0
        ) {
            $this->selectedEventId =
                (int) array_key_first($events);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Page Heading
    |--------------------------------------------------------------------------
    */

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Event Options
    |--------------------------------------------------------------------------
    */

    public function eventOptions(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            return Event::query()
                ->orderByDesc('starts_at')
                ->pluck('name', 'id')
                ->mapWithKeys(
                    fn ($name, $id) => [
                        (int) $id => $name,
                    ]
                )
                ->toArray();
        }

        if ($user->isTicketOrganizer()) {
            $organizationIds = $user
                ->ticketOrganizerOrganizations()
                ->pluck('organizations.id');

            return Event::query()
                ->whereIn(
                    'organization_id',
                    $organizationIds
                )
                ->orderByDesc('starts_at')
                ->pluck('name', 'id')
                ->mapWithKeys(
                    fn ($name, $id) => [
                        (int) $id => $name,
                    ]
                )
                ->toArray();
        }

        return Event::query()
            ->orderByDesc('starts_at')
            ->get()
            ->filter(
                fn (Event $event): bool =>
                    $user->canViewEventReports($event)
            )
            ->pluck('name', 'id')
            ->mapWithKeys(
                fn ($name, $id) => [
                    (int) $id => $name,
                ]
            )
            ->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | Sales Metrics
    |--------------------------------------------------------------------------
    */

    public function salesMetrics(): array
    {
        if (! $this->selectedEventId) {
            return $this->emptyMetrics();
        }

        $event = Event::query()
            ->find($this->selectedEventId);

        if (! $event) {
            return $this->emptyMetrics();
        }

        $user = Auth::user();

        if (
            $user instanceof User
            && ! $this->canUserViewEvent(
                $user,
                $event
            )
        ) {
            return $this->emptyMetrics();
        }

        return app(
            OrganizerSalesMetricsService::class
        )->forEvent($event);
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    protected function canUserViewEvent(
        User $user,
        Event $event
    ): bool {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isTicketOrganizer()) {
            return $user
                ->ticketOrganizerOrganizations()
                ->where(
                    'organizations.id',
                    $event->organization_id
                )
                ->exists();
        }

        return $user->canViewEventReports($event);
    }

    /*
    |--------------------------------------------------------------------------
    | Empty Metrics
    |--------------------------------------------------------------------------
    */

    protected function emptyMetrics(): array
    {
        return [
            'gross_sales' => 0,
            'paid_orders' => 0,
            'pending_orders' => 0,
            'expired_orders' => 0,
            'tickets_sold' => 0,
            'currency' => 'TZS',
            'sales_by_ticket_type' => [],
            'recent_orders' => [],
        ];
    }
}