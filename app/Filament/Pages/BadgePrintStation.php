<?php

namespace App\Filament\Pages;

use App\Models\Attendee;
use App\Models\Event;
use App\Services\BadgeGenerationService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    public string $search = '';

    public string $badgeStatus = 'generated';

    public int $perPage = 12;

    public function mount(): void
    {
        $this->eventId = Event::query()
            ->latest('id')
            ->value('id');
    }

    public function updatedEventId(): void
    {
        $this->resetPageState();
    }

    public function updatedSearch(): void
    {
        $this->resetPageState();
    }

    public function updatedBadgeStatus(): void
    {
        $this->resetPageState();
    }

    protected function resetPageState(): void
    {
        $this->resetPage();
    }

    public function getEventsProperty()
    {
        return Event::query()
            ->orderByDesc('id')
            ->get(['id', 'name']);
    }

    public function getAttendeesProperty(): LengthAwarePaginator
    {
        return Attendee::query()
            ->with(['event', 'category', 'badgeType'])
            ->when($this->eventId, fn ($query) => $query->where('event_id', $this->eventId))
            ->when($this->badgeStatus !== 'all', function ($query) {
                if (Schema::hasColumn('attendees', 'badge_status')) {
                    $query->where('badge_status', $this->badgeStatus);
                }
            })
            ->when(filled($this->search), function ($query) {
                $search = trim($this->search);

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('full_name', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%")
                        ->orWhere('badge_number', 'ilike', "%{$search}%")
                        ->orWhere('organization_name', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('full_name')
            ->paginate($this->perPage);
    }

    public function generateBadge(int $attendeeId): void
    {
        $attendee = Attendee::query()
            ->with(['event', 'category', 'badgeType', 'qrToken'])
            ->find($attendeeId);

        if (! $attendee) {
            Notification::make()
                ->title('Attendee not found')
                ->danger()
                ->send();

            return;
        }

        try {
            app(BadgeGenerationService::class)->generateForAttendee($attendee);

            Notification::make()
                ->title('Badge generated')
                ->body($attendee->full_name . ' badge is ready for printing.')
                ->success()
                ->send();
        } catch (Throwable $e) {
            report($e);

            if (Schema::hasColumn('attendees', 'badge_status')) {
                $attendee->forceFill([
                    'badge_status' => 'failed',
                ])->save();
            }

            Notification::make()
                ->title('Badge generation failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function markPrinted(int $attendeeId): void
    {
        $attendee = Attendee::query()->find($attendeeId);

        if (! $attendee) {
            return;
        }

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

        Notification::make()
            ->title('Badge marked as printed')
            ->body($attendee->full_name . ' badge has been marked as printed.')
            ->success()
            ->send();
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

        return asset('storage/' . ltrim((string) $attendee->badge_path, '/'));
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
}