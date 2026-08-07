<?php

namespace App\Filament\Pages;

use App\Models\Attendee;
use App\Models\AttendeeCategory;
use App\Models\Event;
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

class AttendeeReport extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel =
        'Attendee Report';

    protected static ?string $title =
        'Attendee Report';

    protected static string|UnitEnum|null $navigationGroup =
        'Reports';

    protected static ?int $navigationSort = 10;

    protected string $view =
        'filament.pages.attendee-report';

    public ?int $eventId = null;

    public ?int $categoryId = null;

    public string $status = 'all';

    public string $registrationSource = 'all';

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
        $this->categoryId = null;
        $this->status = 'all';
        $this->registrationSource = 'all';
        $this->resetPage();
    }

    public function updatedCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedRegistrationSource(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50, 100], true)) {
            $this->perPage = 25;
        }

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

    public function getStatusOptionsProperty(): Collection
    {
        return $this->authorizedAttendeeQuery()
            ->when(
                $this->eventId,
                fn (Builder $query): Builder =>
                    $query->where('event_id', $this->eventId)
            )
            ->select('status')
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');
    }

    public function getSourceOptionsProperty(): Collection
    {
        return $this->authorizedAttendeeQuery()
            ->when(
                $this->eventId,
                fn (Builder $query): Builder =>
                    $query->where('event_id', $this->eventId)
            )
            ->select('registration_source')
            ->whereNotNull('registration_source')
            ->distinct()
            ->orderBy('registration_source')
            ->pluck('registration_source');
    }

    public function getAttendeesProperty(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with([
                'event:id,name',
                'category:id,name',
                'badgeType:id,name',
            ])
            ->orderBy('full_name')
            ->paginate($this->perPage);
    }

    public function getSummaryProperty(): array
    {
        $base = $this->filteredQuery();

        $total = (clone $base)->count();

        $checkedIn = (clone $base)
            ->whereNotNull('checked_in_at')
            ->count();

        $pendingApproval = (clone $base)
            ->where('status', 'pending_approval')
            ->count();

        $waitlisted = (clone $base)
            ->where('status', 'waitlisted')
            ->count();

        return [
            'total' => $total,
            'checked_in' => $checkedIn,
            'not_checked_in' => max($total - $checkedIn, 0),
            'pending_approval' => $pendingApproval,
            'waitlisted' => $waitlisted,
        ];
    }

    public function clearFilters(): void
    {
        $this->categoryId = null;
        $this->status = 'all';
        $this->registrationSource = 'all';
        $this->search = '';
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        $filename = 'attendee-report-'
            . now()->format('Y-m-d-His')
            . '.csv';

        Notification::make()
            ->title('Export started')
            ->body('Your attendee CSV report is being prepared.')
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
                    'Full Name',
                    'Phone',
                    'Email',
                    'Organization',
                    'Position',
                    'Category',
                    'Badge Type',
                    'Badge Number',
                    'Registration Status',
                    'Registration Source',
                    'Registered At',
                    'Checked In At',
                ]);

                $this->filteredQuery()
                    ->with([
                        'event:id,name',
                        'category:id,name',
                        'badgeType:id,name',
                    ])
                    ->orderBy('id')
                    ->chunkById(
                        500,
                        function (Collection $attendees) use ($handle): void {
                            foreach ($attendees as $attendee) {
                                fputcsv($handle, [
                                    $attendee->event?->name,
                                    $attendee->full_name,
                                    $attendee->phone,
                                    $attendee->email,
                                    $attendee->organization_name,
                                    $attendee->position,
                                    $attendee->category?->name,
                                    $attendee->badgeType?->name,
                                    $attendee->badge_number,
                                    $attendee->status,
                                    $attendee->registration_source,
                                    $attendee->registered_at?->format(
                                        'Y-m-d H:i:s'
                                    ),
                                    $attendee->checked_in_at?->format(
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
        return $this->authorizedAttendeeQuery()
            ->when(
                $this->eventId,
                fn (Builder $query): Builder =>
                    $query->where('event_id', $this->eventId)
            )
            ->when(
                $this->categoryId,
                fn (Builder $query): Builder =>
                    $query->where('category_id', $this->categoryId)
            )
            ->when(
                $this->status !== 'all',
                fn (Builder $query): Builder =>
                    $query->where('status', $this->status)
            )
            ->when(
                $this->registrationSource !== 'all',
                fn (Builder $query): Builder =>
                    $query->where(
                        'registration_source',
                        $this->registrationSource
                    )
            )
            ->when(
                filled($this->search),
                function (Builder $query): void {
                    $search = trim($this->search);

                    $query->where(
                        function (Builder $query) use ($search): void {
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
