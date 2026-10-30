<?php

namespace App\Services\Tickets;

use App\Jobs\SendAutomaticCommunicationJob;
use App\Models\CommunicationLog;
use App\Models\TicketOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrganizerTicketSaleNotificationService
{
    public function queueForPaidOrder(int $ticketOrderId): void
    {
        $queuedIds = DB::transaction(function () use ($ticketOrderId): array {
            $order = TicketOrder::query()
                ->with(['event.organization', 'items.ticketType'])
                ->whereKey($ticketOrderId)
                ->lockForUpdate()
                ->first();

            if (! $order || ! $order->isPaid() || ! $order->event || ! $order->financial_snapshot_at) {
                return [];
            }

            $event = $order->event;
            $organization = $event->organization;
            $ownerEmails = $organization
                ? $organization->users()->wherePivot('status', User::ORGANIZATION_STATUS_ACTIVE)
                    ->wherePivot('is_owner', true)->pluck('users.email')
                : collect();

            $ticketOrganizerEmails = $event->ticketingManagers()->pluck('users.email');

            $recipients = $ownerEmails->concat($ticketOrganizerEmails)
                ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
                ->map(fn ($email) => strtolower(trim($email)))
                ->unique()->values();

            $details = $order->items->map(function ($item): string {
                return ($item->ticketType?->name ?? 'Ticket').' x '.(int) $item->quantity;
            })->implode(', ');

            $message = "A ticket purchase has been confirmed on eLive Events.\n\n"
                ."Event: {$event->name}\n"
                ."Order: {$order->order_number}\n"
                ."Buyer name: ".(filled($order->buyer_name) ? trim($order->buyer_name) : 'Not provided')."\n"
                ."Buyer email: ".(filled($order->buyer_email) ? trim($order->buyer_email) : 'Not provided')."\n"
                ."Buyer phone: ".(filled($order->buyer_phone) ? trim($order->buyer_phone) : 'Not provided')."\n"
                ."Tickets: {$details}\n"
                ."Quantity: {$order->quantity}\n"
                ."Total paid: {$order->currency} ".number_format((float) $order->total, 2)."\n"
                ."Organizer net amount: {$order->currency} ".number_format((float) $order->organizer_net_amount, 2)."\n\n"
                ."This is a confirmed ticket payment, not a settlement or payout confirmation.";

            $ids = [];

            foreach ($recipients as $email) {
                $existing = CommunicationLog::query()
                    ->where('ticket_order_id', $order->id)
                    ->where('purpose', 'organizer_ticket_sale')
                    ->where('channel', CommunicationLog::CHANNEL_EMAIL)
                    ->where('recipient', $email)
                    ->exists();

                if ($existing) {
                    continue;
                }

                $log = CommunicationLog::query()->create([
                    'event_id' => $event->id,
                    'ticket_order_id' => $order->id,
                    'purpose' => 'organizer_ticket_sale',
                    'channel' => CommunicationLog::CHANNEL_EMAIL,
                    'recipient' => $email,
                    'subject' => 'New ticket sale confirmed - '.$event->name,
                    'message' => $message,
                    'status' => CommunicationLog::STATUS_QUEUED,
                    'queued_at' => now(),
                ]);

                $ids[] = $log->id;
            }

            return $ids;
        }, attempts: 3);

        foreach ($queuedIds as $id) {
            SendAutomaticCommunicationJob::dispatch($id)->onQueue('communications-email');
        }
    }
}
