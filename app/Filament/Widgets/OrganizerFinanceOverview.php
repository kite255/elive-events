<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\TicketOrder;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class OrganizerFinanceOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -9;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ! $user->isSuperAdmin()
            && ($user->managedOrganizations()->exists()
                || $user->assignedTicketingEvents()->exists());
    }

    protected function getStats(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $eventIds = Event::query()
            ->where(function (Builder $query) use ($user): void {
                $query->whereIn('organization_id', $user->managedOrganizations()->select('organizations.id'))
                    ->orWhereIn('events.id', $user->assignedTicketingEvents()->select('events.id'));
            })->select('events.id');

        $orders = TicketOrder::query()
            ->whereIn('event_id', clone $eventIds)
            ->where('status', TicketOrder::STATUS_PAID);

        $payouts = EventPayoutRequest::query()
            ->whereIn('event_id', clone $eventIds);

        $gross = (float) (clone $orders)->sum('gross_amount');
        $charges = (float) (clone $orders)->sum('total_charges');
        $net = (float) (clone $orders)->sum('organizer_net_amount');
        $paid = (float) (clone $payouts)
            ->where('status', EventPayoutRequest::PAID)->sum('amount');
        $reserved = (float) (clone $payouts)
            ->whereIn('status', [EventPayoutRequest::REQUESTED, EventPayoutRequest::APPROVED])
            ->sum('amount');

        return [
            Stat::make('Gross ticket sales', 'TZS '.number_format($gross, 2))
                ->description('Paid ticket orders only'),
            Stat::make('Ticket charges', 'TZS '.number_format($charges, 2))
                ->description('Recorded commission and processing deductions'),
            Stat::make('Net ticket earnings', 'TZS '.number_format($net, 2))
                ->description('Excludes refund adjustments not captured in snapshots'),
            Stat::make('Recorded payouts', 'TZS '.number_format($paid, 2))
                ->description('Marked paid after a confirmed transfer'),
            Stat::make('Pending payout requests', 'TZS '.number_format($reserved, 2))
                ->description('Requested or approved amounts'),
            Stat::make('Unallocated ticket balance', 'TZS '.number_format(max(0, $net - $paid - $reserved), 2))
                ->description('Indicative only; not verified cleared funds'),
        ];
    }
}
