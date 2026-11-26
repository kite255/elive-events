<?php

namespace App\Services\Tickets;

use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\Ticket;
use App\Models\TicketOrder;
use DateTimeInterface;
use DateTimeZone;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;
use ZipArchive;

class EventReportArchive
{
    public function create(Event $event): string
    {
        $directory = sys_get_temp_dir().'/elive-event-reports-'.bin2hex(random_bytes(16));
        if (! mkdir($directory, 0700)) {
            throw new RuntimeException('Unable to create the report directory.');
        }

        $archivePath = false;

        try {
            $archivePath = tempnam(sys_get_temp_dir(), 'elive-report-');
            if ($archivePath === false) {
                throw new RuntimeException('Unable to create the report archive.');
            }

            DB::transaction(function () use ($event, $directory): void {
                if (DB::connection()->getDriverName() === 'pgsql' && DB::transactionLevel() === 1) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
                }

                $this->writeReports($event, $directory);
            });

            $zip = new ZipArchive;
            if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to open the report archive.');
            }

            try {
                foreach (glob($directory.'/*.csv') ?: [] as $path) {
                    if (! $zip->addFile($path, basename($path))) {
                        throw new RuntimeException('Unable to add a report to the archive.');
                    }
                }
            } finally {
                if (! $zip->close()) {
                    throw new RuntimeException('Unable to finish the report archive.');
                }
            }

