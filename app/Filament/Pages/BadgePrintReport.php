<?php

namespace App\Filament\Pages;

use App\Models\BadgePrintLog;
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

class BadgePrintReport extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-printer';

    protected static ?string $navigationLabel =
        'Badge Print Report';

    protected static ?string $title =
        'Badge Print Report';

    protected static string|UnitEnum|null $navigationGroup =
        'Reports';

    protected static ?int $navigationSort = 30;

    protected string $view =
        'filament.pages.badge-print-report';

    public ?int $eventId = null;

    public string $printType = 'all';

    public ?int $printedBy = null;

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
        $this->printedBy = null;
        $this->resetPage();
    }

    public function updatedPrintType(): void
    {
        $this->resetPage();
    }

    public function updatedPrintedBy(): void
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

    public function getOfficersProperty(): Collection
    {
        return User::query()
            ->whereIn(
                'id',
                $this->authorizedPrintLogQuery()
                    ->when(
                        $this->eventId,
                        fn (Builder $query): Builder =>
                            $query->where('event_id', $this->eventId)
                    )
                    ->whereNotNull('printed_by')
                    ->select('printed_by')
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);
    }

    public function getPrintLogsProperty(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with([
                'event:id,name',
                'attendee:id,event_id,full_name,badge_number,organization_name',
                'printedBy:id,name',
            ])
            ->latest('printed_at')
            ->latest('id')
            ->paginate($this->perPage);
    }

    public function getSummaryProperty(): array
    {
        $base = $this->filteredQuery();

        return [
            'actions' => (clone $base)->count(),
            'first_prints' => (clone $base)
                ->where('print_type', 'first_print')
                ->count(),
            'reprints' => (clone $base)
                ->where('print_type', 'reprint')
                ->count(),
            'copies' => (int) (clone $base)->sum('copies'),
        ];
    }

    public function clearFilters(): void
    {
        $this->printType = 'all';
        $this->printedBy = null;
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->search = '';
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        $filename = 'badge-print-report-'
            . now()->format('Y-m-d-His')
            . '.csv';

        Notification::make()
            ->title('Export started')
            ->body('Your badge print CSV report is being prepared.')
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
                    'Attendee',
                    'Badge Number',
                    'Organization',
                    'Print Type',
                    'Copies',
                    'Printer Name',
                    'Reprint Reason',
                    'Printed By',
                    'Printed At',
                ]);

                $this->filteredQuery()
                    ->with([
                        'event:id,name',
                        'attendee:id,event_id,full_name,badge_number,organization_name',
                        'printedBy:id,name',
                    ])
                    ->orderBy('id')
                    ->chunkById(
                        500,
                        function (Collection $logs) use ($handle): void {
                            foreach ($logs as $log) {
                                fputcsv($handle, [
                                    $log->event?->name,
                                    $log->attendee?->full_name,
                                    $log->attendee?->badge_number,
                                    $log->attendee?->organization_name,
                                    $log->print_type,
                                    $log->copies,
                                    $log->printer_name,
                                    $log->reprint_reason,
                                    $log->printedBy?->name,
                                    $log->printed_at?->format(
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
        return $this->authorizedPrintLogQuery()
            ->when(
                $this->eventId,
                fn (Builder $query): Builder =>
                    $query->where('event_id', $this->eventId)
            )
            ->when(
                $this->printType !== 'all',
                fn (Builder $query): Builder =>
                    $query->where('print_type', $this->printType)
            )
            ->when(
                $this->printedBy,
                fn (Builder $query): Builder =>
                    $query->where('printed_by', $this->printedBy)
            )
            ->when(
                filled($this->dateFrom),
                fn (Builder $query): Builder =>
                    $query->whereDate(
                        'printed_at',
                        '>=',
                        $this->dateFrom
                    )
            )
            ->when(
                filled($this->dateTo),
                fn (Builder $query): Builder =>
                    $query->whereDate(
                        'printed_at',
                        '<=',
                        $this->dateTo
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
                                    'printer_name',
                                    'ilike',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'reprint_reason',
                                    'ilike',
                                    "%{$search}%"
                                )
                                ->orWhereHas(
                                    'attendee',
                                    function (Builder $attendeeQuery) use ($search): void {
                                        $attendeeQuery
                                            ->where(
                                                'full_name',
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
            );
    }

    private function authorizedPrintLogQuery(): Builder
    {
        $user = auth()->user();

        $query = BadgePrintLog::query();

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
