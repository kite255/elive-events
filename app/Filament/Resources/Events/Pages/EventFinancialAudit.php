<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\User;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

class EventFinancialAudit extends Page
{
    use WithPagination;

    protected static string $resource = EventResource::class;
    protected string $view = 'filament.resources.events.pages.event-financial-audit';

    #[Locked]
    public int $eventId;

    public string $reference = '';
    public string $action = '';
    public string $from = '';
    public string $to = '';

    public static function canViewAudit(Event $event, ?User $user): bool
    {
        return $user instanceof User && $user->isSuperAdmin()
            && ! $user->is_gate_staff && EventFinanceWorkspace::canViewFinance($event, $user);
    }

    public function mount(int|string $record): void
    {
        abort_unless(ctype_digit((string) $record), 404);
        $this->eventId = (int) $record;
        $this->event();
    }

    public function event(): Event
    {
        $event = Event::query()->findOrFail($this->eventId);
        abort_unless(static::canViewAudit($event, auth()->user()), 403);

        return $event;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Financial audit — '.$this->event()->name;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['reference', 'action', 'from', 'to'], true)) {
            $this->resetPage('auditPage');
        }
    }

    public function auditRows(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $event = $this->event();
        $this->validate([
            'reference' => ['string', 'max:255'],
            'action' => ['nullable', 'in:requested,approved,rejected,paid,receipt_confirmed'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return DB::table('event_payout_audits as audit')
            ->join('event_payout_requests as payout', 'payout.id', '=', 'audit.event_payout_request_id')
            ->where('payout.event_id', $event->id)
            ->when($this->reference !== '', fn ($query) => $query->where('payout.reference', 'like', '%'.$this->reference.'%'))
            ->when($this->action !== '', fn ($query) => $query->where('audit.action', $this->action))
            ->when($this->from !== '', fn ($query) => $query->whereDate('audit.created_at', '>=', $this->from))
            ->when($this->to !== '', fn ($query) => $query->whereDate('audit.created_at', '<=', $this->to))
            ->orderByDesc('audit.id')
            ->paginate(25, ['audit.id', 'payout.reference', 'payout.currency', 'payout.amount', 'audit.action', 'audit.from_status', 'audit.to_status', 'audit.actor_id', 'audit.created_at'], 'auditPage');
    }

    public function clearanceRows(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return DB::table('event_finance_clearance_entries')
            ->where('event_id', $this->event()->id)
            ->orderByDesc('id')
            ->paginate(25, ['id', 'entry_type', 'currency', 'amount', 'recorded_by', 'created_at', 'independently_verified_by', 'independently_verified_at', 'self_attested_by', 'self_attested_at', 'voided_by', 'voided_at'], 'clearancePage');
    }
}
