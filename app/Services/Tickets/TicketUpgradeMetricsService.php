<?php

namespace App\Services\Tickets;

use App\Models\Event;
use App\Models\TicketUpgrade;

class TicketUpgradeMetricsService
{
    public function forEvent(Event $event): array
    {
        $base = TicketUpgrade::query()
            ->where('event_id', $event->getKey());

        $completed = (clone $base)
            ->where('status', TicketUpgrade::STATUS_COMPLETED);

        $pending = (clone $base)
            ->whereIn('status', TicketUpgrade::UNRESOLVED_STATUSES);

        $paths = (clone $completed)
            ->with(['fromTicketType:id,name', 'toTicketType:id,name'])
            ->latest('completed_at')
            ->get()
            ->groupBy(fn (TicketUpgrade $upgrade): string =>
                ($upgrade->fromTicketType?->name ?? 'Unknown')
                . ' → '
                . ($upgrade->toTicketType?->name ?? 'Unknown')
            )
            ->map(function ($upgrades, string $path): array {
                return [
                    'path' => $path,
                    'count' => $upgrades->count(),
                    'revenue' => (float) $upgrades->sum('upgrade_amount'),
                ];
            })
            ->values()
            ->all();

        return [
            'completed_upgrades' => (clone $completed)->count(),
            'pending_upgrades' => (clone $pending)->count(),
            'upgrade_revenue' => (float) (clone $completed)->sum('upgrade_amount'),
            'upgrade_net_payable' => (float) (clone $completed)
                ->whereNotNull('financial_snapshot_at')
                ->sum('organizer_net_amount'),
            'upgrade_paths' => $paths,
        ];
    }
}
