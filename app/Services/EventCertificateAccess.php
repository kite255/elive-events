<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventCertificateSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EventCertificateAccess
{
    public function authorize(User $user, int $eventId): Event
    {
        abort_if($user->is_gate_staff, 403);
        $query = Event::query()->accessibleBy($user);
        if ($user->isTicketOrganizer()) {
            $query->whereIn('events.id', $user->assignedTicketingEventIds());
        }
        $event = $query->whereKey($eventId)->firstOrFail();
        abort_unless($event->canBeManagedBy($user), 403);

        return $event;
    }

    public function configure(User $user, int $eventId, bool $enabled, string $rule): EventCertificateSetting
    {
        $this->authorize($user, $eventId);
        Validator::make(['eligibility_rule' => $rule], [
            'eligibility_rule' => ['required', Rule::in(EventCertificateSetting::RULES)],
        ])->validate();

        return DB::transaction(function () use ($user, $eventId, $enabled, $rule): EventCertificateSetting {
            Event::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
            $event = $this->authorize($user, $eventId);

            return $event->certificateSettings()->updateOrCreate([], [
                'enabled' => $enabled,
                'eligibility_rule' => $rule,
            ]);
        });
    }

    public function eligibleAttendees(User $user, int $eventId, array $manualIds = []): Builder
    {
        $event = $this->authorize($user, $eventId);
        $settings = $event->certificateSettings()->first();
        abort_unless($settings?->enabled && ! $event->isCancelled(), 403);

        $query = $event->attendees()->getQuery()
            ->whereIn('status', ['registered', 'approved', 'confirmed', 'checked_in']);

        return match ($settings->eligibility_rule) {
            'registered' => $query,
            'confirmed' => $query->whereIn('status', ['approved', 'confirmed', 'checked_in']),
            'checked_in' => $query->where(function (Builder $query): void {
                $query->where('status', 'checked_in')->orWhereNotNull('checked_in_at');
            }),
            'manual' => $query->whereIn('attendees.id', $manualIds),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
