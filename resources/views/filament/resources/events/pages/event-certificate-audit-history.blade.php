<x-filament-panels::page>
    @php($event = $this->event())
    @php($rows = $this->records())
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-semibold">{{ $event->name }}</h2>
        <div class="flex flex-wrap gap-2">
            <x-filament::button color="gray" tag="a" :href="\App\Filament\Resources\Events\EventResource::getUrl('certificates', ['record' => $event])">Certificate management</x-filament::button>
            <x-filament::button color="gray" tag="a" :href="\App\Filament\Resources\Events\EventResource::getUrl('certificate-report', ['record' => $event])">Reports</x-filament::button>
        </div>
    </div>
    <x-filament::section>
        <div class="flex flex-wrap items-end gap-3">
            <label class="flex-1 text-sm">Certificate number<input type="search" wire:model.live.debounce.400ms="search" maxlength="100" class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-900"></label>
            <label class="text-sm">Action<select wire:model.live="actionFilter" class="mt-1 block rounded-lg border-gray-300 dark:bg-gray-900"><option value="">All actions</option><option value="prepared">Prepared</option><option value="issued">Issued</option><option value="revoked">Revoked</option></select></label>
            <x-filament::button color="gray" wire:click="$refresh">Refresh</x-filament::button>
        </div>
        <p class="mt-2 text-xs text-gray-500">Read-only certificate lifecycle history. Communication submission statuses are available in certificate records and reports.</p>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-2">Date and time</th><th class="p-2">Certificate</th><th class="p-2">Attendee</th><th class="p-2">Action</th><th class="p-2">Responsible user</th><th class="p-2">Details</th></tr></thead>
                <tbody>
                @forelse ($rows as $audit)
                    <tr wire:key="audit-{{ $audit->id }}" class="border-t border-gray-200 dark:border-gray-700">
                        <td class="p-2 whitespace-nowrap">{{ $audit->created_at?->format('d M Y H:i') }}</td>
                        <td class="p-2">{{ $audit->certificate?->certificate_number }}</td>
                        <td class="p-2">{{ $audit->certificate?->issuance_snapshot['attendee_name'] ?? '' }}</td>
                        <td class="p-2">{{ ucfirst($audit->action) }}</td>
                        <td class="p-2">{{ $audit->actor?->name ?? 'Unavailable' }}</td>
                        <td class="p-2 break-words">{{ $audit->action === 'revoked' ? ($audit->metadata['reason'] ?? '—') : ($audit->metadata['eligibility_rule'] ?? '—') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-gray-500">No matching audit records.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $rows->links() }}
    </x-filament::section>
</x-filament-panels::page>
