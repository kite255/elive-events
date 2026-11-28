<?php

namespace App\Filament\Resources\Events\Pages;

use App\Exports\EventFinanceStatement;
use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\User;
use App\Services\EventCertificateAccess;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

class EventCertificateReport extends Page
{
    use WithPagination;

    protected static string $resource = EventResource::class;
    protected string $view = 'filament.resources.events.pages.event-certificate-report';
    protected static ?string $title = 'Certificate reports';

    #[Locked]
    public int $eventId;
    public string $status = '';
    public string $search = '';
    public string $templateFilter = '';
    public string $issuedFrom = '';
    public string $issuedTo = '';
    public string $deliveryFilter = '';

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

    public function updatedStatus(): void { $this->resetPage(); }
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedTemplateFilter(): void { $this->resetPage(); }
    public function updatedIssuedFrom(): void { $this->resetPage(); }
    public function updatedIssuedTo(): void { $this->resetPage(); }
    public function updatedDeliveryFilter(): void { $this->resetPage(); }

    public function summary(): array
    {
        $event = $this->event();
        $settings = $event->certificateSettings()->first();
        $outstanding = null;
        if ($settings?->enabled && ! $event->isCancelled() && $settings->eligibility_rule !== 'manual') {
            $outstanding = app(EventCertificateAccess::class)->eligibleAttendees(auth()->user(), $event->id)
                ->whereDoesntHave('certificates')->count();
        }

        return [
            'certificates' => $event->certificates()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
            'outstanding' => $outstanding,
            'delivery' => DB::table('event_certificate_deliveries as delivery')
                ->join('event_certificates as certificate', 'certificate.id', '=', 'delivery.event_certificate_id')
                ->where('certificate.event_id', $event->id)
                ->selectRaw('delivery.channel, delivery.status, count(*) as total')
                ->groupBy('delivery.channel', 'delivery.status')->get()
                ->groupBy('channel')->map(fn ($rows) => $rows->pluck('total', 'status')->all())->all(),
        ];
    }

    private function query()
    {
        $query = $this->event()->certificates()->with('template');
        if (in_array($this->status, ['issued', 'pending', 'revoked'], true)) {
            $query->where('status', $this->status);
        }
        $search = mb_substr(trim($this->search), 0, 100);
        if ($search !== '') {
            $query->where('certificate_number', 'like', '%'.$search.'%');
        }
        if ($this->templateFilter !== '') {
            $query->whereIn('event_certificate_template_id', $this->event()->certificateTemplates()
                ->whereKey(ctype_digit($this->templateFilter) ? (int) $this->templateFilter : 0)->select('id'));
        }
        foreach (['issuedFrom' => '>=', 'issuedTo' => '<='] as $field => $operator) {
            $value = $this->{$field};
            if ($value !== '') {
                $valid = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts)
                    && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
                if (! $valid) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereDate('issued_at', $operator, $value);
                }
            }
        }
        if ($this->deliveryFilter !== '') {
            if (! in_array($this->deliveryFilter, ['email', 'sms'], true)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', DB::table('event_certificate_deliveries')->where('channel', $this->deliveryFilter)->select('event_certificate_id'));
            }
        }
        return $query->orderByDesc('id');
    }

    public function records()
    {
        return $this->query()->paginate(20);
    }

    private function exportRows(int $limit): array
    {
        $event = $this->event();
        $summary = $this->summary();
        $rows = [['eLive Events - Certificate report'], ['Event', $event->name],
            ['Generated', now()->toDateTimeString()], ['Event-wide totals'],
            ['Issued', $summary['certificates']['issued'] ?? 0], ['Pending', $summary['certificates']['pending'] ?? 0],
            ['Revoked', $summary['certificates']['revoked'] ?? 0], ['Outstanding', $summary['outstanding'] ?? 'Not applicable'],
            [], ['Channel', 'Status', 'Total']];
        foreach ($summary['delivery'] as $channel => $statuses) {
            foreach ($statuses as $status => $total) {
                $rows[] = [$channel, $status, $total];
            }
        }
        $rows[] = ['Submitted means provider acceptance, not confirmed delivery.'];
        $rows[] = ['Filtered records (maximum '.$limit.')', 'Matching records', $this->query()->count()];
        $rows[] = ['Attendee', 'Certificate number', 'Template', 'Status', 'Issued on', 'Revoked on'];
        foreach ($this->query()->limit($limit)->get() as $certificate) {
            $rows[] = [$certificate->issuance_snapshot['attendee_name'] ?? '', $certificate->certificate_number,
                $certificate->issuance_snapshot['template_name'] ?? $certificate->template?->name ?? '',
                $certificate->status, $certificate->issued_at?->toDateTimeString() ?? '', $certificate->revoked_at?->toDateTimeString() ?? ''];
        }
        return $rows;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('excel')->label('Export Excel')->action(fn () => Excel::download(new EventFinanceStatement($this->exportRows(5000)), 'event-'.$this->eventId.'-certificates.xlsx')),
            Action::make('pdf')->label('Export PDF')->action(fn () => app(\App\Services\OperationalReportPdf::class)->download($this->exportRows(500), 'event-'.$this->eventId.'-certificates.pdf')),
        ];
    }
}
