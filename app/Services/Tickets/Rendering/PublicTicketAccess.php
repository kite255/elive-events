<?php

namespace App\Services\Tickets\Rendering;

use App\Models\Ticket;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class PublicTicketAccess
{
    public function findEligible(string $token): Ticket
    {
        $ticket = Ticket::query()
            ->with([
                'event.organization',
                'ticketType',
                'order',
            ])
            ->where('public_token', $token)
            ->firstOrFail();

        if (
            in_array(
                $ticket->status,
                [
                    Ticket::STATUS_CANCELLED,
                    Ticket::STATUS_REFUNDED,
                ],
                true
            )
            || ! $ticket->order?->isPaid()
        ) {
            $this->notFound();
        }

        try {
            if (blank($ticket->qr_token_encrypted)) {
                $this->notFound();
            }
        } catch (DecryptException) {
            $this->notFound();
        }

        return $ticket;
    }

    private function notFound(): never
    {
        throw (new ModelNotFoundException())
            ->setModel(Ticket::class);
    }
}
