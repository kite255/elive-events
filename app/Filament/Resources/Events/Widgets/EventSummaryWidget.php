<?php

namespace App\Filament\Resources\Events\Widgets;

use App\Models\CommunicationLog;
use App\Models\Event;
use App\Models\EventDay;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class EventSummaryWidget extends Widget
{
    protected string $view =
        'filament.resources.events.widgets.event-summary-widget';

    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        /** @var Event|null $event */
        $event = $this->record;

        if (! $event) {
            return [
                'event' => null,
                'summary' => [],
            ];
        }

        $event->loadMissing([
            'organization',
        ]);

        $now = now();

        /*
         * Do not rely on an Event::eventDays() relationship here.
         * The Event model in this project does not define that
         * relationship name, so load event days directly.
         */
        $eventDays = EventDay::query()
            ->where('event_id', $event->getKey())
            ->orderBy('event_date')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        /** @var EventDay|null $todayDay */
        $todayDay = $eventDays->first(
            fn (EventDay $day): bool =>
                $day->event_date?->isToday()
                    ?? false
        );

        /** @var EventDay|null $nextDay */
        $nextDay = $eventDays->first(
            function (EventDay $day) use ($now): bool {
                if (! $day->event_date) {
                    return false;
                }

                return $day->event_date
                    ->copy()
                    ->startOfDay()
                    ->gte(
                        $now->copy()->startOfDay()
                    );
            }
        );

        $totalRegistrations = $event->attendees()
            ->count();

        $eligibleAttendees = $event->attendees()
            ->whereNotIn('status', [
                'pending_approval',
                'waitlisted',
                'cancelled',
                'rejected',
            ])
            ->count();

        $pendingApproval = $event->attendees()
            ->where('status', 'pending_approval')
            ->count();

        $waitlisted = $event->attendees()
            ->where('status', 'waitlisted')
            ->count();

        $checkedIn = $event->checkIns()
            ->whereNull('event_session_id')
            ->distinct('attendee_id')
            ->count('attendee_id');

        $capacity = $event->capacity
            ? (int) $event->capacity
            : null;

        $capacityUsed = $capacity && $capacity > 0
            ? round(
                ($eligibleAttendees / $capacity) * 100,
                1
            )
            : null;

        $remainingCapacity =
            $capacity && $capacity > 0
                ? max(
                    0,
                    $capacity - $eligibleAttendees
                )
                : null;

        $communication = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'queued' => 0,
        ];

        if (
            class_exists(CommunicationLog::class)
            && Schema::hasTable('communication_logs')
        ) {
            $communicationQuery =
                CommunicationLog::query()
                    ->where(
                        'event_id',
                        $event->getKey()
                    );

            $communication['total'] =
                (clone $communicationQuery)->count();

            $communication['sent'] =
                (clone $communicationQuery)
                    ->whereIn(
                        'status',
                        [
                            'sent',
                            'delivered',
                        ]
                    )
                    ->count();

            $communication['failed'] =
                (clone $communicationQuery)
                    ->where('status', 'failed')
                    ->count();

            $communication['queued'] =
                (clone $communicationQuery)
                    ->whereIn(
                        'status',
                        [
                            'queued',
                            'pending',
                            'processing',
                        ]
                    )
                    ->count();
        }

        return [
            'event' => $event,

            'summary' => [
                'registration_open' =>
                    (bool) $event->registration_is_open,

                'total_registrations' =>
                    $totalRegistrations,

                'eligible_attendees' =>
                    $eligibleAttendees,

                'pending_approval' =>
                    $pendingApproval,

                'waitlisted' =>
                    $waitlisted,

                'checked_in' =>
                    $checkedIn,

                'capacity' =>
                    $capacity,

                'capacity_used' =>
                    $capacityUsed,

                'remaining_capacity' =>
                    $remainingCapacity,

                'event_days_count' =>
                    $eventDays->count(),

                'today_day' =>
                    $todayDay,

                'next_day' =>
                    $nextDay,

                'communication' =>
                    $communication,
            ],
        ];
    }
}
