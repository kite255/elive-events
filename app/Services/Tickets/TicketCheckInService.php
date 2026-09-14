<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TicketCheckInService
{
    public function checkInByQrToken(
        string $rawQrToken
    ): array {
        return DB::transaction(
            function () use ($rawQrToken): array {
                $qrTokenHash = hash(
                    'sha256',
                    $rawQrToken
                );

                $ticket = Ticket::query()
                    ->lockForUpdate()
                    ->where(
                        'qr_token_hash',
                        $qrTokenHash
                    )
                    ->first();

                if (! $ticket) {
                    return [
                        'success' => false,
                        'status' => 'ticket_not_found',
                        'message' => 'Ticket could not be found.',
                    ];
                }

                /*
                 * A ticket that has already been used is a duplicate scan,
                 * not a generic unusable-ticket case.
                 *
                 * Return the original check-in timestamp so the scanner UI
                 * can tell the officer when the ticket first entered.
                 */
                if ($ticket->isUsed()) {
                    $existingCheckIn = DB::table('ticket_check_ins')
                        ->where(
                            'ticket_id',
                            $ticket->id
                        )
                        ->orderByDesc('checked_in_at')
                        ->first();

                    return [
                        'success' => false,
                        'status' => 'already_used',
                        'message' => 'Ticket has already been checked in.',
                        'ticket' => $ticket,
                        'checked_in_at' =>
                            $existingCheckIn?->checked_in_at
                            ?? $ticket->used_at,
                    ];
                }

                if (
                    $ticket->status
                    !== Ticket::STATUS_ISSUED
                ) {
                    return [
                        'success' => false,
                        'status' => 'ticket_not_usable',
                        'message' => 'Ticket is not available for entry.',
                        'ticket' => $ticket,
                    ];
                }

                $checkedInAt = now();

                DB::table('ticket_check_ins')
                    ->insert([
                        'event_id' =>
                            $ticket->event_id,

                        'ticket_id' =>
                            $ticket->id,

                        'check_in_point_id' =>
                            null,

                        'checked_in_by' =>
                            Auth::id(),

                        'method' =>
                            'qr',

                        'checked_in_at' =>
                            $checkedInAt,

                        'device_name' =>
                            request()->userAgent(),

                        'ip_address' =>
                            request()->ip(),

                        'note' =>
                            null,

                        'created_at' =>
                            $checkedInAt,

                        'updated_at' =>
                            $checkedInAt,
                    ]);

                $ticket->forceFill([
                    'status' =>
                        Ticket::STATUS_USED,

                    'used_at' =>
                        $checkedInAt,
                ])->save();

                return [
                    'success' => true,
                    'status' => 'checked_in',
                    'message' => 'Ticket checked in successfully.',
                    'ticket' => $ticket->fresh(),
                    'checked_in_at' => $checkedInAt,
                ];
            },
            attempts: 3
        );
    }
}
