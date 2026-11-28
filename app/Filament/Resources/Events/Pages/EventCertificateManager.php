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
    public array $selectedCertificates = [];
    public string $deliveryChannel = 'email';
    #[Locked]
    public ?int $viewBatchId = null;

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

    public function updatedSearch(): void { $this->selectedAttendees = []; $this->selectedCertificates = []; $this->resetPage(); }
    public function updatedStatus(): void { $this->selectedCertificates = []; $this->resetPage(); }

    public function switchTab(string $tab): void
    {
        $this->event();
        abort_unless(in_array($tab, ['records', 'eligible', 'batches'], true), 422);
        $this->tab = $tab;
        $this->viewBatchId = null;
        $this->selectedCertificates = [];
        $this->selectedAttendees = [];
        $this->search = '';
        $this->resetPage();
    }

    public function batches()
    {
        $event = $this->event();
        return \Illuminate\Support\Facades\DB::table('event_certificate_batches as batch')
            ->join('event_certificate_templates as template', 'template.id', '=', 'batch.event_certificate_template_id')
            ->where('batch.event_id', $event->id)->where('template.event_id', $event->id)
            ->select('batch.*', 'template.name as template_name')
            ->orderByDesc('batch.id')->paginate(10);
    }

    public function batchCounts(array $ids): array
    {
        $event = $this->event();
        return \Illuminate\Support\Facades\DB::table('event_certificate_batch_items as item')
            ->join('event_certificate_batches as batch', 'batch.id', '=', 'item.event_certificate_batch_id')
            ->where('batch.event_id', $event->id)->whereIn('batch.id', array_slice($ids, 0, 10))
            ->selectRaw('item.event_certificate_batch_id, item.status, count(*) as total')
            ->groupBy('item.event_certificate_batch_id', 'item.status')->get()
            ->groupBy('event_certificate_batch_id')->map(fn ($rows) => $rows->pluck('total', 'status')->all())->all();
    }

    public function viewBatch(int $id): void
    {
        $event = $this->event();
        abort_unless(\Illuminate\Support\Facades\DB::table('event_certificate_batches')->where('event_id', $event->id)->where('id', $id)->exists(), 404);
        $this->viewBatchId = $id;
    }

    public function batchDetails()
    {
        $event = $this->event();
        return \Illuminate\Support\Facades\DB::table('event_certificate_batch_items as item')
            ->join('event_certificate_batches as batch', 'batch.id', '=', 'item.event_certificate_batch_id')
            ->join('attendees as attendee', 'attendee.id', '=', 'item.attendee_id')
            ->where('batch.event_id', $event->id)->where('attendee.event_id', $event->id)
            ->where('batch.id', $this->viewBatchId)
            ->select('item.status', 'item.attempts', 'item.failure_code', 'attendee.full_name')
            ->orderBy('item.id')->limit(100)->get();
    }

    public function retryBatch(int $id): void
    {
        $this->event();
        try {
            $count = app(\App\Services\EventCertificateBatchRetry::class)->retry($this->user(), $this->eventId, $id);
            Notification::make()->title($count.' failed recipients resubmitted')->success()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Retry unavailable')->body('Check batch ownership, eligibility settings and queue configuration.')->danger()->send();
        }
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
            $this->switchTab('batches');
            Notification::make()->title($count.' certificate recipients recorded')
                ->body('Check issuance batches for queued and failed recipients. No messages were sent.')->success()->send();
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

    #[Locked]
    public ?int $revokingId = null;

    public string $revocationReason = '';

    public function beginRevocation(int $id): void
    {
        $certificate = $this->event()->certificates()->whereKey($id)
            ->where('status', EventCertificate::STATUS_ISSUED)->firstOrFail();
        $this->resetValidation();
        $this->revokingId = $certificate->id;
        $this->revocationReason = '';
    }

    public function cancelRevocation(): void
    {
        $this->event();
        $this->revokingId = null;
        $this->revocationReason = '';
        $this->resetValidation();
    }

    public function revokeCertificate(): void
    {
        $this->event();
        abort_unless($this->revokingId, 422);
        $this->revocationReason = trim($this->revocationReason);
        $this->validate(['revocationReason' => ['required', 'string', 'min:10', 'max:1000']]);
        app(\App\Services\EventCertificateRevocation::class)->revoke(
            $this->user(), $this->eventId, $this->revokingId, $this->revocationReason
        );
        $this->cancelRevocation();
        Notification::make()->title('Certificate revoked')
            ->body('Public verification now identifies this certificate as revoked.')->success()->send();
    }

    public function deliveryStatuses(array $ids): array
    {
        $event = $this->event();

        return \Illuminate\Support\Facades\DB::table('event_certificate_deliveries as delivery')
            ->join('event_certificates as certificate', 'certificate.id', '=', 'delivery.event_certificate_id')
            ->where('certificate.event_id', $event->id)
            ->whereIn('certificate.id', array_slice($ids, 0, 15))
            ->select('delivery.event_certificate_id', 'delivery.channel', 'delivery.status')
            ->get()->groupBy('event_certificate_id')
            ->map(fn ($rows) => $rows->pluck('status', 'channel')->all())->all();
    }

    public function selectDeliveryPage(): void
    {
        $this->selectedCertificates = $this->records()->getCollection()
            ->where('status', EventCertificate::STATUS_ISSUED)
            ->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function clearDeliverySelection(): void
    {
        $this->event();
        $this->selectedCertificates = [];
    }

    public function sendSelectedCertificates(): void
    {
        $event = $this->event();
        $this->validate([
            'deliveryChannel' => ['required', 'in:email,sms'],
            'selectedCertificates' => ['required', 'array', 'min:1', 'max:100'],
            'selectedCertificates.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);
        $ids = $event->certificates()->whereIn('id', $this->selectedCertificates)
            ->where('status', EventCertificate::STATUS_ISSUED)->pluck('id');
        if ($ids->count() !== count($this->selectedCertificates)) {
            Notification::make()->title('Selection contains unavailable certificates')->danger()->send();
            return;
        }

        $queued = 0;
        $blocked = 0;
        foreach ($ids as $id) {
            try {
                app(\App\Services\EventCertificateDeliveryQueue::class)->queue($this->user(), $this->eventId, (int) $id, $this->deliveryChannel);
                $queued++;
            } catch (Throwable $exception) {
                $blocked++;
            }
        }
        $this->selectedCertificates = [];
        Notification::make()->title($queued.' queued; '.$blocked.' not confirmed')
            ->body('Review channel statuses. Existing delivery records are never automatically resent.')
            ->color($blocked ? 'warning' : 'success')->send();
    }

    public function sendCertificate(int $id, string $channel): void
    {
        $this->event();
        try {
            app(\App\Services\EventCertificateDeliveryQueue::class)->queue($this->user(), $this->eventId, $id, $channel);
            Notification::make()->title('Certificate delivery queued')
                ->body('Provider acceptance and recipient delivery are not yet confirmed.')->success()->send();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()->title('Delivery unavailable')
                ->body(collect($exception->errors())->flatten()->first())->danger()->send();
        } catch (Throwable $exception) {
            Notification::make()->title('Delivery submission could not be confirmed')
                ->body('Review the certificate status and recipient contact before attempting another submission.')->danger()->send();
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
