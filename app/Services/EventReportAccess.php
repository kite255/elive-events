<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Collection;

final class EventReportAccess
{
    public static function ids(?User $user): Collection
    {
        if (! $user || $user->is_gate_staff || $user->isTicketOrganizer()) {
            return collect();
        }

        return Event::query()->accessibleBy($user)->get()
            ->filter(fn (Event $event): bool => $event->canViewReportsBy($user))
            ->pluck('id')->values();
    }
}
