<x-filament-panels::page>
    @php($event = $this->event())
    @php($summary = $this->summary())
    @php($rows = $this->records())
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">{{ $event->name }}</h2>
        <x-filament::button color="gray" tag="a" :href="\App\Filament\Resources\Events\EventResource::getUrl('certificates', ['record' => $event])">Certificate management</x-filament::button>
    </div>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach (['issued' => 'Issued', 'pending' => 'Pending', 'revoked' => 'Revoked', 'outstanding' => 'Outstanding'] as $key => $label)
            <x-filament::section>
                <div class="text-sm text-gray-500">{{ $label }}</div>
                <div class="text-xl font-semibold">{{ $key === 'outstanding' ? ($summary['outstanding'] === null ? 'N/A' : number_format($summary['outstanding'])) : number_format($summary['certificates'][$key] ?? 0) }}</div>
            </x-filament::section>
        @endforeach
    </div>
    <x-filament::section>
        <x-slot name="heading">Delivery summary</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-2">Channel</th>@foreach (['queued','processing','submitted','delivered','failed','uncertain'] as $statusKey)<th class="p-2">{{ ucfirst($statusKey) }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach (['email' => 'Email', 'sms' => 'SMS'] as $channel => $label)
                    <tr class="border-t border-gray-200 dark:border-gray-700"><td class="p-2 font-medium">{{ $label }}</td>@foreach (['queued','processing','submitted','delivered','failed','uncertain'] as $statusKey)<td class="p-2">{{ number_format($summary['delivery'][$channel][$statusKey] ?? 0) }}</td>@endforeach</tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-gray-500">Submitted means provider acceptance, not confirmed delivery. Delivery status synchronization is not currently enabled.</p>
    </x-filament::section>
    <x-filament::section>
        <x-slot name="heading">Certificate records</x-slot>
        <div class="flex flex-wrap items-end gap-3">
            <label class="flex-1 text-sm">Certificate number<input type="search" wire:model.live.debounce.400ms="search" maxlength="100" class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-900"></label>
            <label class="text-sm">Status<select wire:model.live="status" class="mt-1 block rounded-lg border-gray-300 dark:bg-gray-900"><option value="">All statuses</option><option value="issued">Issued</option><option value="pending">Pending</option><option value="revoked">Revoked</option></select></label>
            <x-filament::button color="gray" wire:click="$refresh">Refresh</x-filament::button>
        </div>
        <p class="mt-2 text-xs text-gray-500">Event-wide summary above; filters apply to records and exported detail. Excel exports up to 5,000 records, PDF up to 500.</p>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-2">Attendee</th><th class="p-2">Number</th><th class="p-2">Template</th><th class="p-2">Status</th><th class="p-2">Issued</th><th class="p-2">Revoked</th></tr></thead>
                <tbody>
                @forelse ($rows as $certificate)
                    <tr wire:key="report-{{ $certificate->id }}" class="border-t border-gray-200 dark:border-gray-700">
                        <td class="p-2">{{ $certificate->issuance_snapshot['attendee_name'] ?? '' }}</td><td class="p-2">{{ $certificate->certificate_number }}</td><td class="p-2">{{ $certificate->issuance_snapshot['template_name'] ?? $certificate->template?->name ?? '' }}</td><td class="p-2">{{ ucfirst($certificate->status) }}</td><td class="p-2">{{ $certificate->issued_at?->format('d M Y') ?? '—' }}</td><td class="p-2">{{ $certificate->revoked_at?->format('d M Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-gray-500">No matching certificate records.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $rows->links() }}
    </x-filament::section>
</x-filament-panels::page>
