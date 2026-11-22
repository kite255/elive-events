<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_tickets_csv')
                ->label('Download CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (): StreamedResponse {
                    abort_unless(TicketResource::canViewAny(), 403);

                    $tickets = $this->getTableQueryForExport()->without(['attendee', 'checkIns']);

                    return response()->streamDownload(function () use ($tickets): void {
                        $output = fopen('php://output', 'wb');
                        fwrite($output, "\xEF\xBB\xBF");

                        fputcsv($output, [
                            'Ticket Number', 'Event', 'Ticket Type', 'Order Number',
                            'Buyer Name', 'Holder Name', 'Price', 'Currency', 'Status',
                            'Check-in', 'Checked In At', 'Issued At',
                        ], escape: '');

                        foreach ($tickets->lazy(500) as $ticket) {
                            $row = [
                                $ticket->ticket_number,
                                $ticket->event?->name,
                                $ticket->ticketType?->name,
                                $ticket->order?->order_number,
                                $ticket->order?->buyer_name,
                                $ticket->holder_name,
                                $ticket->price,
                                $ticket->currency,
                                str($ticket->status)->headline()->toString(),
                                $ticket->used_at ? 'Checked In' : 'Not Checked In',
                                $ticket->used_at?->toDateTimeString(),
                                $ticket->issued_at?->toDateTimeString(),
                            ];

                            // Keep values that spreadsheet apps could interpret as formulas as text.
                            $row = array_map(
                                fn (?string $value): ?string => preg_match('/^(?:\s*[=+@-]|[\t\r\n])/u', $value ?? '')
                                    ? "'".$value
                                    : $value,
                                $row,
                            );

                            fputcsv($output, $row, escape: '');
                        }

                        fclose($output);
                    }, 'all-tickets-'.now()->format('Y-m-d-His').'.csv', [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ]);
                }),
        ];
    }
}
