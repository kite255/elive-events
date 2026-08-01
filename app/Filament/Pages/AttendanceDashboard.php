<?php

namespace App\Filament\Pages;

use App\Models\Attendee;
use App\Models\CheckIn;
use App\Models\CheckInPoint;
use App\Models\Event;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class AttendanceDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|UnitEnum|null $navigationGroup = 'Check-in Management';

    protected static ?string $navigationLabel = 'Attendance Dashboard';

    protected static ?string $title = 'Attendance Dashboard';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.attendance-dashboard';

    public ?int $eventId = null;

    public ?string $eventName = null;

    public int $totalAttendees = 0;

    public int $checkedInAttendees = 0;

    public int $notCheckedInAttendees = 0;

    public int $manualCheckIns = 0;

    public int $qrCheckIns = 0;

    public int $todayCheckIns = 0;

    public float $attendanceRate = 0;

    public Collection $recentCheckIns;

    public Collection $checkInsByPoint;

    public function mount(): void
    {
        $this->eventId = request()->integer('event_id') ?: null;

        if ($this->eventId) {
            $event = Event::query()->find($this->eventId);
            $this->eventName = $event?->name;
        }

        $attendeesQuery = Attendee::query()
            ->when($this->eventId, fn ($query) => $query->where('event_id', $this->eventId));

        $checkInsQuery = CheckIn::query()
            ->when($this->eventId, fn ($query) => $query->where('event_id', $this->eventId));

        $this->totalAttendees = (clone $attendeesQuery)->count();

        $this->checkedInAttendees = (clone $attendeesQuery)
            ->whereNotNull('checked_in_at')
            ->count();

        $this->notCheckedInAttendees = (clone $attendeesQuery)
            ->whereNull('checked_in_at')
            ->count();

        $this->manualCheckIns = (clone $checkInsQuery)
            ->where('method', 'manual')
            ->count();

        $this->qrCheckIns = (clone $checkInsQuery)
            ->where('method', 'qr')
            ->count();

        $this->todayCheckIns = (clone $checkInsQuery)
            ->whereDate('checked_in_at', today())
            ->count();

        $this->attendanceRate = $this->totalAttendees > 0
            ? round(($this->checkedInAttendees / $this->totalAttendees) * 100, 1)
            : 0;

        $this->recentCheckIns = CheckIn::query()
            ->with(['attendee', 'event', 'checkInPoint', 'checkedInBy'])
            ->when($this->eventId, fn ($query) => $query->where('event_id', $this->eventId))
            ->latest('checked_in_at')
            ->limit(10)
            ->get();

        $this->checkInsByPoint = CheckInPoint::query()
            ->when($this->eventId, fn ($query) => $query->where('event_id', $this->eventId))
            ->withCount([
                'checkIns' => fn ($query) => $query->when(
                    $this->eventId,
                    fn ($query) => $query->where('event_id', $this->eventId)
                ),
            ])
            ->orderByDesc('check_ins_count')
            ->get();
    }
}