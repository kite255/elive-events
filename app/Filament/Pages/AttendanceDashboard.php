<?php

namespace App\Filament\Pages;

use App\Models\Attendee;
use App\Models\AttendeeCategory;
use App\Models\CheckIn;
use App\Models\CheckInPoint;
use App\Models\Event;
use App\Models\EventDay;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class AttendanceDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-chart-bar-square';

    protected static string|UnitEnum|null $navigationGroup =
        'Check-in Management';

    protected static ?string $navigationLabel =
        'Attendance Dashboard';

    protected static ?string $title =
        'Attendance Dashboard';

    protected static ?int $navigationSort = 3;

    protected string $view =
        'filament.pages.attendance-dashboard';

    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */

    public ?int $eventId = null;

    public ?int $eventDayId = null;

    public ?string $eventName = null;

    /*
    |--------------------------------------------------------------------------
    | Main counters
    |--------------------------------------------------------------------------
    */

    public int $totalAttendees = 0;

    public int $checkedInAttendees = 0;

    public int $notCheckedInAttendees = 0;

    public int $manualCheckIns = 0;

    public int $qrCheckIns = 0;

    public int $onsiteCheckIns = 0;

    public int $todayCheckIns = 0;

    public float $attendanceRate = 0;

    /*
    |--------------------------------------------------------------------------
    | Dashboard collections
    |--------------------------------------------------------------------------
    */

    public Collection $recentCheckIns;

    public Collection $checkInsByPoint;

    public Collection $attendanceByCategory;

    public Collection $attendanceByDay;

    /*
    |--------------------------------------------------------------------------
    | Mount / authorization
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->recentCheckIns = collect();
        $this->checkInsByPoint = collect();
        $this->attendanceByCategory = collect();
        $this->attendanceByDay = collect();

        $requestedEventId = request()->integer('event_id');

        if ($requestedEventId > 0) {
            $event = $this->getAvailableEvents()
                ->firstWhere('id', $requestedEventId);

            if ($event) {
                $this->eventId = (int) $event->getKey();
            }
        }

        if (! $this->eventId) {
            $defaultEvent = $this->getAvailableEvents()->first();

            $this->eventId = $defaultEvent
                ? (int) $defaultEvent->getKey()
                : null;
        }

        $this->selectDefaultEventDay();
        $this->loadDashboard();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return Event::query()
            ->accessibleBy($user)
            ->exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /*
    |--------------------------------------------------------------------------
    | Filter changes
    |--------------------------------------------------------------------------
    */

    public function updatedEventId(): void
    {
        $this->eventDayId = null;

        $this->selectDefaultEventDay();
        $this->loadDashboard();
    }

    public function updatedEventDayId(): void
    {
        $this->loadDashboard();
    }

    public function refreshDashboard(): void
    {
        $this->loadDashboard();
    }

    /*
    |--------------------------------------------------------------------------
    | Available selections
    |--------------------------------------------------------------------------
    */

    public function getAvailableEvents(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        return Event::query()
            ->accessibleBy($user)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get();
    }

    public function getAvailableEventDays(): Collection
    {
        if (! $this->eventId) {
            return collect();
        }

        return EventDay::query()
            ->where('event_id', $this->eventId)
            ->where('status', 'active')
            ->orderBy('display_order')
            ->orderBy('event_date')
            ->orderBy('id')
            ->get();
    }

    public function getSelectedEvent(): ?Event
    {
        if (! $this->eventId) {
            return null;
        }

        return $this->getAvailableEvents()
            ->firstWhere('id', $this->eventId);
    }

    public function getSelectedEventDay(): ?EventDay
    {
        if (! $this->eventId || ! $this->eventDayId) {
            return null;
        }

        return EventDay::query()
            ->whereKey($this->eventDayId)
            ->where('event_id', $this->eventId)
            ->where('status', 'active')
            ->first();
    }

    private function selectDefaultEventDay(): void
    {
        $days = $this->getAvailableEventDays();

        if ($days->isEmpty()) {
            $this->eventDayId = null;

            return;
        }

        $today = $days->first(
            fn (EventDay $day): bool =>
                $day->event_date?->isToday() ?? false
        );

        $this->eventDayId = $today
            ? (int) $today->getKey()
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Dashboard loading
    |--------------------------------------------------------------------------
    */

    private function loadDashboard(): void
    {
        $event = $this->getSelectedEvent();

        $this->eventName = $event?->name;

        if (! $event) {
            $this->resetDashboardData();

            return;
        }

        $attendeesQuery = $this->eligibleAttendeesQuery();

        $checkInsQuery = $this->scopedCheckInsQuery();

        $this->totalAttendees =
            (clone $attendeesQuery)->count();

        $this->checkedInAttendees =
            (clone $checkInsQuery)
                ->distinct('attendee_id')
                ->count('attendee_id');

        $this->notCheckedInAttendees = max(
            $this->totalAttendees - $this->checkedInAttendees,
            0
        );

        $this->manualCheckIns =
            (clone $checkInsQuery)
                ->where('method', 'manual')
                ->count();

        $this->qrCheckIns =
            (clone $checkInsQuery)
                ->whereIn('method', [
                    'qr',
                    'badge_number',
                ])
                ->count();

        $this->onsiteCheckIns =
            (clone $checkInsQuery)
                ->where('method', 'onsite')
                ->count();

        $this->todayCheckIns =
            (clone $checkInsQuery)
                ->whereDate('checked_in_at', today())
                ->distinct('attendee_id')
                ->count('attendee_id');

        $this->attendanceRate =
            $this->totalAttendees > 0
                ? round(
                    (
                        $this->checkedInAttendees
                        / $this->totalAttendees
                    ) * 100,
                    1
                )
                : 0;

        $this->loadRecentCheckIns();
        $this->loadCheckInsByPoint();
        $this->loadAttendanceByCategory();
        $this->loadAttendanceByDay();
    }

    private function resetDashboardData(): void
    {
        $this->eventName = null;
        $this->totalAttendees = 0;
        $this->checkedInAttendees = 0;
        $this->notCheckedInAttendees = 0;
        $this->manualCheckIns = 0;
        $this->qrCheckIns = 0;
        $this->onsiteCheckIns = 0;
        $this->todayCheckIns = 0;
        $this->attendanceRate = 0;

        $this->recentCheckIns = collect();
        $this->checkInsByPoint = collect();
        $this->attendanceByCategory = collect();
        $this->attendanceByDay = collect();
    }

    /*
    |--------------------------------------------------------------------------
    | Base queries
    |--------------------------------------------------------------------------
    */

    private function eligibleAttendeesQuery(): Builder
    {
        $query = Attendee::query()
            ->where('event_id', $this->eventId)
            ->whereNotIn('status', [
                'rejected',
                'cancelled',
                'waitlisted',
            ]);

        if ($this->eventDayId) {
            $query->whereHas(
                'eventDays',
                fn (Builder $dayQuery): Builder =>
                    $dayQuery->where(
                        'event_days.id',
                        $this->eventDayId
                    )
            );
        }

        return $query;
    }

    private function scopedCheckInsQuery(): Builder
    {
        return CheckIn::query()
            ->where('event_id', $this->eventId)
            ->when(
                $this->eventDayId,
                fn (Builder $query): Builder =>
                    $query->where(
                        'event_day_id',
                        $this->eventDayId
                    ),
                fn (Builder $query): Builder =>
                    $query->whereNull('event_day_id')
            )
            ->whereNull('event_session_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Recent check-ins
    |--------------------------------------------------------------------------
    */

    private function loadRecentCheckIns(): void
    {
        $this->recentCheckIns = $this->scopedCheckInsQuery()
            ->with([
                'attendee.category',
                'event',
                'eventDay',
                'checkInPoint',
                'checkedInBy',
            ])
            ->latest('checked_in_at')
            ->limit(15)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Check-ins by point
    |--------------------------------------------------------------------------
    */

    private function loadCheckInsByPoint(): void
    {
        $this->checkInsByPoint = CheckInPoint::query()
            ->where('event_id', $this->eventId)
            ->withCount([
                'checkIns' => function (Builder $query): void {
                    $query
                        ->where('event_id', $this->eventId)
                        ->when(
                            $this->eventDayId,
                            fn (Builder $query): Builder =>
                                $query->where(
                                    'event_day_id',
                                    $this->eventDayId
                                ),
                            fn (Builder $query): Builder =>
                                $query->whereNull(
                                    'event_day_id'
                                )
                        )
                        ->whereNull('event_session_id');
                },
            ])
            ->orderByDesc('check_ins_count')
            ->orderBy('name')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Attendance by category
    |--------------------------------------------------------------------------
    */

    private function loadAttendanceByCategory(): void
    {
        $categories = AttendeeCategory::query()
            ->where('event_id', $this->eventId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $this->attendanceByCategory = $categories
            ->map(function (AttendeeCategory $category): array {
                $expectedQuery = $this->eligibleAttendeesQuery()
                    ->where(
                        'category_id',
                        $category->getKey()
                    );

                $checkedQuery = $this->scopedCheckInsQuery()
                    ->whereHas(
                        'attendee',
                        fn (Builder $query): Builder =>
                            $query->where(
                                'category_id',
                                $category->getKey()
                            )
                    );

                $expected = (clone $expectedQuery)->count();

                $checkedIn = (clone $checkedQuery)
                    ->distinct('attendee_id')
                    ->count('attendee_id');

                return [
                    'id' => $category->getKey(),
                    'name' => $category->name,
                    'expected' => $expected,
                    'checked_in' => $checkedIn,
                    'remaining' => max(
                        $expected - $checkedIn,
                        0
                    ),
                    'rate' => $expected > 0
                        ? round(
                            ($checkedIn / $expected) * 100,
                            1
                        )
                        : 0,
                ];
            })
            ->filter(
                fn (array $row): bool =>
                    $row['expected'] > 0
                    || $row['checked_in'] > 0
            )
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Attendance by event day
    |--------------------------------------------------------------------------
    */

    private function loadAttendanceByDay(): void
    {
        $days = EventDay::query()
            ->where('event_id', $this->eventId)
            ->orderBy('display_order')
            ->orderBy('event_date')
            ->orderBy('id')
            ->get();

        $this->attendanceByDay = $days
            ->map(function (EventDay $day): array {
                $expected = Attendee::query()
                    ->where('event_id', $this->eventId)
                    ->whereNotIn('status', [
                        'rejected',
                        'cancelled',
                        'waitlisted',
                    ])
                    ->whereHas(
                        'eventDays',
                        fn (Builder $query): Builder =>
                            $query->where(
                                'event_days.id',
                                $day->getKey()
                            )
                    )
                    ->count();

                $checkedIn = CheckIn::query()
                    ->where('event_id', $this->eventId)
                    ->where(
                        'event_day_id',
                        $day->getKey()
                    )
                    ->whereNull('event_session_id')
                    ->distinct('attendee_id')
                    ->count('attendee_id');

                return [
                    'id' => $day->getKey(),
                    'name' => $day->name,
                    'date' => $day->event_date,
                    'expected' => $expected,
                    'checked_in' => $checkedIn,
                    'remaining' => max(
                        $expected - $checkedIn,
                        0
                    ),
                    'rate' => $expected > 0
                        ? round(
                            ($checkedIn / $expected) * 100,
                            1
                        )
                        : 0,
                ];
            })
            ->values();
    }
}
