<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TicketCheckInService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}
    public function checkInByQrToken(
        string $rawQrToken
    ): array {
        return $this->checkInByCredential(
            $rawQrToken
        );
    }

    public function checkInByCredential(
        string $credential,
        ?int $eventId = null,
    ): array {
        $credential = trim(
            $credential
        );

        return DB::transaction(
            function () use ($credential, $eventId): array {
                $qrTokenHash = hash(
                    'sha256',
                    $credential
                );

                $tickets = Ticket::query()->lockForUpdate();

                if ($eventId !== null) {
                    $tickets->where('event_id', $eventId);
                }

                $ticket = (clone $tickets)
                    ->where(
                        'qr_token_hash',
                        $qrTokenHash
                    )
                    ->first();

                if (! $ticket) {
                    $ticket = (clone $tickets)
                        ->where(
                            'ticket_number',
                            $credential
                        )
                        ->first();
                }

                if (! $ticket) {
                    return [
                        'success' => false,
                        'status' => 'ticket_not_found',
                        'message' => 'Ticket could not be found.',
                    ];
                }

                if ($ticket->isUsed()) {
                    $existingCheckIn = DB::table(
                        'ticket_check_ins'
                    )
                        ->where(
                            'ticket_id',
                            $ticket->id
                        )
                        ->orderByDesc(
                            'checked_in_at'
                        )
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

                DB::table(
                    'ticket_check_ins'
                )->insert([
                    'event_id' =>
                        $ticket->event_id,

                    'ticket_id' =>
                        $ticket->id,

                    'check_in_point_id' =>
                        null,

                    'checked_in_by' =>
                        $eventId === null ? Auth::id() : null,

                    'method' =>
                        'qr',

                    'checked_in_at' =>
                        $checkedInAt,

                    'device_name' =>
                        request()->userAgent(),

                    'ip_address' =>
                        request()->ip(),

                    'note' =>
                        $eventId === null ? null : 'Shared scanner link',

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

    public function undoCheckIn(int $eventId, int $ticketId, User $actor, ?int $expectedCheckInId = null): bool
    {
        return DB::transaction(function () use ($eventId, $ticketId, $expectedCheckInId, $actor): bool {
            $ticket = Ticket::query()->where('event_id', $eventId)->lockForUpdate()->find($ticketId);

            if ($ticket?->status !== Ticket::STATUS_USED || ! $ticket->event?->canResetTicketCheckInsBy($actor)) {
                return false;
            }

            $checkIns = DB::table('ticket_check_ins')->where('event_id', $eventId)->where('ticket_id', $ticketId);
            $checkIn = (clone $checkIns)->first();

            if (! $checkIn || ($expectedCheckInId !== null && (int) $checkIn->id !== $expectedCheckInId)) {
                return false;
            }

            $this->auditLogService->record(
                'ticket.check_in_undone',
                $ticket,
                $actor,
                [
                    'check_in_id' => $checkIn->id,
                    'order_number' => $ticket->order?->order_number,
                    'source' => 'admin',
                ],
                [
                    'status' => $ticket->status,
                    'checked_in_at' => $checkIn->checked_in_at,
                    'checked_in_by' => $checkIn->checked_in_by,
                    'check_in_point_id' => $checkIn->check_in_point_id,
                    'method' => $checkIn->method,
                    'device_name' => $checkIn->device_name,
                    'ip_address' => $checkIn->ip_address,
                    'note' => $checkIn->note,
                ],
                ['status' => Ticket::STATUS_ISSUED, 'used_at' => null],
            );

            $checkIns->where('id', $checkIn->id)->delete();
            $ticket->forceFill(['status' => Ticket::STATUS_ISSUED, 'used_at' => null])->save();

            return true;
        }, attempts: 3);
    }
}
