<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class EventFinanceStatementService
{
    public function rows(Event $event, User $user): array
    {
        abort_unless(\App\Filament\Resources\Events\Pages\EventFinanceWorkspace::canViewFinance($event, $user), 403);
        $internal = $user->isSuperAdmin();
        $summary = app(EventFinanceWorkspaceSummary::class)->summarize($event->id, $internal, $user->isTicketOrganizer() ? $user->id : null);
        $rows = [
            ['eLive Events — Financial statement'],
            ['Event', $event->name],
            ['Generated', now()->toDateTimeString(), config('app.timezone')],
            ['Recorded financial data only; not proof of settled cash or payout eligibility.'],
            [],
            ['Paid ticket orders'],
            ['Currency', 'Orders', 'Gross', 'Commission', 'Gateway fees', 'Charges', 'Organizer net'],
        ];

        foreach ($summary['ticket_sales'] as $row) {
            $rows[] = array_map(fn ($key) => $row[$key] ?? null, ['currency', 'paid_orders', 'gross', 'commission', 'gateway_fees', 'charges', 'net']);
        }

        $rows[] = [];
        $rows[] = ['Event-wide payout totals by status'];
        $rows[] = ['Currency', 'Status', 'Requests', 'Amount'];
        foreach ($summary['payouts'] as $row) {
            $rows[] = [$row['currency'], $row['status'], $row['requests'], $row['amount']];
        }

        $rows[] = [];
        $rows[] = [$user->isTicketOrganizer() ? 'My payout history (latest 500)' : 'Payout history (latest 500)'];
        $rows[] = ['Reference', 'Currency', 'Amount', 'Status', 'Requested', 'Paid', 'Receipt confirmed'];
        foreach (EventPayoutRequest::query()->where('event_id', $event->id)
            ->when($user->isTicketOrganizer(), fn ($query) => $query->where('requested_by', $user->id))
            ->orderByDesc('id')->limit(500)
            ->get(['reference', 'currency', 'amount', 'status', 'created_at', 'paid_at', 'received_at']) as $row) {
            $rows[] = [$row->reference, $row->currency, $row->amount, $row->status, $row->created_at?->toDateTimeString(), $row->paid_at?->toDateTimeString(), $row->received_at?->toDateTimeString()];
        }

        if ($internal) {
            $rows[] = [];
            $rows[] = ['Clearance summaries — manual documentary review'];
            $rows[] = ['Currency', 'Type', 'Review status', 'Entries', 'Amount'];
            foreach ($summary['clearance'] as $row) {
                $rows[] = [$row->currency, $row->entry_type, $row->review_status, $row->entries, $row->amount];
            }
            $rows[] = [];
            $rows[] = ['Reconciliation exceptions'];
            foreach ($summary['exceptions'] as $label => $count) {
                $rows[] = [$label, $count];
            }
            $rows[] = [];
            $rows[] = ['Payout audit history (latest 500)'];
            $rows[] = ['Payout reference', 'Action', 'Previous status', 'New status', 'Actor ID', 'Recorded'];
            foreach (DB::table('event_payout_audits as audit')
                ->join('event_payout_requests as payout', 'payout.id', '=', 'audit.event_payout_request_id')
                ->where('payout.event_id', $event->id)->orderByDesc('audit.id')->limit(500)
                ->get(['payout.reference', 'audit.action', 'audit.from_status', 'audit.to_status', 'audit.actor_id', 'audit.created_at']) as $row) {
                $rows[] = [$row->reference, $row->action, $row->from_status, $row->to_status, $row->actor_id, $row->created_at];
            }
        }

        return $rows;
    }
}
