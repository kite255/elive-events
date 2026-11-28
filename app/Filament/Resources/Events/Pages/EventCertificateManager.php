<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\EventCertificate;
use App\Models\User;
use App\Services\EventCertificateAccess;
use App\Services\EventCertificateIssuance;
use App\Services\EventCertificateFinalization;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EventCertificateManager extends Page
{
    use WithPagination;

    protected static string $resource = EventResource::class;
    protected string $view = 'filament.resources.events.pages.event-certificate-manager';
    protected static ?string $title = 'Certificates';

    #[Locked]
    public int $eventId;
    public string $tab = 'records';
    public string $search = '';
    public string $status = '';
    public ?int $templateId = null;
    public array $selectedAttendees = [];

    public function mount(int|string $record): void
    {
        abort_unless(ctype_digit((string) $record), 404);
        $this->eventId = (int) $record;
        $this->event();
    }

    protected function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function event(): Event
    {
        return app(EventCertificateAccess::class)->authorize($this->user(), $this->eventId);
    }

    public function updatedSearch(): void { $this->selectedAttendees = []; $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }

    public function switchTab(string $tab): void
    {
        $this->event();
        abort_unless(in_array($tab, ['records', 'eligible'], true), 422);
        $this->tab = $tab;
        $this->selectedAttendees = [];
        $this->search = '';
        $this->resetPage();
    }

    public function records()
    {
        $query = $this->event()->certificates()->with('attendee');
        if (in_array($this->status, ['pending', 'issued', 'revoked'], true)) {
            $query->where('status', $this->status);
        }
        $search = mb_substr(trim($this->search), 0, 100);
        if ($search !== '') {
            $query->where(fn ($q) => $q->where('certificate_number', 'like', '%'.$search.'%')
                ->orWhereHas('attendee', fn ($a) => $a->where('full_name', 'like', '%'.$search.'%')));
        }

        return $query->latest('id')->paginate(15);
    }

    public function eligible()
    {
        $event = $this->event();
        $settings = $event->certificateSettings()->first();
        $query = $event->attendees()->getQuery()->whereDoesntHave('certificates');
        if (! $settings?->enabled || $event->isCancelled()) {
            $query->whereRaw('1 = 0');
        } elseif ($settings->eligibility_rule === 'manual') {
            $query->whereIn('status', ['registered', 'approved', 'confirmed', 'checked_in']);
        } else {
            $query = app(EventCertificateAccess::class)->eligibleAttendees($this->user(), $this->eventId)
                ->whereDoesntHave('certificates');
        }
        $search = mb_substr(trim($this->search), 0, 100);
        if ($search !== '') {
            $query->where('full_name', 'like', '%'.$search.'%');
        }

        return $query->orderBy('full_name')->orderBy('id')->paginate(15);
    }

    public function selectVisible(): void
    {
        $this->selectedAttendees = $this->eligible()->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function clearSelection(): void
    {
        $this->event();
        $this->selectedAttendees = [];
    }

    public function issueSelected(): void
    {
        $this->event();
        $this->validate([
            'templateId' => ['required', 'integer', 'min:1'],
            'selectedAttendees' => ['required', 'array', 'min:1', 'max:100'],
            'selectedAttendees.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);
        try {
            $count = app(\App\Services\EventCertificateBulkIssuance::class)->queue(
                $this->user(), $this->eventId, $this->templateId, $this->selectedAttendees
            );
            $this->selectedAttendees = [];
            Notification::make()->title($count.' certificate jobs submitted')
                ->body('Check certificate records as processing completes. No messages were sent.')->success()->send();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()->title('Selection is no longer valid')
                ->body(collect($exception->errors())->flatten()->first())->danger()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Submission did not complete')
                ->body('Some jobs may have been queued. Review records before submitting again.')->danger()->send();
        }
    }

    public function issue(int $attendeeId): void
    {
        $this->event();
        $this->validate(['templateId' => ['required', 'integer', 'min:1']]);
        try {
            $certificate = app(EventCertificateIssuance::class)->prepare($this->user(), $this->eventId, $attendeeId, $this->templateId, true);
            app(EventCertificateFinalization::class)->finalize($this->user(), $this->eventId, $certificate->id, true);
            Notification::make()->title('Certificate issued')->success()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Issuance did not complete')->body('Check certificate records for a pending certificate before retrying.')->danger()->send();
        }
        $this->resetPage();
    }

    public function retry(int $id): void
    {
        $this->event();
        try {
            app(EventCertificateFinalization::class)->finalize($this->user(), $this->eventId, $id, true);
            Notification::make()->title('Certificate issued')->success()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Certificate could not be finalized')->body('Review eligibility, saved details and certificate assets.')->danger()->send();
        }
    }

    public function download(int $id): StreamedResponse
    {
        $certificate = $this->event()->certificates()->whereKey($id)
            ->where('status', EventCertificate::STATUS_ISSUED)->firstOrFail();
        $path = $certificate->pdf_path;
        $prefix = 'certificates/event-'.$this->eventId.'/certificate-'.$certificate->id.'/';
        $disk = Storage::disk('local');
        abort_unless(is_string($path) && str_starts_with($path, $prefix) && ! str_contains($path, '..') && $disk->exists($path), 404);
        $audit = $certificate->audits()->where('action', 'issued')->latest('id')->first();
        $hash = $audit?->metadata['pdf_sha256'] ?? '';
        $bytes = $disk->get($path);
        abort_unless(is_string($bytes) && hash_equals($hash, hash('sha256', $bytes)), 409, 'Certificate file integrity check failed.');

        return response()->streamDownload(fn () => print($bytes), $certificate->certificate_number.'.pdf', [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
