<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailOrganizerTicketSummaries extends Command
{
    protected $signature = 'tickets:email-organizer-summaries {--date= : Completed EAT date YYYY-MM-DD} {--dry-run : Preview without emailing}';
    protected $description = 'Email authorized organizers a per-event daily ticket summary';

    public function handle(): int
    {
        $zone = 'Africa/Dar_es_Salaam';
        $date = (string) ($this->option('date') ?: now($zone)->subDay()->toDateString());
        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $zone);
            if (! $day || $day->toDateString() !== $date || $day->greaterThanOrEqualTo(now($zone)->startOfDay())) {
                throw new \InvalidArgumentException('Invalid or incomplete day');
            }
        } catch (Throwable) {
            $this->error('Provide a completed date in YYYY-MM-DD format.');
            return self::FAILURE;
        }

        if (! $this->option('dry-run') && ! config('daily_ticket_summary.organizer_enabled', false)) {
            $this->warn('Organizer summary emails are disabled.');
            return self::FAILURE;
        }

        $orders = TicketOrder::query()
            ->where('status', TicketOrder::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $day->startOfDay()->utc())
            ->where('paid_at', '<', $day->addDay()->startOfDay()->utc())
            ->get(['id', 'event_id', 'quantity', 'total', 'organizer_net_amount']);

        foreach ($orders->groupBy('event_id') as $eventId => $eventOrders) {
            $event = Event::query()->find($eventId);
            if (! $event) {
                continue;
            }

            // No broad event contact address: only authenticated users with
            // an active organizer assignment or active organization ownership/admin role.
            $recipients = User::query()
                ->whereNotNull('email_verified_at')
                ->where(function ($query) use ($event): void {
                    $query->whereHas('assignedTicketingEvents', fn ($q) => $q->where('events.id', $event->id))
                        ->orWhereHas('managedOrganizations', fn ($q) => $q->where('organizations.id', $event->organization_id));
                })->get(['id', 'email'])->filter(fn ($user) => filter_var($user->email, FILTER_VALIDATE_EMAIL))
                ->unique(fn ($user) => strtolower($user->email));

            $totals = [
                'orders' => $eventOrders->count(),
                'tickets' => (int) $eventOrders->sum('quantity'),
                'free_tickets' => (int) $eventOrders->filter(fn ($o) => (float) $o->total === 0.0)->sum('quantity'),
                'gross' => round((float) $eventOrders->sum('total'), 2),
                'organizer_net' => round((float) $eventOrders->sum('organizer_net_amount'), 2),
            ];

            $categoryRows = TicketOrderItem::query()
                ->whereIn('ticket_order_id', $eventOrders->pluck('id'))
                ->with('ticketType:id,name')
                ->get(['ticket_order_id', 'ticket_type_id', 'quantity', 'total'])
                ->groupBy(fn ($item) => $item->ticketType?->name ?: 'Other')
                ->map(fn ($items, $name) => [
                    'name' => $name,
                    'tickets' => (int) $items->sum('quantity'),
                    'gross' => round((float) $items->sum('total'), 2),
                ])->values();

            $this->line($event->name.' | '.$date.' | '.$totals['tickets'].' tickets | '.count($recipients).' verified recipients');
            if ($this->option('dry-run')) {
                continue;
            }

            foreach ($recipients as $recipient) {
                $claimed = DB::table('daily_organizer_ticket_summary_deliveries')->insertOrIgnore([
                    'report_date' => $date,
                    'event_id' => $event->id,
                    'recipient_user_id' => $recipient->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if (! $claimed) {
                    continue;
                }

                try {
                    Mail::send('emails.daily-organizer-ticket-summary', [
                        'event' => $event, 'date' => $date, 'totals' => $totals, 'categories' => $categoryRows,
                    ], function ($message) use ($recipient, $event, $date): void {
                        $message->to($recipient->email)->subject('eLive Events | '.$event->name.' | Daily Ticket Sales | '.$date);
                    });
                    DB::table('daily_organizer_ticket_summary_deliveries')
                        ->where('report_date', $date)->where('event_id', $event->id)
                        ->where('recipient_user_id', $recipient->id)
                        ->update(['sent_at' => now(), 'updated_at' => now()]);
                } catch (Throwable $exception) {
                    Log::error('Organizer ticket summary email failed', [
                        'event_id' => $event->id, 'recipient_id' => $recipient->id,
                        'date' => $date, 'exception_class' => get_class($exception),
                    ]);
                    $this->error('Email failure; claim retained for manual investigation.');
                    return self::FAILURE;
                }
            }
        }

        return self::SUCCESS;
    }
}
