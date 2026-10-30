<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class StagingPayoutSimulation extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-beaker';
    protected static ?string $navigationLabel = 'Payout Simulation';
    protected static ?string $title = 'Payout Simulation — TEST ONLY';
    protected static string|UnitEnum|null $navigationGroup = 'Finance';
    protected string $view = 'filament.pages.staging-payout-simulation';

    private const EVENT_ID = 10;
    private const START_BALANCE = 100000;

    public string $amount = '60000';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! app()->environment('staging') || ! $user instanceof User) {
            return false;
        }
        $event = Event::query()->find(self::EVENT_ID);
        return $event && $event->organization_id === 7
            && $event->slug === 'elive-payout-testing-simulated'
            && ($user->isSuperAdmin() || $user->assignedTicketingEvents()->where('events.id', $event->id)->exists());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getRequestsProperty()
    {
        abort_unless(static::canAccess(), 403);
        return DB::table('staging_payout_simulations')
            ->where('event_id', self::EVENT_ID)
            ->orderByDesc('id')
            ->get();
    }

    public function getAvailableProperty(): float
    {
        abort_unless(static::canAccess(), 403);
        $reserved = (float) DB::table('staging_payout_simulations')
            ->where('event_id', self::EVENT_ID)
            ->whereIn('status', ['requested', 'approved', 'simulated_transferred'])
            ->sum('amount');
        return max(0, self::START_BALANCE - $reserved);
    }

    public function submitSimulation(): void
    {
        abort_unless(static::canAccess(), 403);
        $user = auth()->user();
        if (! is_numeric($this->amount) || (float) $this->amount <= 0
            || round((float) $this->amount, 2) !== (float) $this->amount) {
            throw ValidationException::withMessages(['amount' => 'Enter a valid positive amount.']);
        }
        $amount = (float) $this->amount;
        DB::transaction(function () use ($user, $amount): void {
            $event = Event::query()->whereKey(self::EVENT_ID)->lockForUpdate()->firstOrFail();
            abort_unless($event->slug === 'elive-payout-testing-simulated' && $event->organization_id === 7, 403);
            $reserved = (float) DB::table('staging_payout_simulations')
                ->where('event_id', self::EVENT_ID)
                ->whereIn('status', ['requested','approved','simulated_transferred'])
                ->sum('amount');
            if ($amount > self::START_BALANCE - $reserved) {
                throw ValidationException::withMessages(['amount' => 'Amount exceeds available simulated balance.']);
            }
            DB::table('staging_payout_simulations')->insert([
                'event_id' => self::EVENT_ID,
                'requested_by' => $user->id,
                'amount' => $amount,
                'status' => 'requested',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
        Notification::make()->title('Simulated request recorded — no actual payout created')->success()->send();
    }
}
