<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\EventCertificateAudit;
use App\Models\User;
use App\Services\EventCertificateAccess;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

class EventCertificateAuditHistory extends Page
{
    use WithPagination;

    protected static string $resource = EventResource::class;
    protected string $view = 'filament.resources.events.pages.event-certificate-audit-history';
    protected static ?string $title = 'Certificate audit history';

    #[Locked]
    public int $eventId;
    public string $actionFilter = '';
    public string $search = '';

    public function mount(int|string $record): void
    {
        abort_unless(ctype_digit((string) $record), 404);
        $this->eventId = (int) $record;
        $this->event();
    }

    public function event(): Event
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return app(EventCertificateAccess::class)->authorize($user, $this->eventId);
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedActionFilter(): void { $this->resetPage(); }

    public function records()
    {
        $event = $this->event();
        $query = EventCertificateAudit::query()
            ->whereHas('certificate', fn ($query) => $query->where('event_id', $event->id))
            ->with(['certificate', 'actor:id,name']);
        if (in_array($this->actionFilter, ['prepared', 'issued', 'revoked'], true)) {
            $query->where('action', $this->actionFilter);
        }
        $search = mb_substr(trim($this->search), 0, 100);
        if ($search !== '') {
            $query->whereHas('certificate', fn ($query) => $query->where('certificate_number', 'like', '%'.$search.'%'));
        }

        return $query->latest('id')->paginate(25);
    }
}
