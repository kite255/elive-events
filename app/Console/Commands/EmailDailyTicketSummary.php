<?php

namespace App\Console\Commands;

use App\Models\TicketOrder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class EmailDailyTicketSummary extends Command
{
    protected $signature = 'tickets:email-daily-summary {--date= : EAT report date (YYYY-MM-DD)} {--dry-run : Preview counts without sending}';
    protected $description = 'Email a daily confirmed-ticket sales summary to explicitly configured internal recipients';

    public function handle(): int
    {
        $tz = config('daily_ticket_summary.timezone', 'Africa/Dar_es_Salaam');
        $date = $this->option('date') ?: now($tz)->subDay()->toDateString();
        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', (string) $date, $tz);
            if (! $day || $day->toDateString() !== $date) {
                throw new \InvalidArgumentException('Invalid date');
            }
        } catch (Throwable) {
            $this->error('Provide a valid YYYY-MM-DD date.');
            return self::FAILURE;
        }

        if ($day->greaterThanOrEqualTo(now($tz)->startOfDay())) {
            $this->error('Only completed days can be reported.');
            return self::FAILURE;
        }

        $start = $day->startOfDay()->utc();
        $end = $day->addDay()->startOfDay()->utc();

        // Orders are attributed to the day of confirmed payment, not creation.
        // This is an order-value report, not proof of gateway settlement.
        $orders = TicketOrder::query()
            ->where('status', TicketOrder::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $start)
            ->where('paid_at', '<', $end)
            ->with('event:id,name')
            ->get(['id', 'event_id', 'quantity', 'total', 'organizer_net_amount', 'paid_at']);

        $groups = $orders->groupBy('event_id')->map(function ($items) {
            return [
                'event' => $items->first()?->event?->name ?? 'Unknown event',
                'orders' => $items->count(),
                'tickets' => (int) $items->sum('quantity'),
                'free_tickets' => (int) $items->filter(fn ($o) => (float) $o->total === 0.0)->sum('quantity'),
                'gross' => round((float) $items->sum('total'), 2),
                'organizer_net' => round((float) $items->sum('organizer_net_amount'), 2),
            ];
        })->values()->sortBy('event')->values();

        $totals = [
            'orders' => $groups->sum('orders'),
            'tickets' => $groups->sum('tickets'),
            'free_tickets' => $groups->sum('free_tickets'),
            'gross' => round((float) $groups->sum('gross'), 2),
            'organizer_net' => round((float) $groups->sum('organizer_net'), 2),
        ];

        $this->line('Date (EAT): '.$date.' | orders: '.$totals['orders'].' | tickets: '.$totals['tickets'].' | gross: TZS '.number_format($totals['gross'], 2));

        if ($this->option('dry-run')) {
            $this->info('Dry run: no email sent or delivery records changed.');
            return self::SUCCESS;
        }
        if (! config('daily_ticket_summary.enabled', false)) {
            $this->warn('Daily summary email is disabled.');
            return self::FAILURE;
        }

        $recipients = array_unique(config('daily_ticket_summary.recipients', []));
        if (empty($recipients) || count(array_filter($recipients, fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))) !== count($recipients)) {
            $this->error('Configure valid DAILY_TICKET_SUMMARY_RECIPIENTS first.');
            return self::FAILURE;
        }

        foreach ($recipients as $email) {
            // Claim recipient/date to prevent duplicate automatic emails and concurrent sends.
            $claimed = DB::table('daily_ticket_summary_deliveries')->insertOrIgnore([
                'report_date' => $date,
                'recipient' => $email,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if (! $claimed) {
                $this->line('Already claimed for this date: '.$email);
                continue;
            }

            try {
                Mail::send('emails.daily-ticket-summary', [
                    'reportDate' => $date,
                    'totals' => $totals,
                    'groups' => $groups,
                ], function ($message) use ($email, $date): void {
                    $message->to($email)->subject('eLive Events | Daily Ticket Summary | '.$date);
                });
                DB::table('daily_ticket_summary_deliveries')
                    ->where('report_date', $date)->where('recipient', $email)
                    ->update(['sent_at' => now(), 'updated_at' => now()]);
                $this->info('Email submitted to configured mail transport: '.$email);
            } catch (Throwable $exception) {
                // Leave the claim in place until an administrator investigates.
                // Automatically retrying could duplicate a delivered email.
                Log::error('Daily ticket summary email failed', [
                    'date' => $date,
                    'recipient_hash' => hash('sha256', strtolower($email)),
                    'exception_class' => get_class($exception),
                ]);
                $this->error('Email failed; delivery claim retained to prevent duplicates.');
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
