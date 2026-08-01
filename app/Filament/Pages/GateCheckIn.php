<?php

namespace App\Filament\Pages;

use App\Models\Attendee;
use App\Models\AttendeeQrToken;
use App\Models\CheckInPoint;
use App\Models\Event;
use App\Services\CheckInService;
use App\Services\QrTokenService;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use UnitEnum;

class GateCheckIn extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static string|UnitEnum|null $navigationGroup = 'Check-in Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Gate Scanner';

    protected static ?string $title = 'Gate Scanner';

    protected string $view = 'filament.pages.gate-check-in';

    public ?array $data = [];

    public ?int $eventId = null;

    public ?string $eventName = null;

    public ?Attendee $attendee = null;

    public ?array $checkInResult = null;

    public bool $alreadyCheckedIn = false;

    public function mount(): void
    {
        $this->eventId = request()->integer('event_id') ?: null;

        if ($this->eventId) {
            $event = Event::query()->find($this->eventId);
            $this->eventName = $event?->name;
        }

        $this->form->fill([
            'event_id' => $this->eventId,
            'check_in_point_id' => null,
            'code' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Scanner Settings')
                    ->description('Select event and check-in point, then scan or enter a QR token / badge number.')
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
                            ->label('Check-In Point')
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

                        Forms\Components\TextInput::make('code')
                            ->label('QR Token / Badge Number')
                            ->placeholder('Scan QR code or enter badge number')
                            ->required()
                            ->maxLength(500)
                            ->autofocus(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ]),
            ])
            ->statePath('data');
    }

    public function verifyCode(): void
    {
        $data = $this->form->getState();

        $eventId = $data['event_id'] ?? request()->integer('event_id');
        $checkInPointId = $data['check_in_point_id'] ?? null;
        $code = trim($data['code'] ?? '');

        $this->attendee = null;
        $this->checkInResult = null;
        $this->alreadyCheckedIn = false;

        if (! $eventId || $code === '') {
            Notification::make()
                ->title('Missing information')
                ->body('Please select an event and scan or enter a code.')
                ->danger()
                ->send();

            return;
        }

        $plainToken = $this->extractToken($code);

        $attendee = app(QrTokenService::class)->findAttendeeByToken($plainToken);

        if (! $attendee) {
            $attendee = Attendee::query()
                ->with(['event', 'category', 'badgeType'])
                ->where('event_id', $eventId)
                ->where(function ($query) use ($code, $plainToken) {
                    $query->where('badge_number', $code)
                        ->orWhere('badge_number', $plainToken);
                })
                ->first();
        }

        if (! $attendee) {
            Notification::make()
                ->title('Invalid code')
                ->body('No attendee was found using this QR token or badge number.')
                ->danger()
                ->send();

            $this->checkInResult = [
                'success' => false,
                'status' => 'invalid',
                'message' => 'No attendee was found using this QR token or badge number.',
            ];

            return;
        }

        if ((int) $attendee->event_id !== (int) $eventId) {
            Notification::make()
                ->title('Wrong event')
                ->body($attendee->full_name . ' belongs to another event.')
                ->danger()
                ->send();

            $this->attendee = $attendee;
            $this->checkInResult = [
                'success' => false,
                'status' => 'wrong_event',
                'message' => $attendee->full_name . ' belongs to another event.',
            ];

            return;
        }

        $result = app(CheckInService::class)->checkIn(
            attendee: $attendee,
            checkInPointId: $checkInPointId,
            method: 'qr',
            note: 'Checked in from gate scanner.'
        );

        $this->attendee = $result['attendee'];
        $this->checkInResult = $result;
        $this->alreadyCheckedIn = $result['status'] === 'already_checked_in';

        if ($result['success']) {
            $this->markTokenAsUsed($plainToken);

            Notification::make()
                ->title('Check-in successful')
                ->body($result['message'])
                ->success()
                ->send();

            $this->form->fill([
                'event_id' => $eventId,
                'check_in_point_id' => $checkInPointId,
                'code' => null,
            ]);

            return;
        }

        Notification::make()
            ->title('Duplicate check-in blocked')
            ->body($result['message'])
            ->warning()
            ->send();
    }

    public function resetScanner(): void
    {
        $eventId = $this->data['event_id'] ?? request()->integer('event_id') ?: null;
        $checkInPointId = $this->data['check_in_point_id'] ?? null;

        $this->attendee = null;
        $this->checkInResult = null;
        $this->alreadyCheckedIn = false;

        $this->form->fill([
            'event_id' => $eventId,
            'check_in_point_id' => $checkInPointId,
            'code' => null,
        ]);
    }

    protected function extractToken(string $value): string
    {
        $value = trim($value);

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $path = parse_url($value, PHP_URL_PATH);

            if ($path) {
                $segments = array_values(array_filter(explode('/', $path)));

                return end($segments) ?: $value;
            }
        }

        return $value;
    }

    protected function markTokenAsUsed(string $plainToken): void
    {
        $tokenHash = hash('sha256', $plainToken);

        AttendeeQrToken::query()
            ->where('token_hash', $tokenHash)
            ->update([
                'used_at' => now(),
            ]);
    }
}