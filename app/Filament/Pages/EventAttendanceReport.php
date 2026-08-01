<?php

namespace App\Filament\Pages;

use App\Exports\AttendeesExport;
use App\Models\Attendee;
use App\Models\Event;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

class EventAttendanceReport extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Event Attendance Report';

    protected static ?string $title = 'Event Attendance Report';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.event-attendance-report';

    public ?array $data = [];

    public ?int $eventId = null;

    public int $totalAttendees = 0;

    public int $checkedInAttendees = 0;

    public int $notCheckedInAttendees = 0;

    public float $attendanceRate = 0;

    public Collection $attendees;

    public function mount(): void
    {
        $this->attendees = collect();

        $this->form->fill([
            'event_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Select Event')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->label('Event')
                            ->options(fn () => Event::query()
                                ->latest()
                                ->pluck('name', 'id')
                                ->toArray()
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->placeholder('Select event'),
                    ]),
            ])
            ->statePath('data');
    }

    public function loadReport(): void
    {
        $data = $this->form->getState();

        $this->eventId = $data['event_id'] ?? null;

        if (! $this->eventId) {
            Notification::make()
                ->title('Select an event')
                ->body('Please select an event before loading report.')
                ->danger()
                ->send();

            return;
        }

        $this->totalAttendees = Attendee::query()
            ->where('event_id', $this->eventId)
            ->count();

        $this->checkedInAttendees = Attendee::query()
            ->where('event_id', $this->eventId)
            ->whereNotNull('checked_in_at')
            ->count();

        $this->notCheckedInAttendees = Attendee::query()
            ->where('event_id', $this->eventId)
            ->whereNull('checked_in_at')
            ->count();

        $this->attendanceRate = $this->totalAttendees > 0
            ? round(($this->checkedInAttendees / $this->totalAttendees) * 100, 1)
            : 0;

        $this->attendees = Attendee::query()
            ->with(['event', 'category', 'badgeType'])
            ->where('event_id', $this->eventId)
            ->latest()
            ->limit(50)
            ->get();
    }

    public function exportAll(): BinaryFileResponse
    {
        $this->ensureEventSelected();

        return Excel::download(
            new AttendeesExport(eventId: $this->eventId),
            'event-attendees-' . $this->eventId . '-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    public function exportCheckedIn(): BinaryFileResponse
    {
        $this->ensureEventSelected();

        return Excel::download(
            new AttendeesExport(status: 'checked_in', eventId: $this->eventId),
            'event-checked-in-' . $this->eventId . '-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    public function exportNotCheckedIn(): BinaryFileResponse
    {
        $this->ensureEventSelected();

        return Excel::download(
            new AttendeesExport(status: 'not_checked_in', eventId: $this->eventId),
            'event-not-checked-in-' . $this->eventId . '-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    protected function ensureEventSelected(): void
    {
        if (! $this->eventId) {
            $data = $this->form->getState();
            $this->eventId = $data['event_id'] ?? null;
        }
    }
}