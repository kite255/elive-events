<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\CheckIn;
use App\Models\CheckInPoint;
use App\Models\EventDay;
use App\Models\EventSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckInService
{
    public const METHOD_QR = 'qr';

    public const METHOD_MANUAL = 'manual';

    public const METHOD_BADGE_NUMBER = 'badge_number';

    public const METHOD_PHONE = 'phone';

    public const METHOD_NAME = 'name';

    public const METHOD_ONSITE = 'onsite';

    /**
     * Check an attendee into an event, event day, or session/activity.
     *
     * Duplicate protection is scoped to:
     * - event-level: attendee + event + point
     * - day-level: attendee + event day + point
     * - session-level: attendee + event day + session + point
     */
    public function checkIn(
        Attendee $attendee,
        ?int $checkInPointId = null,
        string $method = self::METHOD_MANUAL,
        ?string $note = null,
        ?int $eventDayId = null,
        ?int $eventSessionId = null
    ): array {
        try {
            return DB::transaction(function () use (
                $attendee,
                $checkInPointId,
                $method,
                $note,
                $eventDayId,
                $eventSessionId
            ): array {
                /*
                 * Lock the attendee to reduce the chance of two scanners
                 * processing the same attendee simultaneously.
                 */
                $lockedAttendee = Attendee::query()
                    ->with([
                        'event.organization',
                        'eventDays',
                        'eventSessions',
                    ])
                    ->lockForUpdate()
                    ->find($attendee->getKey());

                if (! $lockedAttendee) {
                    return $this->failure(
                        status: 'attendee_not_found',
                        message: 'The attendee could not be found.'
                    );
                }

                $accessResult = $this->validateOfficerAccess(
                    $lockedAttendee
                );

                if (! $accessResult['success']) {
                    return $accessResult;
                }

                $eligibilityResult = $this->validateAttendeeEligibility(
                    $lockedAttendee
                );

                if (! $eligibilityResult['success']) {
                    return $eligibilityResult;
                }

                $eventDayResult = $this->resolveEventDay(
                    attendee: $lockedAttendee,
                    eventDayId: $eventDayId
                );

                if (! $eventDayResult['success']) {
                    return $eventDayResult;
                }

                /** @var EventDay|null $eventDay */
                $eventDay = $eventDayResult['event_day'];

                $eventSessionResult = $this->resolveEventSession(
                    attendee: $lockedAttendee,
                    eventDay: $eventDay,
                    eventSessionId: $eventSessionId
                );

                if (! $eventSessionResult['success']) {
                    return $eventSessionResult;
                }

                /** @var EventSession|null $eventSession */
                $eventSession = $eventSessionResult['event_session'];

                $pointResult = $this->resolveCheckInPoint(
                    attendee: $lockedAttendee,
                    checkInPointId: $checkInPointId
                );

                if (! $pointResult['success']) {
                    return $pointResult;
                }

                /** @var CheckInPoint|null $checkInPoint */
                $checkInPoint = $pointResult['check_in_point'];

                /*
                 * Duplicate protection mirrors the PostgreSQL partial
                 * unique indexes:
                 *
                 * Event-level:
                 * attendee + event + check-in point
                 *
                 * Day-level:
                 * attendee + event day + check-in point
                 *
                 * Session-level:
                 * attendee + event day + session + check-in point
                 *
                 * The attendee row is already locked above, which also
                 * serializes concurrent scans for the same attendee.
                 */
                $existingQuery = CheckIn::query()
                    ->where('event_id', $lockedAttendee->event_id)
                    ->where('attendee_id', $lockedAttendee->id);

                if ($eventDay) {
                    $existingQuery->where(
                        'event_day_id',
                        $eventDay->getKey()
                    );
                } else {
                    $existingQuery->whereNull('event_day_id');
                }

                if ($eventSession) {
                    $existingQuery->where(
                        'event_session_id',
                        $eventSession->getKey()
                    );
                } else {
                    $existingQuery->whereNull('event_session_id');
                }

                if ($checkInPoint) {
                    $existingQuery->where(
                        'check_in_point_id',
                        $checkInPoint->getKey()
                    );
                } else {
                    $existingQuery->whereNull('check_in_point_id');
                }

                $existingCheckIn = $existingQuery
                    ->lockForUpdate()
                    ->latest('checked_in_at')
                    ->first();

                if ($existingCheckIn) {
                    return $this->alreadyCheckedInResult(
                        attendee: $lockedAttendee,
                        checkIn: $existingCheckIn,
                        eventDay: $eventDay,
                        eventSession: $eventSession,
                        checkInPoint: $checkInPoint
                    );
                }

                /*
                 * Preserve legacy event-level behavior for events that do not
                 * use event-day records.
                 */
                if (
                    ! $eventDay
                    && ! $eventSession
                    && (
                        $lockedAttendee->status === 'checked_in'
                        || filled($lockedAttendee->checked_in_at)
                    )
                ) {
                    return $this->alreadyCheckedInResult(
                        attendee: $lockedAttendee,
                        checkIn: null,
                        eventDay: null,
                        eventSession: null,
                        checkInPoint: $checkInPoint
                    );
                }

                $checkedInAt = now();

                $checkIn = CheckIn::query()->create([
                    'event_id' => $lockedAttendee->event_id,
                    'event_day_id' => $eventDay?->getKey(),
                    'event_session_id' => $eventSession?->getKey(),
                    'attendee_id' => $lockedAttendee->id,
                    'check_in_point_id' => $checkInPoint?->getKey(),
                    'checked_in_by' => Auth::id(),
                    'method' => $this->normalizeMethod($method),
                    'checked_in_at' => $checkedInAt,
                    'device_name' => $this->deviceName(),
                    'ip_address' => request()->ip(),
                    'note' => $this->cleanNote($note),
                ]);

                /*
                 * checked_in_at remains the attendee's first successful
                 * check-in timestamp. Per-day history lives in check_ins.
                 */
                $lockedAttendee->forceFill([
                    'status' => 'checked_in',
                    'checked_in_at' =>
                        $lockedAttendee->checked_in_at
                        ?? $checkedInAt,
                ])->save();

                $message = sprintf(
                    '%s checked in successfully',
                    $lockedAttendee->full_name
                );

                if ($eventSession) {
                    $message .= ' for ' . $eventSession->name;
                } elseif ($eventDay) {
                    $message .= ' for ' . $eventDay->name;
                }

                $message .= '.';

                return [
                    'success' => true,
                    'status' => 'checked_in',
                    'message' => $message,
                    'attendee' => $lockedAttendee->fresh([
                        'event',
                        'eventDays',
                        'eventSessions',
                    ]),
                    'check_in' => $checkIn->fresh([
                        'attendee',
                        'eventDay',
                        'eventSession',
                        'checkInPoint',
                    ]),
                    'event_day' => $eventDay,
                    'event_session' => $eventSession,
                    'check_in_point' => $checkInPoint,
                    'checked_in_at' => $checkedInAt,
                ];
            }, 3);
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure(
                status: 'check_in_failed',
                message: 'Check-in failed. Please try again.',
                attendee: $attendee->fresh()
            );
        }
    }

    /**
     * Determine whether an attendee has checked in.
     *
     * When an event day is supplied, the check is day-specific.
     * When a check-in point is also supplied, it is day + point specific.
     */
    public function hasCheckedIn(
        Attendee $attendee,
        ?int $eventDayId = null,
        ?int $eventSessionId = null,
        ?int $checkInPointId = null
    ): bool {
        if ($eventDayId || $eventSessionId) {
            $query = CheckIn::query()
                ->where('event_id', $attendee->event_id)
                ->where('attendee_id', $attendee->id);

            if ($eventDayId) {
                $query->where(
                    'event_day_id',
                    $eventDayId
                );
            }

            if ($eventSessionId) {
                $query->where(
                    'event_session_id',
                    $eventSessionId
                );
            } else {
                $query->whereNull('event_session_id');
            }

            if ($checkInPointId) {
                $query->where(
                    'check_in_point_id',
                    $checkInPointId
                );
            }

            return $query->exists();
        }

        if (
            $attendee->status === 'checked_in'
            || filled($attendee->checked_in_at)
        ) {
            return true;
        }

        return CheckIn::query()
            ->where('event_id', $attendee->event_id)
            ->where('attendee_id', $attendee->id)
            ->exists();
    }

    /**
     * Return the attendee's latest check-in.
     */
    public function latestCheckIn(
        Attendee $attendee,
        ?int $eventDayId = null,
        ?int $eventSessionId = null
    ): ?CheckIn {
        return CheckIn::query()
            ->where('event_id', $attendee->event_id)
            ->where('attendee_id', $attendee->id)
            ->when(
                $eventDayId,
                fn ($query) => $query->where(
                    'event_day_id',
                    $eventDayId
                )
            )
            ->when(
                $eventSessionId,
                fn ($query) => $query->where(
                    'event_session_id',
                    $eventSessionId
                )
            )
            ->latest('checked_in_at')
            ->first();
    }

    /**
     * Validate whether the logged-in officer can check attendees
     * into the selected event.
     */
    private function validateOfficerAccess(
        Attendee $attendee
    ): array {
        $user = Auth::user();

        if (! $user instanceof User) {
            return $this->failure(
                status: 'unauthenticated',
                message: 'You must be logged in to perform check-in.',
                attendee: $attendee
            );
        }

        $event = $attendee->event;

        if (! $event) {
            return $this->failure(
                status: 'event_not_found',
                message: 'The attendee is not linked to a valid event.',
                attendee: $attendee
            );
        }

        if (! $event->canBeCheckedInBy($user)) {
            return $this->failure(
                status: 'access_denied',
                message: 'You are not authorized to check in attendees for this event.',
                attendee: $attendee
            );
        }

        return [
            'success' => true,
            'status' => 'authorized',
        ];
    }

    /**
     * Validate attendee status before check-in.
     */
    private function validateAttendeeEligibility(
        Attendee $attendee
    ): array {
        $blockedStatuses = [
            'pending_approval',
            'rejected',
            'waitlisted',
            'cancelled',
        ];

        if (in_array($attendee->status, $blockedStatuses, true)) {
            return $this->failure(
                status: 'attendee_not_eligible',
                message: match ($attendee->status) {
                    'pending_approval' =>
                        $attendee->full_name . ' is still awaiting approval.',

                    'rejected' =>
                        $attendee->full_name . ' registration was rejected.',

                    'waitlisted' =>
                        $attendee->full_name . ' is currently on the waitlist.',

                    'cancelled' =>
                        $attendee->full_name . ' registration was cancelled.',

                    default =>
                        $attendee->full_name . ' is not eligible for check-in.',
                },
                attendee: $attendee
            );
        }

        return [
            'success' => true,
            'status' => 'eligible',
        ];
    }

    /**
     * Resolve and validate the selected event day.
     */
    private function resolveEventDay(
        Attendee $attendee,
        ?int $eventDayId
    ): array {
        $activeDaysExist = EventDay::query()
            ->where('event_id', $attendee->event_id)
            ->where('status', 'active')
            ->exists();

        if (! $activeDaysExist) {
            return [
                'success' => true,
                'status' => 'no_event_day_required',
                'event_day' => null,
            ];
        }

        if (! $eventDayId) {
            return $this->failure(
                status: 'event_day_required',
                message: 'Select the event day before checking in this attendee.',
                attendee: $attendee
            );
        }

        $eventDay = EventDay::query()
            ->whereKey($eventDayId)
            ->where('event_id', $attendee->event_id)
            ->where('status', 'active')
            ->first();

        if (! $eventDay) {
            return $this->failure(
                status: 'invalid_event_day',
                message: 'The selected event day is not available for this event.',
                attendee: $attendee
            );
        }

        if (! $attendee->hasSelectedEventDay($eventDay)) {
            return $this->failure(
                status: 'event_day_not_selected',
                message: sprintf(
                    '%s is not registered to attend %s.',
                    $attendee->full_name,
                    $eventDay->name
                ),
                attendee: $attendee
            );
        }

        return [
            'success' => true,
            'status' => 'valid_event_day',
            'event_day' => $eventDay,
        ];
    }

    /**
     * Resolve and validate an optional session/activity.
     */
    private function resolveEventSession(
        Attendee $attendee,
        ?EventDay $eventDay,
        ?int $eventSessionId
    ): array {
        if (! $eventSessionId) {
            return [
                'success' => true,
                'status' => 'no_event_session',
                'event_session' => null,
            ];
        }

        $event = $attendee->event;

        if (! $event) {
            return $this->failure(
                status: 'event_not_found',
                message: 'The attendee is not linked to a valid event.',
                attendee: $attendee
            );
        }

        if (! $event->allowsSessionCheckIn()) {
            return $this->failure(
                status: 'session_check_in_disabled',
                message: 'Session-level check-in is disabled for this event.',
                attendee: $attendee
            );
        }

        if (! $eventDay) {
            return $this->failure(
                status: 'event_day_required',
                message: 'Select the event day before selecting a session.',
                attendee: $attendee
            );
        }

        $eventSession = EventSession::query()
            ->whereKey($eventSessionId)
            ->where('event_id', $attendee->event_id)
            ->where('event_day_id', $eventDay->getKey())
            ->where('status', EventSession::STATUS_ACTIVE)
            ->where('requires_check_in', true)
            ->first();

        if (! $eventSession) {
            return $this->failure(
                status: 'invalid_event_session',
                message: 'The selected session is not available for check-in.',
                attendee: $attendee
            );
        }

        if (
            $eventSession->requires_registration
            && ! $attendee->hasSelectedEventSession(
                $eventSession
            )
        ) {
            return $this->failure(
                status: 'event_session_not_selected',
                message: sprintf(
                    '%s is not registered for %s.',
                    $attendee->full_name,
                    $eventSession->name
                ),
                attendee: $attendee
            );
        }

        return [
            'success' => true,
            'status' => 'valid_event_session',
            'event_session' => $eventSession,
        ];
    }

    /**
     * Validate that the selected check-in point belongs to the
     * attendee's event.
     */
    private function resolveCheckInPoint(
        Attendee $attendee,
        ?int $checkInPointId
    ): array {
        if (! $checkInPointId) {
            return [
                'success' => true,
                'status' => 'no_check_in_point',
                'check_in_point' => null,
            ];
        }

        $checkInPoint = CheckInPoint::query()
            ->whereKey($checkInPointId)
            ->where('event_id', $attendee->event_id)
            ->first();

        if (! $checkInPoint) {
            return $this->failure(
                status: 'invalid_check_in_point',
                message: 'The selected check-in point does not belong to this event.',
                attendee: $attendee
            );
        }

        return [
            'success' => true,
            'status' => 'valid_check_in_point',
            'check_in_point' => $checkInPoint,
        ];
    }

    /**
     * Build the duplicate check-in response.
     */
    private function alreadyCheckedInResult(
        Attendee $attendee,
        ?CheckIn $checkIn,
        ?EventDay $eventDay = null,
        ?EventSession $eventSession = null,
        ?CheckInPoint $checkInPoint = null
    ): array {
        $checkedInAt = $checkIn?->checked_in_at
            ?? $attendee->checked_in_at;

        $message = sprintf(
            '%s has already checked in',
            $attendee->full_name
        );

        if ($eventSession) {
            $message .= ' for ' . $eventSession->name;
        } elseif ($eventDay) {
            $message .= ' for ' . $eventDay->name;
        }

        if ($checkInPoint) {
            $message .= ' at ' . $checkInPoint->name;
        }

        $message .= '.';

        if ($checkedInAt) {
            $message .= ' Previous check-in: '
                . $checkedInAt->format('d/m/Y H:i:s')
                . '.';
        }

        return [
            'success' => false,
            'status' => 'already_checked_in',
            'message' => $message,
            'attendee' => $attendee->fresh([
                'event',
                'eventDays',
                'eventSessions',
            ]),
            'check_in' => $checkIn,
            'event_day' => $eventDay ?? $checkIn?->eventDay,
            'event_session' =>
                $eventSession ?? $checkIn?->eventSession,
            'check_in_point' =>
                $checkInPoint ?? $checkIn?->checkInPoint,
            'checked_in_at' => $checkedInAt,
        ];
    }

    /**
     * Normalize the accepted check-in method.
     */
    private function normalizeMethod(string $method): string
    {
        $method = strtolower(trim($method));

        $allowedMethods = [
            self::METHOD_QR,
            self::METHOD_MANUAL,
            self::METHOD_BADGE_NUMBER,
            self::METHOD_PHONE,
            self::METHOD_NAME,
            self::METHOD_ONSITE,
        ];

        return in_array($method, $allowedMethods, true)
            ? $method
            : self::METHOD_MANUAL;
    }

    /**
     * Return a short device description rather than storing an
     * unlimited user-agent string.
     */
    private function deviceName(): ?string
    {
        $userAgent = request()->userAgent();

        if (blank($userAgent)) {
            return null;
        }

        return mb_substr($userAgent, 0, 500);
    }

    /**
     * Clean and limit an optional check-in note.
     */
    private function cleanNote(?string $note): ?string
    {
        if (blank($note)) {
            return null;
        }

        return mb_substr(trim($note), 0, 1000);
    }

    /**
     * Build a standard failure response.
     */
    private function failure(
        string $status,
        string $message,
        ?Attendee $attendee = null
    ): array {
        return [
            'success' => false,
            'status' => $status,
            'message' => $message,
            'attendee' => $attendee,
            'check_in' => null,
            'event_day' => null,
            'event_session' => null,
        ];
    }
}
