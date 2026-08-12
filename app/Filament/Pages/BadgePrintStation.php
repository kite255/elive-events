<?php

namespace App\Filament\Pages;

use App\Models\Attendee;
use App\Models\BadgePrintLog;
use App\Models\Event;
use App\Models\User;
use App\Services\BadgeGenerationService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\WithPagination;
use Throwable;
use UnitEnum;

class BadgePrintStation extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    protected static ?string $navigationLabel = 'Badge Print Station';

    protected static ?string $title = 'Badge Print Station';

    protected static string|UnitEnum|null $navigationGroup = 'Badge Management';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.badge-print-station';

    public ?int $eventId = null;

    public ?int $attendeeId = null;

    public string $search = '';

    public string $badgeStatus = 'generated';

    public int $perPage = 12;

    public array $printCopies = [];

    public array $printerNames = [];

    public array $reprintReasons = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $requestedAttendeeId = request()->integer('attendee');

        if ($requestedAttendeeId > 0) {
            $attendee = $this->authorizedAttendeeQuery()
                ->whereKey($requestedAttendeeId)
                ->first();

            if ($attendee) {
                $this->attendeeId = (int) $attendee->getKey();
                $this->eventId = (int) $attendee->event_id;
                $this->badgeStatus = 'all';
                $this->search = filled($attendee->badge_number)
                    ? (string) $attendee->badge_number
                    : (string) $attendee->full_name;

                return;
            }
        }

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
        $this->attendeeId = null;
        $this->resetPageState();
    }

    public function updatedSearch(): void
    {
        $this->attendeeId = null;
        $this->resetPageState();
    }

    public function updatedBadgeStatus(): void
    {
        $this->attendeeId = null;
        $this->resetPageState();
    }

    public function updatedPerPage(): void
    {
        $this->resetPageState();
    }

    protected function resetPageState(): void
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
            ->orderByDesc('id')
            ->get(['id', 'name']);
    }

    public function getAttendeesProperty(): LengthAwarePaginator
    {
        return $this->authorizedAttendeeQuery()
            ->with(['event', 'category', 'badgeType'])
            ->when(
                $this->eventId,
                fn (Builder $query): Builder =>
                    $query->where('event_id', $this->eventId)
            )
            ->when(
                $this->attendeeId,
                fn (Builder $query): Builder =>
                    $query->whereKey($this->attendeeId)
            )
            ->when(
                $this->badgeStatus !== 'all',
                function (Builder $query): void {
                    if (Schema::hasColumn('attendees', 'badge_status')) {
                        $query->where('badge_status', $this->badgeStatus);
                    }
                }
            )
            ->when(
                filled($this->search),
                function (Builder $query): void {
                    $search = trim($this->search);

                    $query->where(function (Builder $query) use ($search): void {
                        $query
                            ->where('full_name', 'ilike', "%{$search}%")
                            ->orWhere('phone', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%")
                            ->orWhere('badge_number', 'ilike', "%{$search}%")
                            ->orWhere('organization_name', 'ilike', "%{$search}%");
                    });
                }
            )
            ->orderBy('full_name')
            ->paginate($this->perPage);
    }

    public function getSelectedAttendeeProperty(): ?Attendee
    {
        if (! $this->attendeeId) {
            return null;
        }

        return $this->authorizedAttendeeQuery()
            ->with([
                'event',
                'category',
                'badgeType',
            ])
            ->whereKey($this->attendeeId)
            ->first();
    }

    public function selectAttendee(int $attendeeId): void
    {
        $attendee = $this->authorizedAttendeeQuery()
            ->whereKey($attendeeId)
            ->first();

        if (! $attendee) {
            Notification::make()
                ->title('Attendee not found')
                ->danger()
                ->send();

            return;
        }

        $this->attendeeId = (int) $attendee->getKey();

        $this->eventId = (int) $attendee->event_id;

        $this->search = filled($attendee->badge_number)
            ? (string) $attendee->badge_number
            : (string) $attendee->full_name;

        $this->badgeStatus = 'all';

        $this->resetPageState();
    }

    public function generateBadge(int $attendeeId): void
    {
        $attendee = $this->authorizedAttendeeQuery()
            ->with(['event', 'category', 'badgeType', 'qrToken'])
            ->whereKey($attendeeId)
            ->first();

        if (! $attendee) {
            Notification::make()
                ->title('Attendee not found')
                ->danger()
                ->send();

            return;
        }

        try {
            app(BadgeGenerationService::class)
                ->generateForAttendee($attendee);

            $attendee->refresh();

            Notification::make()
                ->title('Badge generated')
                ->body($attendee->full_name . ' badge is ready for printing.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            if (Schema::hasColumn('attendees', 'badge_status')) {
                $attendee->forceFill([
                    'badge_status' => 'failed',
                ])->save();
            }

            Notification::make()
                ->title('Badge generation failed')
                ->body(
                    app()->isLocal()
                        ? $exception->getMessage()
                        : 'The badge could not be generated.'
                )
                ->danger()
                ->send();
        }
    }

    public function markPrinted(int $attendeeId): void
    {
        $attendee = $this->authorizedAttendeeQuery()
            ->whereKey($attendeeId)
            ->first();

        if (! $attendee) {
            Notification::make()
                ->title('Attendee not found')
                ->danger()
                ->send();

            return;
        }

        $previousPrintCount = BadgePrintLog::query()
            ->where('attendee_id', $attendee->getKey())
            ->count();

        $printType = $previousPrintCount > 0
            ? 'reprint'
            : 'first_print';

        $validated = $this->validate([
            "printCopies.{$attendeeId}" => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
            "printerNames.{$attendeeId}" => [
                'nullable',
                'string',
                'max:255',
            ],
            "reprintReasons.{$attendeeId}" => [
                $printType === 'reprint'
                    ? 'required'
                    : 'nullable',
                'string',
                'max:1000',
            ],
        ], [
            "reprintReasons.{$attendeeId}.required" =>
                'Enter a reason before recording a reprint.',
        ]);

        $copies = (int) (
            $validated['printCopies'][$attendeeId]
            ?? 1
        );

        $printerName = filled(
            $validated['printerNames'][$attendeeId]
            ?? null
        )
            ? trim(
                $validated['printerNames'][$attendeeId]
            )
            : null;

        $reprintReason = $printType === 'reprint'
            ? trim(
                $validated['reprintReasons'][$attendeeId]
            )
            : null;

        BadgePrintLog::query()->create([
            'event_id' => $attendee->event_id,
            'attendee_id' => $attendee->getKey(),
            'printed_by' => auth()->id(),
            'copies' => $copies,
            'printer_name' => $printerName,
            'print_type' => $printType,
            'reprint_reason' => $reprintReason,
            'printed_at' => now(),
        ]);

        $data = [];

        if (Schema::hasColumn('attendees', 'badge_status')) {
            $data['badge_status'] = 'printed';
        }

        if (Schema::hasColumn('attendees', 'badge_printed_at')) {
            $data['badge_printed_at'] = now();
        }

        if ($data !== []) {
            $attendee->forceFill($data)->save();
        }

        $this->printCopies[$attendeeId] = 1;
        $this->reprintReasons[$attendeeId] = '';

        Notification::make()
            ->title(
                $printType === 'reprint'
                    ? 'Badge reprint recorded'
                    : 'Badge print recorded'
            )
            ->body(
                $attendee->full_name
                . ' badge print was recorded successfully.'
            )
            ->success()
            ->send();
    }

    public function getCountersProperty(): array
    {
        $query = $this->authorizedAttendeeQuery()
            ->when(
                $this->eventId,
                fn (Builder $query): Builder =>
                    $query->where('event_id', $this->eventId)
            );

        $total = (clone $query)->count();

        $generated = Schema::hasColumn('attendees', 'badge_status')
            ? (clone $query)
                ->where('badge_status', 'generated')
                ->count()
            : (clone $query)
                ->whereNotNull('badge_path')
                ->count();

        $printed = Schema::hasColumn('attendees', 'badge_status')
            ? (clone $query)
                ->where('badge_status', 'printed')
                ->count()
            : BadgePrintLog::query()
                ->when(
                    $this->eventId,
                    fn (Builder $query): Builder =>
                        $query->where('event_id', $this->eventId)
                )
                ->distinct('attendee_id')
                ->count('attendee_id');

        $failed = Schema::hasColumn('attendees', 'badge_status')
            ? (clone $query)
                ->where('badge_status', 'failed')
                ->count()
            : 0;

        $printedToday = BadgePrintLog::query()
            ->when(
                $this->eventId,
                fn (Builder $query): Builder =>
                    $query->where('event_id', $this->eventId)
            )
            ->whereDate('printed_at', today())
            ->sum('copies');

        return [
            'total' => $total,
            'generated' => $generated,
            'printed' => $printed,
            'failed' => $failed,
            'pending' => max(
                $total - $generated - $printed - $failed,
                0
            ),
            'printed_today' => (int) $printedToday,
        ];
    }

    public function getRecentPrintsProperty(): Collection
    {
        return BadgePrintLog::query()
            ->with([
                'attendee',
                'event',
                'printedBy',
            ])
            ->when(
                $this->eventId,
                fn (Builder $query): Builder =>
                    $query->where('event_id', $this->eventId)
            )
            ->latest('printed_at')
            ->limit(10)
            ->get();
    }

    public function getPrintHistoryCount(int $attendeeId): int
    {
        return BadgePrintLog::query()
            ->where('attendee_id', $attendeeId)
            ->count();
    }

    public function isReprint(Attendee $attendee): bool
    {
        return $this->getPrintHistoryCount(
            (int) $attendee->getKey()
        ) > 0;
    }

    public function clearSelectedAttendee(): void
    {
        $this->attendeeId = null;
        $this->search = '';
        $this->badgeStatus = 'generated';
        $this->printCopies = [];
        $this->printerNames = [];
        $this->reprintReasons = [];
        $this->resetValidation();
        $this->resetPageState();
    }

    public function badgeExists(Attendee $attendee): bool
    {
        return filled($attendee->badge_path)
            && Storage::disk('public')->exists($attendee->badge_path);
    }

    public function badgeUrl(Attendee $attendee): ?string
    {
        if (! $this->badgeExists($attendee)) {
            return null;
        }

        return asset(
            'storage/' . ltrim((string) $attendee->badge_path, '/')
        );
    }

    public function badgeStatusLabel(?string $status): string
    {
        return match ($status) {
            'generated' => 'Generated',
            'printed' => 'Printed',
            'generating' => 'Generating',
            'failed' => 'Failed',
            default => 'Pending',
        };
    }

    public function badgeStatusClass(?string $status): string
    {
        return match ($status) {
            'generated' => 'bg-green-50 text-green-700 ring-green-600/20',
            'printed' => 'bg-blue-50 text-blue-700 ring-blue-600/20',
            'generating' => 'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
            'failed' => 'bg-red-50 text-red-700 ring-red-600/20',
            default => 'bg-gray-50 text-gray-700 ring-gray-600/20',
        };
    }

    public function badgeStatusTone(?string $status): string
    {
        return match ($status) {
            'generated' => 'success',
            'printed' => 'info',
            'generating' => 'warning',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    public function canPrintBadge(Attendee $attendee): bool
    {
        return $this->badgeExists($attendee);
    }

    public function getPrintActionLabel(Attendee $attendee): string
    {
        return $this->isReprint($attendee)
            ? 'Record Reprint'
            : 'Record Print';
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
