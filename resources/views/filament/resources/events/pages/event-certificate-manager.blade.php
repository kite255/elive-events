<x-filament-panels::page>
    @php($event = $this->event())
    @php($settings = $event->certificateSettings()->first())
    @php($enabled = $settings?->enabled && ! $event->isCancelled())
    @php($templates = $event->certificateTemplates()->where('is_active', true)->orderBy('name')->get(['id', 'name']))
    @php($counts = $event->certificates()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'))
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold">{{ $event->name }}</h2>
            <p class="text-sm text-gray-500">Issue, verify and download event certificates.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-filament::button color="gray" tag="a" :href="\App\Filament\Resources\Events\EventResource::getUrl('workspace', ['record' => $event])">Event workspace</x-filament::button>
            <x-filament::button color="gray" tag="a" :href="\App\Filament\Resources\Events\EventResource::getUrl('certificate-templates', ['record' => $event])">Templates</x-filament::button>
            <x-filament::button color="gray" tag="a" :href="\App\Filament\Resources\Events\EventResource::getUrl('certificate-settings', ['record' => $event])">Settings</x-filament::button>
        </div>
    </div>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        @foreach (['issued' => 'Issued', 'pending' => 'Pending', 'revoked' => 'Revoked'] as $key => $label)
            <x-filament::section>
                <div class="text-sm text-gray-500">{{ $label }}</div>
                <div class="text-2xl font-semibold">{{ number_format($counts[$key] ?? 0) }}</div>
            </x-filament::section>
        @endforeach
    </div>
    @unless ($enabled)
        <p class="text-sm text-gray-500">Issuance is disabled for this event. Existing certificate records remain available.</p>
    @endunless
    <x-filament::section>
        <div class="flex flex-wrap items-center gap-2">
            <x-filament::button :color="$tab === 'records' ? 'primary' : 'gray'" wire:click="switchTab('records')">Certificate records</x-filament::button>
            <x-filament::button :color="$tab === 'eligible' ? 'primary' : 'gray'" wire:click="switchTab('eligible')">Eligible attendees</x-filament::button>
        </div>
        <div class="mt-4 flex flex-wrap items-end gap-3">
            <label class="block flex-1 text-sm">Search
                <input type="search" wire:model.live.debounce.400ms="search" maxlength="100" placeholder="Attendee name or certificate number" class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900">
            </label>
            @if ($tab === 'records')
                <label class="block text-sm">Status
                    <select wire:model.live="status" class="mt-1 block rounded-lg border-gray-300 dark:bg-gray-900">
                        <option value="">All statuses</option><option value="issued">Issued</option><option value="pending">Pending</option><option value="revoked">Revoked</option>
                    </select>
                </label>
            @else
                <label class="block text-sm">Published template
                    <select wire:model.live="templateId" class="mt-1 block rounded-lg border-gray-300 dark:bg-gray-900">
                        <option value="">Select template</option>
                        @foreach ($templates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach
                    </select>
                    @error('templateId') <span class="text-danger-600">{{ $message }}</span> @enderror
                </label>
            @endif
        </div>
        @if ($tab === 'eligible')
            @php($rows = $this->eligible())
            <p class="mt-3 text-sm text-gray-500">{{ $settings?->eligibility_rule === 'manual' ? 'Manual selection: confirm each recipient individually.' : 'Attendees matching the configured eligibility rule without an existing certificate.' }}</p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b border-gray-200 dark:border-gray-700"><th class="p-3">Attendee</th><th class="p-3">Registration status</th><th class="p-3">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $attendee)
                            <tr wire:key="eligible-{{ $attendee->id }}" class="border-b border-gray-100 dark:border-gray-800">
                                <td class="p-3 font-medium">{{ $attendee->full_name }}</td>
                                <td class="p-3">{{ ucfirst(str_replace('_', ' ', $attendee->status)) }}</td>
                                <td class="p-3"><x-filament::button size="sm" :disabled="! $enabled || ! $templateId" wire:click="issue({{ $attendee->id }})" wire:confirm="Issue a certificate to this attendee using the selected template?" wire:loading.attr="disabled" wire:target="issue">Issue</x-filament::button></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-6 text-center text-gray-500">No eligible attendees found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            @php($rows = $this->records())
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b border-gray-200 dark:border-gray-700"><th class="p-3">Attendee</th><th class="p-3">Certificate number</th><th class="p-3">Status</th><th class="p-3">Issued on</th><th class="p-3">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $certificate)
                            <tr wire:key="certificate-{{ $certificate->id }}" class="border-b border-gray-100 dark:border-gray-800">
                                <td class="p-3 font-medium">{{ $certificate->issuance_snapshot['attendee_name'] ?? $certificate->attendee?->full_name }}</td>
                                <td class="p-3">{{ $certificate->certificate_number }}</td>
                                <td class="p-3">{{ ucfirst($certificate->status) }}</td>
                                <td class="p-3 whitespace-nowrap">{{ $certificate->issued_at?->format('d M Y') ?? '—' }}</td>
                                <td class="p-3"><div class="flex flex-wrap gap-2">
                                    @if ($certificate->status === 'issued')
                                        <x-filament::button size="sm" wire:click="download({{ $certificate->id }})" wire:loading.attr="disabled" wire:target="download">Download</x-filament::button>
                                    @endif
                                    @if (in_array($certificate->status, ['issued', 'revoked'], true))
                                        <x-filament::button size="sm" color="gray" tag="a" target="_blank" rel="noopener noreferrer" :href="route('public.certificates.verify', ['token' => $certificate->verification_token])">Verify</x-filament::button>
                                    @endif
                                    @if ($certificate->status === 'pending' && $enabled)
                                        <x-filament::button size="sm" color="gray" wire:click="retry({{ $certificate->id }})" wire:confirm="Retry finalizing this pending certificate?" wire:loading.attr="disabled" wire:target="retry">Retry</x-filament::button>
                                    @endif
                                </div></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-6 text-center text-gray-500">No certificate records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
        <div class="mt-3">{{ $rows->links() }}</div>
    </x-filament::section>
</x-filament-panels::page>
