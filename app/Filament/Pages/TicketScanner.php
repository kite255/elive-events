<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;
use UnitEnum;

class TicketScanner extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup =
        'Check-in & Attendance';

    protected static ?string $navigationLabel =
        'Ticket Scanner';

    protected static ?string $title =
        'Ticket Scanner';

    protected static ?int $navigationSort = 2;

    protected string $view =
        'filament.pages.ticket-scanner';

    #[Locked]
    public string $attendanceTab = 'scan';

    public string $attendanceEventId = '';

    public string $attendanceSearch = '';

    public function showAttendance(string $tab): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(in_array($tab, ['scan', 'checked_in', 'pending'], true), 404);

        $this->attendanceTab = $tab;
        $this->resetPage('attendancePage');

        if ($tab !== 'scan' && $this->attendanceEventId === '') {
            $this->attendanceEventId = (string) $this->attendanceEvents->first()?->getKey();
        }
    }

    public function updatedAttendanceEventId(): void
    {
        $this->resetPage('attendancePage');
    }

    public function updatedAttendanceSearch(): void
    {
        $this->resetPage('attendancePage');
    }

    #[Computed]
    public function attendanceEvents(): Collection
    {
        $user = Auth::user();
        abort_unless($user instanceof User && static::canAccess(), 403);

        return Event::query()
            ->accessibleBy($user)
            ->whereHas('tickets')
            ->orderByRaw('starts_at IS NULL')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get(['id', 'name', 'organization_id'])
            ->filter(fn (Event $event): bool => $event->canBeCheckedInBy($user));
    }

    #[Computed]
    public function attendanceTickets(): ?LengthAwarePaginator
    {
        $event = $this->attendanceEvents->firstWhere('id', $this->attendanceEventId);

        if (! $event || $this->attendanceTab === 'scan') {
            return null;
        }

        $query = $event->tickets()
            ->with('ticketType:id,name')
            ->select(['id', 'ticket_number', 'holder_name', 'ticket_type_id', 'used_at']);

        if ($this->attendanceTab === 'checked_in') {
            $query->whereNotNull('used_at')->orderByDesc('used_at');
        } else {
            $query->where('status', Ticket::STATUS_ISSUED)->whereNull('used_at');
        }

        $search = trim(mb_substr($this->attendanceSearch, 0, 120));

        if ($search !== '') {
            $query->where(fn ($tickets) => $tickets
                ->whereLike('holder_name', '%'.$search.'%')
                ->orWhereLike('ticket_number', '%'.$search.'%'));
        }

        return $query->orderByDesc('id')->paginate(20, pageName: 'attendancePage');
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return false;
        }

        return true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
