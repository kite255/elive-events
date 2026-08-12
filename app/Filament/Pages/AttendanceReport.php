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
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class AttendanceReport extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel =
        'Attendance Report';

    protected static ?string $title =
        'Attendance Report';

    protected static string|UnitEnum|null $navigationGroup =
        'Reports';

    protected static ?int $navigationSort = 20;

    protected string $view =
        'filament.pages.attendance-report';

    public ?int $eventId = null;

    public ?int $eventDayId = null;

    public ?int $categoryId = null;

    public string $attendanceStatus = 'all';

    public ?int $checkInPointId = null;

    public string $method = 'all';

    public ?int $officerId = null;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public string $search = '';

    public int $perPage = 25;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->eventId = $this->getEventsProperty()
            ->first()?->getKey();
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

    public function updatedEventId(): void
    {
        $this->eventDayId = null;
        $this->categoryId = null;
        $this->checkInPointId = null;
        $this->officerId = null;
        $this->resetPage();
    }

    public function updatedEventDayId(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatedAttendanceStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCheckInPointId(): void
    {
        $this->resetPage();
    }

    public function updatedMethod(): void
    {
        $this->resetPage();
    }

    public function updatedOfficerId(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function getEventsProperty(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        return Event::query()
            ->accessibleBy($user)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'name',
            ]);
    }

    public function getEventDaysProperty(): Collection
    {
        if (! $this->eventId) {
            return collect();
        }

        return EventDay::query()
            ->where('event_id', $this->eventId)
            ->orderBy('display_order')
            ->orderBy('event_date')
            ->orderBy('id')
            ->get([
                'id',
                'event_id',
                'name',
                'event_date',
                'status',
            ]);
    }

    public function getSelectedEventDayProperty(): ?EventDay
    {
        if (! $this->eventId || ! $this->eventDayId) {
            return null;
        }

        return EventDay::query()
            ->whereKey($this->eventDayId)
            ->where('event_id', $this->eventId)
            ->first();
    }

    public function getCategoriesProperty(): Collection
    {
        if (! $this->eventId) {
            return collect();
        }

        return AttendeeCategory::query()
            ->where('event_id', $this->eventId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);
    }

    public function getCheckInPointsProperty(): Collection
    {
        if (! $this->eventId) {
            return collect();
        }

        return CheckInPoint::query()
            ->where('event_id', $this->eventId)
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);
    }

    public function getMethodsProperty(): Collection
    {
        return CheckIn::query()
            ->whereIn('event_id', $this->accessibleEventIds())
            ->when(
                $this->eventId,
                fn (Builder $query): Builder =>
                    $query->where('event_id', $this->eventId)
            )
            ->when(
                $this->eventDayId,
                fn (Builder $query): Builder =>
                    $query->where(
                        'event_day_id',
                        $this->eventDayId
                    )
            )
            ->whereNotNull('method')
            ->distinct()
            ->orderBy('method')
            ->pluck('method');
    }

    public function getOfficersProperty(): Collection
    {
        return User::query()
            ->whereIn(
                'id',
                CheckIn::query()
                    ->whereIn(
                        'event_id',
                        $this->accessibleEventIds()
                    )
                    ->when(
                        $this->eventId,
                        fn (Builder $query): Builder =>
                            $query->where(
                                'event_id',
                                $this->eventId
                            )
                    )
                    ->when(
                        $this->eventDayId,
                        fn (Builder $query): Builder =>
                            $query->where(
                                'event_day_id',
                                $this->eventDayId
                            )
                    )
                    ->whereNotNull('checked_in_by')
                    ->select('checked_in_by')
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);
    }

    public function getAttendeesProperty(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with([
                'event:id,name',
                'category:id,name',
                'eventDays:id,event_id,name,event_date',
            ])
            ->orderBy('full_name')
            ->paginate($this->perPage);
    }

    public function getLatestCheckInsProperty(): Collection
    {
        return $this->latestCheckInsFor(
            $this->attendees
                ->getCollection()
                ->pluck('id')
                ->filter()
                ->values()
        );
    }

    public function registeredDaysLabel(Attendee $attendee): string
    {
        if (! $attendee->relationLoaded('eventDays')) {
            $attendee->load([
                'eventDays:id,event_id,name,event_date',
            ]);
        }

        if ($attendee->eventDays->isEmpty()) {
            return 'General event';
        }

        return $attendee->eventDays
            ->sortBy([
                ['event_date', 'asc'],
                ['id', 'asc'],
            ])
            ->map(function (EventDay $day): string {
                if ($day->event_date) {
                    return $day->name
                        . ' — '
                        . $day->event_date->format('d M Y');
                }

                return $day->name;
            })
            ->implode(', ');
    }

    public function checkedInDayLabel(?CheckIn $checkIn): string
    {
        if (! $checkIn) {
            return '—';
        }

        if ($checkIn->eventDay) {
            return $checkIn->eventDay->name;
        }

        return 'General event';
    }

    public function getSummaryProperty(): array
    {
        $base = $this->summaryBaseQuery();

        $total = (clone $base)->count();

        $checkedIn = (clone $base)
            ->whereExists(
                fn ($query) =>
                    $this->applyCheckInFilters(
                        $query
                            ->selectRaw('1')
                            ->from('check_ins')
                            ->whereColumn(
                                'check_ins.attendee_id',
                                'attendees.id'
                            )
                    )
            )
            ->count();

        return [
            'total' => $total,
            'checked_in' => $checkedIn,
            'not_checked_in' => max(
                $total - $checkedIn,
                0
            ),
            'attendance_rate' => $total > 0
                ? round(($checkedIn / $total) * 100, 1)
                : 0,
        ];
    }

    public function clearFilters(): void
    {
        $this->eventDayId = null;
        $this->categoryId = null;
        $this->attendanceStatus = 'all';
        $this->checkInPointId = null;
        $this->method = 'all';
        $this->officerId = null;
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->search = '';
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        $filename = 'attendance-report-'
            . now()->format('Y-m-d-His')
            . '.csv';

        Notification::make()
            ->title('Export started')
            ->body(
                'Your attendance CSV report is being prepared.'
            )
            ->success()
            ->send();

        return response()->streamDownload(
            function (): void {
                $handle = fopen('php://output', 'wb');

                if ($handle === false) {
                    return;
                }

                fwrite($handle, "\xEF\xBB\xBF");

                fputcsv($handle, [
                    'Event',
                    'Registered Days',
                    'Checked-in Day',
                    'Attendee',
                    'Badge Number',
                    'Category',
                    'Organization',
                    'Phone',
                    'Attendance Status',
                    'Check-in Point',
                    'Method',
                    'Officer',
                    'Checked-in At',
                ]);

                $this->filteredQuery()
                    ->with([
                        'event:id,name',
                        'category:id,name',
                        'eventDays:id,event_id,name,event_date',
                    ])
                    ->orderBy('id')
                    ->chunkById(
                        500,
                        function (
                            Collection $attendees
                        ) use ($handle): void {
                            $latestCheckIns =
                                $this->latestCheckInsFor(
                                    $attendees->pluck('id')
                                );

                            foreach ($attendees as $attendee) {
                                $checkIn = $latestCheckIns->get(
                                    $attendee->id
                                );

                                fputcsv($handle, [
                                    $attendee->event?->name,
                                    $this->registeredDaysLabel(
                                        $attendee
                                    ),
                                    $this->checkedInDayLabel(
                                        $checkIn
                                    ),
                                    $attendee->full_name,
                                    $attendee->badge_number,
                                    $attendee->category?->name,
                                    $attendee->organization_name,
                                    $attendee->phone,
                                    $checkIn
                                        ? 'Checked In'
                                        : 'Not Checked In',
                                    $checkIn?->checkInPoint?->name,
                                    $checkIn?->method,
                                    $checkIn?->checkedInBy?->name
                                        ?? (
                                            $checkIn
                                                ? 'System'
                                                : null
                                        ),
                                    $checkIn?->checked_in_at?->format(
                                        'Y-m-d H:i:s'
                                    ),
                                ]);
                            }
                        }
                    );

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' =>
                    'text/csv; charset=UTF-8',
            ]
        );
    }

    private function filteredQuery(): Builder
    {
        $query = $this->summaryBaseQuery();

        if ($this->attendanceStatus === 'checked_in') {
            $query->whereExists(
                fn ($checkInQuery) =>
                    $this->applyCheckInFilters(
                        $checkInQuery
                            ->selectRaw('1')
                            ->from('check_ins')
                            ->whereColumn(
                                'check_ins.attendee_id',
                                'attendees.id'
                            )
                    )
            );
        } elseif (
            $this->attendanceStatus === 'not_checked_in'
        ) {
            $query->whereNotExists(
                fn ($checkInQuery) =>
                    $this->applyCheckInFilters(
                        $checkInQuery
                            ->selectRaw('1')
                            ->from('check_ins')
                            ->whereColumn(
                                'check_ins.attendee_id',
                                'attendees.id'
                            )
                    )
            );
        } elseif ($this->hasCheckInFilters()) {
            /*
             * If a check-in-specific filter is selected while status is
             * "all", only attendees with a matching check-in are relevant.
             */
            $query->whereExists(
                fn ($checkInQuery) =>
                    $this->applyCheckInFilters(
                        $checkInQuery
                            ->selectRaw('1')
                            ->from('check_ins')
                            ->whereColumn(
                                'check_ins.attendee_id',
                                'attendees.id'
                            )
                    )
            );
        }

        return $query;
    }

    private function summaryBaseQuery(): Builder
    {
        return $this->authorizedAttendeeQuery()
            ->when(
                $this->eventId,
                fn (Builder $query): Builder =>
                    $query->where(
                        'attendees.event_id',
                        $this->eventId
                    )
            )
            ->when(
                $this->eventDayId,
                fn (Builder $query): Builder =>
                    $query->whereHas(
                        'eventDays',
                        fn (Builder $dayQuery): Builder =>
                            $dayQuery->where(
                                'event_days.id',
                                $this->eventDayId
                            )
                    )
            )
            ->when(
                $this->categoryId,
                fn (Builder $query): Builder =>
                    $query->where(
                        'category_id',
                        $this->categoryId
                    )
            )
            ->when(
                filled($this->search),
                function (Builder $query): void {
                    $search = trim($this->search);

                    $query->where(
                        function (
                            Builder $query
                        ) use ($search): void {
                            $query
                                ->where(
                                    'full_name',
                                    'ilike',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'phone',
                                    'ilike',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'email',
                                    'ilike',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'badge_number',
                                    'ilike',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'organization_name',
                                    'ilike',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            );
    }

    private function applyCheckInFilters($query)
    {
        return $query
            ->when(
                $this->eventId,
                fn ($query) => $query->where(
                    'check_ins.event_id',
                    $this->eventId
                )
            )
            ->when(
                $this->eventDayId,
                fn ($query) => $query->where(
                    'check_ins.event_day_id',
                    $this->eventDayId
                )
            )
            ->when(
                $this->checkInPointId,
                fn ($query) => $query->where(
                    'check_ins.check_in_point_id',
                    $this->checkInPointId
                )
            )
            ->when(
                $this->method !== 'all',
                fn ($query) => $query->where(
                    'check_ins.method',
                    $this->method
                )
            )
            ->when(
                $this->officerId,
                fn ($query) => $query->where(
                    'check_ins.checked_in_by',
                    $this->officerId
                )
            )
            ->when(
                filled($this->dateFrom),
                fn ($query) => $query->whereDate(
                    'check_ins.checked_in_at',
                    '>=',
                    $this->dateFrom
                )
            )
            ->when(
                filled($this->dateTo),
                fn ($query) => $query->whereDate(
                    'check_ins.checked_in_at',
                    '<=',
                    $this->dateTo
                )
            );
    }

    private function hasCheckInFilters(): bool
    {
        return filled($this->eventDayId)
            || filled($this->checkInPointId)
            || $this->method !== 'all'
            || filled($this->officerId)
            || filled($this->dateFrom)
            || filled($this->dateTo);
    }

    private function latestCheckInsFor(
        Collection $attendeeIds
    ): Collection {
        $attendeeIds = $attendeeIds
            ->filter()
            ->values();

        if ($attendeeIds->isEmpty()) {
            return collect();
        }

        $latestIdsQuery = CheckIn::query()
            ->whereIn(
                'attendee_id',
                $attendeeIds
            );

        $this->applyCheckInModelFilters(
            $latestIdsQuery
        );

        $latestIds = $latestIdsQuery
            ->selectRaw('MAX(id) AS id')
            ->groupBy('attendee_id')
            ->pluck('id');

        return CheckIn::query()
            ->whereIn('id', $latestIds)
            ->with([
                'eventDay:id,event_id,name,event_date',
                'checkInPoint:id,name',
                'checkedInBy:id,name',
            ])
            ->get()
            ->keyBy('attendee_id');
    }

    private function applyCheckInModelFilters(
        Builder $query
    ): Builder {
        return $query
            ->when(
                $this->eventId,
                fn (Builder $query): Builder =>
                    $query->where(
                        'event_id',
                        $this->eventId
                    )
            )
            ->when(
                $this->eventDayId,
                fn (Builder $query): Builder =>
                    $query->where(
                        'event_day_id',
                        $this->eventDayId
                    )
            )
            ->when(
                $this->checkInPointId,
                fn (Builder $query): Builder =>
                    $query->where(
                        'check_in_point_id',
                        $this->checkInPointId
                    )
            )
            ->when(
                $this->method !== 'all',
                fn (Builder $query): Builder =>
                    $query->where(
                        'method',
                        $this->method
                    )
            )
            ->when(
                $this->officerId,
                fn (Builder $query): Builder =>
                    $query->where(
                        'checked_in_by',
                        $this->officerId
                    )
            )
            ->when(
                filled($this->dateFrom),
                fn (Builder $query): Builder =>
                    $query->whereDate(
                        'checked_in_at',
                        '>=',
                        $this->dateFrom
                    )
            )
            ->when(
                filled($this->dateTo),
                fn (Builder $query): Builder =>
                    $query->whereDate(
                        'checked_in_at',
                        '<=',
                        $this->dateTo
                    )
            );
    }

    private function accessibleEventIds(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        return Event::query()
            ->accessibleBy($user)
            ->pluck('id');
    }

    private function authorizedAttendeeQuery(): Builder
    {
        $user = auth()->user();

        $query = Attendee::query();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereHas(
            'event',
            fn (Builder $eventQuery): Builder =>
                $eventQuery->accessibleBy($user)
        );
    }
}
