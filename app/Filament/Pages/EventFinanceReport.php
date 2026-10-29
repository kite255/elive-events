<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\User;
use App\Services\EventFinanceClearanceService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use App\Services\EventFinanceReconciliationService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class EventFinanceReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static string|UnitEnum|null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Event Finance';
    protected static ?string $title = 'Event Finance';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.pages.event-finance-report';

    public ?int $eventId = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (
            $user->isSuperAdmin()
            || $user->managedOrganizations()->exists()
            || $user->assignedTicketingEvents()->exists()
        );
    }

    private function accessibleEvents(): Builder
    {
        $user = auth()->user();
        $query = Event::query();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('organization_id', $user->managedOrganizations()->select('organizations.id'))
                ->orWhereIn('events.id', $user->assignedTicketingEvents()->select('events.id'));
        });
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $id = request()->integer('event_id');
        $this->eventId = $id > 0 && $this->accessibleEvents()->whereKey($id)->exists()
            ? $id
            : $this->accessibleEvents()->orderBy('name')->value('events.id');
    }

    public function updatedEventId(): void
    {
        if ($this->eventId && ! $this->accessibleEvents()->whereKey($this->eventId)->exists()) {
            $this->eventId = null;
        }
    }

    public function eventOptions(): array
    {
        return $this->accessibleEvents()->orderBy('name')->pluck('name', 'events.id')->all();
    }

    public function financeReport(): ?array
    {
        if (! $this->eventId || ! $this->accessibleEvents()->whereKey($this->eventId)->exists()) {
            return null;
        }

        return app(EventFinanceReconciliationService::class)->inspect($this->eventId);
    }
    protected function getHeaderActions(): array
    {
        return [
            Action::make('verifyClearance')
                ->label('Review Settlement Evidence')
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() === true)
                ->schema([
                    Select::make('clearance_entry_id')
                        ->label('Unreviewed settlement or adjustment')
                        ->options(fn (): array => DB::table('event_finance_clearance_entries')
                            ->whereNull('independently_verified_at')
                            ->orderByDesc('id')
                            ->limit(100)
                            ->get()
                            ->mapWithKeys(fn ($entry): array => [
                                $entry->id => '#'.$entry->id.' · Event '.$entry->event_id
                                    .' · '.$entry->entry_type.' · '.$entry->currency.' '.$entry->amount
                                    .' · '.$entry->external_reference,
                            ])->all())
                        ->searchable()->required(),
                    Textarea::make('independent_verification_note')
                        ->label('Independent evidence reviewed')
                        ->helperText('Check the actual bank/provider statement outside this system. Do not approve solely from an administrator note.')
                        ->minLength(12)->maxLength(2000)->required(),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $actor = auth()->user();
                    if (! $actor instanceof User || ! $actor->isSuperAdmin()) {
                        abort(403);
                    }
                    app(EventFinanceClearanceService::class)->independentlyVerify(
                        (int) $data['clearance_entry_id'],
                        $actor,
                        (string) $data['independent_verification_note'],
                    );
                    Notification::make()->title('Independent evidence review recorded')->success()->send();
                }),
            Action::make('recordClearance')
                ->label('Record Attested Settlement / Reversal')
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() === true)
                ->schema([
                    Select::make('event_id')->label('Event')
                        ->options(fn (): array => Event::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn () => $this->eventId)->searchable()->required(),
                    Select::make('entry_type')->label('Entry type')
                        ->options([
                            'settlement' => 'Admin-attested organizer settlement',
                            'refund' => 'Refund / repayment adjustment',
                            'chargeback' => 'Chargeback adjustment',
                        ])->required(),
                    TextInput::make('amount')->label('Amount (TZS)')->numeric()->minValue(0.01)->required(),
                    TextInput::make('external_reference')->label('Bank or provider statement reference')
                        ->maxLength(255)->required(),
                    Textarea::make('verification_note')->label('Evidence reviewed and verification notes')
                        ->minLength(12)->maxLength(2000)->required(),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $actor = auth()->user();

                    if (! $actor instanceof User || ! $actor->isSuperAdmin()) {
                        abort(403);
                    }

                    $event = Event::query()->findOrFail((int) $data['event_id']);

                    app(EventFinanceClearanceService::class)->record(
                        $event, $actor, $data['entry_type'],
                        (string) $data['amount'], $data['external_reference'],
                        $data['verification_note'],
                    );

                    $this->eventId = $event->id;
                    Notification::make()->title('Finance attestation recorded')->success()->send();
                }),
        ];
    }

}
