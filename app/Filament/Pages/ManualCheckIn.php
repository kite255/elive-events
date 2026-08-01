<?php

namespace App\Filament\Pages;

use App\Models\Attendee;
use App\Models\CheckInPoint;
use App\Models\Event;
use App\Services\CheckInService;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use UnitEnum;

class ManualCheckIn extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static string|UnitEnum|null $navigationGroup = 'Check-in Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Manual Check-in';

    protected static ?string $title = 'Manual Check-in';

    protected string $view = 'filament.pages.manual-check-in';

    public ?array $data = [];

    public ?Attendee $attendee = null;

    public bool $alreadyCheckedIn = false;

    public function mount(): void
    {
        $eventId = request()->integer('event_id') ?: null;

        $this->form->fill([
            'event_id' => $eventId,
            'check_in_point_id' => null,
            'search' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Manual Check-in Search')
                    ->description('Search attendee by full name, phone, email, or badge number.')
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
                            ->required()
                            ->default(fn () => request()->integer('event_id') ?: null)
                            ->disabled(fn () => request()->filled('event_id'))
                            ->dehydrated()
                            ->live(),

                        Forms\Components\Select::make('check_in_point_id')
                            ->label('Check-in Point')
                            ->options(function (Get $get) {
                                $eventId = $get('event_id') ?: request()->integer('event_id');

                                return CheckInPoint::query()
                                    ->when($eventId, fn ($query) => $query->where('event_id', $eventId))
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Only active check-in points for the selected event will appear.'),

                        Forms\Components\TextInput::make('search')
                            ->label('Attendee Search')
                            ->placeholder('Full name, phone, email, or badge number')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function searchAttendee(): void
    {
        $data = $this->form->getState();

        $eventId = $data['event_id'] ?? request()->integer('event_id');
        $search = trim($data['search'] ?? '');

        $this->attendee = null;
        $this->alreadyCheckedIn = false;

        if (! $eventId || $search === '') {
            Notification::make()
                ->title('Missing information')
                ->body('Please select an event and enter attendee search value.')
                ->danger()
                ->send();

            return;
        }

        $this->attendee = Attendee::query()
            ->where('event_id', $eventId)
            ->where(function ($query) use ($search) {
                $query->where('full_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhere('badge_number', 'ilike', "%{$search}%");
            })
            ->first();

        if (! $this->attendee) {
            Notification::make()
                ->title('Attendee not found')
                ->body('No attendee matches the search value for this event.')
                ->danger()
                ->send();

            return;
        }

        $this->alreadyCheckedIn = app(CheckInService::class)
            ->hasCheckedIn($this->attendee);

        if ($this->alreadyCheckedIn) {
            Notification::make()
                ->title('Already checked in')
                ->body($this->attendee->full_name . ' has already checked in.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Attendee found')
            ->body($this->attendee->full_name . ' is ready for check-in.')
            ->success()
            ->send();
    }

    public function confirmCheckIn(): void
    {
        $data = $this->form->getState();

        if (! $this->attendee) {
            Notification::make()
                ->title('No attendee selected')
                ->body('Search and select an attendee first.')
                ->danger()
                ->send();

            return;
        }

        $result = app(CheckInService::class)->checkIn(
            attendee: $this->attendee,
            checkInPointId: $data['check_in_point_id'] ?? null,
            method: 'manual',
            note: 'Checked in manually from admin panel.'
        );

        $this->attendee = $result['attendee'];
        $this->alreadyCheckedIn = true;

        if (! $result['success']) {
            Notification::make()
                ->title('Duplicate check-in blocked')
                ->body($result['message'])
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Check-in successful')
            ->body($result['message'])
            ->success()
            ->send();
    }

    public function resetSearch(): void
    {
        $eventId = $this->data['event_id'] ?? request()->integer('event_id') ?: null;
        $checkInPointId = $this->data['check_in_point_id'] ?? null;

        $this->attendee = null;
        $this->alreadyCheckedIn = false;

        $this->form->fill([
            'event_id' => $eventId,
            'check_in_point_id' => $checkInPointId,
            'search' => null,
        ]);
    }
}