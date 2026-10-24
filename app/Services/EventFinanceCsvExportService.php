<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventFinanceCsvExportService
{
    public function download(User $user): StreamedResponse
    {
        $events = Event::query()->when(! $user->isSuperAdmin(), function (Builder $query) use ($user): void {
            $query->where(function (Builder $scope) use ($user): void {
                $scope->whereIn('organization_id', $user->managedOrganizations()->select('organizations.id'))
                    ->orWhereIn('events.id', $user->assignedTicketingEvents()->select('events.id'));
            });
        });

        return response()->streamDownload(function () use ($events): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Event ID', 'Event', 'Paid Orders', 'Refunded Orders', 'Refunded Payments', 'Net Ticket Earnings (TZS)', 'Recorded Payouts (TZS)', 'Reserved Requests (TZS)', 'Indicative Balance (TZS)', 'Overallocated (TZS)', 'Cleared Funds Verified']);
            $reconciliation = app(EventFinanceReconciliationService::class);
            $events->orderBy('events.id')->chunkById(100, function ($batch) use ($out, $reconciliation): void {
                foreach ($batch as $event) {
                    $report = $reconciliation->inspect((int) $event->id);
                    $safeName = preg_replace('/^[=+@\\-\\t\\r]+/', "'", (string) $event->name);
                    fputcsv($out, [
                        $event->id, $safeName, $report['paid_orders'],
                        $report['refunded_or_partially_refunded_orders'],
                        $report['refunded_or_partially_refunded_payments'],
                        $report['net_ticket_earnings'], $report['recorded_payouts'],
                        $report['reserved_requests'], $report['indicative_unallocated_balance'],
                        $report['overallocated_amount'], 'No',
                    ]);
                }
            }, 'events.id', 'id');
            fclose($out);
        }, 'elive-event-finance-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