            return $archivePath;
        } catch (Throwable $exception) {
            if ($archivePath !== false && is_file($archivePath)) {
                unlink($archivePath);
            }

            throw $exception;
        } finally {
            foreach (glob($directory.'/*.csv') ?: [] as $path) {
                unlink($path);
            }

            rmdir($directory);
        }
    }

    private function writeReports(Event $event, string $directory): void
    {
        $eventId = $event->getKey();
        $metrics = app(OrganizerSalesMetricsService::class)->forEvent($event);
        $payoutsAvailable = Schema::hasTable('event_payout_requests');

        $tickets = Ticket::query()->where('event_id', $eventId);
        $freeTickets = TicketOrder::query()
            ->where('event_id', $eventId)
            ->where('status', TicketOrder::STATUS_PAID)
            ->where('total', 0)
            ->sum('quantity');
        $checkedIn = (clone $tickets)->where(function ($query): void {
            $query->whereNotNull('used_at')->orWhereHas('checkIns');
        })->count();

        $this->writeCsv($directory, 'event-summary.csv', ['Metric', 'Value'], [
            ['Event', $event->name],
            ['Exported at (EAT)', now('Africa/Dar_es_Salaam')->format('Y-m-d H:i:s')],
            ['Currency', $metrics['currency']],
            ['Orders currently paid', $metrics['paid_orders']],
            ['Tickets in currently paid orders', $metrics['tickets_sold']],
            ['Tickets in currently paid zero-value orders', $freeTickets],
            ['Ticket records (all statuses)', $tickets->count()],
            ['Distinct tickets with a recorded check-in', $checkedIn],
            ['Gross sales on currently paid orders', $metrics['gross_sales']],
            ['Paid orders without a stored fee and net breakdown', $metrics['legacy_paid_orders']],
            ['Recorded platform commission on paid orders with a breakdown', $metrics['platform_commission_amount']],
            ['Recorded gateway fee on paid orders with a breakdown', $metrics['gateway_fee_amount']],
            ['Recorded total charges on paid orders with a breakdown', $metrics['total_charges']],
            ['Recorded organizer net on paid orders with a breakdown', $metrics['net_payable']],
            ['Payout ledger available', $payoutsAvailable ? 'Yes' : 'No'],
        ]);

        $this->writeCsv($directory, 'sales-by-ticket-type.csv', ['Ticket Type', 'Quantity', 'Revenue'], (function () use ($metrics): Generator {
            foreach ($metrics['sales_by_ticket_type'] as $type) {
                yield [$type['name'], $type['quantity'], $type['revenue']];
            }
        })());

        $this->writeCsv($directory, 'buyer-orders.csv', [
            'Buyer Name', 'Order Number', 'Order Status', 'Ticket Type Breakdown',
            'Ticket Quantity', 'Payment Confirmed (EAT)', 'Currency', 'Original Order Total',
            'Recorded eLive Commission', 'Recorded Gateway Fee', 'Recorded Total Charges', 'Recorded Organizer Net',
        ], $this->paidOrderRows($eventId));

        $this->writeCsv($directory, 'tickets-and-check-ins.csv', [
            'Buyer Name', 'Order Number', 'Ticket Number', 'Current Ticket Type', 'Ticket Status',
            'Issued At (EAT)', 'Checked In', 'First Recorded Check-in (EAT)', 'Check-in Records',
        ], $this->ticketRows($eventId));

        if ($payoutsAvailable) {
            $this->writeCsv($directory, 'payouts.csv', [
                'Payout Reference', 'Status', 'Currency', 'Requested Amount', 'Transferred Amount',
                'Transfer Date', 'Transfer Reference', 'Receipt Confirmed (EAT)',
            ], $this->payoutRows($eventId));
        }
    }

    private function writeCsv(string $directory, string $fileName, array $header, iterable $rows): void
    {
        $file = fopen($directory.'/'.$fileName, 'xb');
        if ($file === false) {
            throw new RuntimeException("Unable to create {$fileName}.");
        }

        try {
            $write = static function (array $cells) use ($file, $fileName): void {
                foreach ($cells as &$cell) {
                    if (is_string($cell) && preg_match('/^(?:[\t\r\n]|\s*[=+@-])/u', $cell)) {
                        if (! is_numeric($cell) || str_starts_with(ltrim($cell), '+')) {
                            $cell = "'" . $cell;
                        }
                    }
                }
                unset($cell);

                if (fputcsv($file, $cells, ',', '"', '') === false) {
                    throw new RuntimeException("Unable to write {$fileName}.");
                }
            };

            $write($header);
            foreach ($rows as $row) {
                $write($row);
            }
        } finally {
            fclose($file);
        }
    }

    private function eat(?DateTimeInterface $date): ?string
    {
        return $date?->setTimezone(new DateTimeZone('Africa/Dar_es_Salaam'))->format('Y-m-d H:i:s');
    }

    private function paidOrderRows(int $eventId): Generator
    {
        $orders = TicketOrder::query()
            ->where('event_id', $eventId)
            ->whereIn('status', [
                TicketOrder::STATUS_PAID,
                TicketOrder::STATUS_REFUNDED,
                TicketOrder::STATUS_PARTIALLY_REFUNDED,
            ])
            ->with('items.ticketType:id,name')
            ->lazyById(500);

        foreach ($orders as $order) {
            $breakdown = $order->items
                ->groupBy('ticket_type_id')
                ->map(fn ($items) => $items->sum('quantity') . ' × ' . ($items->first()->ticketType?->name ?? 'Unknown type'))
                ->implode(', ');
            $snapshot = $order->hasFinancialSnapshot();

            yield [
                $order->buyer_name, $order->order_number, $order->status, $breakdown,
                $order->quantity, $this->eat($order->paid_at), $order->currency, $order->total,
                $snapshot ? $order->platform_commission_amount : null,
                $snapshot ? $order->gateway_fee_amount : null,
                $snapshot ? $order->total_charges : null,
                $snapshot ? $order->organizer_net_amount : null,
            ];
        }
    }

    private function ticketRows(int $eventId): Generator
    {
        $tickets = Ticket::query()
            ->where('event_id', $eventId)
            ->with(['ticketType:id,name', 'order:id,buyer_name,order_number'])
            ->withCount('checkIns')
            ->withMin('checkIns as first_check_in_at', 'checked_in_at')
            ->withCasts(['first_check_in_at' => 'datetime'])
            ->lazyById(500);

        foreach ($tickets as $ticket) {
            yield [
                $ticket->order?->buyer_name, $ticket->order?->order_number, $ticket->ticket_number,
                $ticket->ticketType?->name, $ticket->status, $this->eat($ticket->issued_at),
                $ticket->used_at || $ticket->check_ins_count > 0 ? 'Yes' : 'No',
                $this->eat($ticket->first_check_in_at ?? $ticket->used_at), $ticket->check_ins_count,
            ];
        }
    }

    private function payoutRows(int $eventId): Generator
    {
        foreach (EventPayoutRequest::query()->where('event_id', $eventId)->lazyById(500) as $payout) {
            yield [
                $payout->reference, $payout->status, $payout->currency, $payout->amount,
                $payout->transferred_amount, $payout->transfer_date?->format('Y-m-d'),
                $payout->transfer_reference, $this->eat($payout->received_at),
            ];
        }
    }
}
