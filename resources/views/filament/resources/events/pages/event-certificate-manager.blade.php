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
    @if ($revokingId)
        <x-filament::section>
            <x-slot name="heading">Revoke certificate</x-slot>
            @php($revoking = $event->certificates()->whereKey($revokingId)->where('status', 'issued')->first())
            @if ($revoking)
                <p class="text-sm font-medium">{{ $revoking->issuance_snapshot['attendee_name'] ?? '' }} · {{ $revoking->certificate_number }}</p>
                <p class="mt-2 text-sm text-gray-500">This permanently invalidates public verification. The original PDF and audit records are retained.</p>
                <form wire:submit="revokeCertificate" class="mt-3 space-y-3">
                    <label class="block text-sm">Reason for revocation
                        <textarea wire:model="revocationReason" required minlength="10" maxlength="1000" rows="3" class="mt-1 w-full rounded-lg border-gray-300 dark:bg-gray-900"></textarea>
                        @error('revocationReason') <span class="text-danger-600">{{ $message }}</span> @enderror
                    </label>
                    <div class="flex justify-end gap-2">
                        <x-filament::button type="button" color="gray" wire:click="cancelRevocation">Cancel</x-filament::button>
                        <x-filament::button type="submit" color="danger" wire:confirm="Permanently revoke this certificate?" wire:loading.attr="disabled" wire:target="revokeCertificate">Revoke certificate</x-filament::button>
                    </div>
                </form>
            @else
                <p class="text-sm text-gray-500">This certificate is no longer available for revocation.</p>
                <x-filament::button color="gray" wire:click="cancelRevocation">Close</x-filament::button>
            @endif
        </x-filament::section>
    @endif
    @unless ($enabled)
        <p class="text-sm text-gray-500">Issuance is disabled for this event. Existing certificate records remain available.</p>
    @endunless
    <x-filament::section>
        <div class="flex flex-wrap items-center gap-2">
            <x-filament::button :color="$tab === 'records' ? 'primary' : 'gray'" wire:click="switchTab('records')">Certificate records</x-filament::button>
            <x-filament::button :color="$tab === 'eligible' ? 'primary' : 'gray'" wire:click="switchTab('eligible')">Eligible attendees</x-filament::button>
            <x-filament::button :color="$tab === 'batches' ? 'primary' : 'gray'" wire:click="switchTab('batches')">Issuance batches</x-filament::button>
        </div>
        @if ($tab === 'batches')
            <div class="mt-4 space-y-3">
                @php($batches = $this->batches())
                @php($batchCounts = $this->batchCounts($batches->pluck('id')->all()))
                <div class="flex justify-end"><x-filament::button size="sm" color="gray" wire:click="$refresh">Refresh</x-filament::button></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead><tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="p-2">Batch / Template</th><th class="p-2">Total</th><th class="p-2">Queued</th><th class="p-2">Processing</th><th class="p-2">Issued</th><th class="p-2">Failed</th><th class="p-2">Skipped</th><th class="p-2">Actions</th>
                        </tr></thead>
                        <tbody>
                            @forelse ($batches as $batch)
                                @php($counts = $batchCounts[$batch->id] ?? [])
                                <tr wire:key="batch-{{ $batch->id }}" class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="p-2"><div class="font-medium">#{{ $batch->id }} · {{ $batch->template_name }}</div><div class="text-xs text-gray-500">{{ $batch->created_at }}</div></td>
                                    <td class="p-2">{{ array_sum($counts) }}</td>
                                    @foreach (['queued', 'processing', 'issued', 'failed', 'skipped'] as $key)
                                        <td class="p-2">{{ $counts[$key] ?? 0 }}</td>
                                    @endforeach
                                    <td class="p-2"><div class="flex flex-wrap gap-2">
                                        <x-filament::button size="sm" color="gray" wire:click="viewBatch({{ $batch->id }})">Details</x-filament::button>
                                        @if ($enabled && ($counts['failed'] ?? 0) && (int) $batch->actor_id === (int) auth()->id())
                                            <x-filament::button size="sm" wire:click="retryBatch({{ $batch->id }})" wire:confirm="Retry failed recipients in this batch? No messages will be sent." wire:loading.attr="disabled" wire:target="retryBatch">Retry failed</x-filament::button>
                                        @endif
                                    </div></td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="p-6 text-center text-gray-500">No issuance batches found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $batches->links() }}
                @if ($viewBatchId)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <h3 class="font-semibold">Batch #{{ $viewBatchId }} recipients</h3>
                        <div class="mt-2 max-h-96 overflow-auto">
                            <table class="w-full text-left text-sm">
                                <thead><tr><th class="p-2">Attendee</th><th class="p-2">Status</th><th class="p-2">Attempts</th><th class="p-2">Failure</th></tr></thead>
                                <tbody>
                                    @foreach ($this->batchDetails() as $item)
                                        <tr class="border-t border-gray-100 dark:border-gray-800"><td class="p-2">{{ $item->full_name }}</td><td class="p-2">{{ ucfirst($item->status) }}</td><td class="p-2">{{ $item->attempts }}</td><td class="p-2">{{ $item->failure_code ? ucfirst(str_replace('_', ' ', $item->failure_code)) : '—' }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @else
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
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium">{{ count($selectedAttendees) }} selected</span>
                <x-filament::button size="sm" color="gray" wire:click="selectVisible" :disabled="! $enabled">Select this page</x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="clearSelection">Clear</x-filament::button>
                <x-filament::button size="sm" :disabled="! $enabled || ! $templateId || ! count($selectedAttendees) || count($selectedAttendees) > 100" wire:click="issueSelected" wire:confirm="Queue {{ count($selectedAttendees) }} certificates using the selected template? No messages will be sent." wire:loading.attr="disabled" wire:target="issueSelected">Issue selected</x-filament::button>
            </div>
            @error('selectedAttendees') <p class="mt-2 text-sm text-danger-600">{{ $message }}</p> @enderror
            @error('selectedAttendees.*') <p class="mt-2 text-sm text-danger-600">{{ $message }}</p> @enderror
            <p class="mt-2 text-xs text-gray-500">Select up to 100 attendees. Selection persists across pages; selecting this page replaces the previous selection.</p>
            <p class="mt-3 text-sm text-gray-500">{{ $settings?->eligibility_rule === 'manual' ? 'Manual selection: confirm each recipient individually.' : 'Attendees matching the configured eligibility rule without an existing certificate.' }}</p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b border-gray-200 dark:border-gray-700"><th class="p-3">Select</th><th class="p-3">Attendee</th><th class="p-3">Registration status</th><th class="p-3">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $attendee)
                            <tr wire:key="eligible-{{ $attendee->id }}" class="border-b border-gray-100 dark:border-gray-800">
                                <td class="p-3"><input type="checkbox" wire:model.live="selectedAttendees" value="{{ $attendee->id }}" aria-label="Select {{ $attendee->full_name }}" class="rounded border-gray-300"></td>
                                <td class="p-3 font-medium">{{ $attendee->full_name }}</td>
                                <td class="p-3">{{ ucfirst(str_replace('_', ' ', $attendee->status)) }}</td>
                                <td class="p-3"><x-filament::button size="sm" :disabled="! $enabled || ! $templateId" wire:click="issue({{ $attendee->id }})" wire:confirm="Issue a certificate to this attendee using the selected template?" wire:loading.attr="disabled" wire:target="issue">Issue</x-filament::button></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-6 text-center text-gray-500">No eligible attendees found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            @php($rows = $this->records())
            @php($deliveryStatuses = $this->deliveryStatuses($rows->pluck('id')->all()))
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium">{{ count($selectedCertificates) }} selected</span>
                <x-filament::button size="sm" color="gray" wire:click="selectDeliveryPage">Select issued on page</x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="clearDeliverySelection">Clear</x-filament::button>
                <select wire:model.live="deliveryChannel" aria-label="Delivery channel" class="rounded-lg border-gray-300 text-sm dark:bg-gray-900">
                    <option value="email">Email</option><option value="sms">SMS</option>
                </select>
                <x-filament::button size="sm" :disabled="! count($selectedCertificates) || count($selectedCertificates) > 100" wire:click="sendSelectedCertificates" wire:confirm="Queue {{ count($selectedCertificates) }} selected certificates by {{ strtoupper($deliveryChannel) }}? Existing delivery records will not be resent." wire:loading.attr="disabled" wire:target="sendSelectedCertificates">Send selected</x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="$refresh">Refresh</x-filament::button>
            </div>
            @error('selectedCertificates') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
            @error('selectedCertificates.*') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
            @error('deliveryChannel') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
            <p class="mt-2 text-xs text-gray-500">Select up to 100 issued certificates. Existing channel records are not resent. Submitted does not mean delivered.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr class="border-b border-gray-200 dark:border-gray-700"><th class="p-3">Select</th><th class="p-3">Attendee</th><th class="p-3">Certificate number</th><th class="p-3">Status</th><th class="p-3">Issued on</th><th class="p-3">Delivery</th><th class="p-3">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $certificate)
                            <tr wire:key="certificate-{{ $certificate->id }}" class="border-b border-gray-100 dark:border-gray-800">
                                <td class="p-3">@if ($certificate->status === 'issued')<input type="checkbox" wire:model.live="selectedCertificates" value="{{ $certificate->id }}" aria-label="Select certificate {{ $certificate->certificate_number }}" class="rounded border-gray-300">@endif</td>
                                <td class="p-3 font-medium">{{ $certificate->issuance_snapshot['attendee_name'] ?? $certificate->attendee?->full_name }}</td>
                                <td class="p-3">{{ $certificate->certificate_number }}</td>
                                <td class="p-3">{{ ucfirst($certificate->status) }}</td>
                                <td class="p-3 whitespace-nowrap">{{ $certificate->issued_at?->format('d M Y') ?? '—' }}</td>
                                <td class="p-3">
                                    @foreach (['email' => 'Email', 'sms' => 'SMS'] as $channel => $label)
                                        @php($deliveryStatus = $deliveryStatuses[$certificate->id][$channel] ?? null)
                                        <div class="mb-1 flex flex-wrap items-center gap-2">
                                            <span class="text-xs">{{ $label }}: {{ $deliveryStatus ? ucfirst($deliveryStatus) : 'Not sent' }}</span>
                                            @if ($certificate->status === 'issued' && ! $deliveryStatus)
                                                <x-filament::button size="xs" color="gray" wire:click="sendCertificate({{ $certificate->id }}, '{{ $channel }}')" wire:confirm="Send this certificate by {{ $label }} to the attendee's recorded contact?" wire:loading.attr="disabled" wire:target="sendCertificate">Send</x-filament::button>
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                                <td class="p-3"><div class="flex flex-wrap gap-2">
                                    @if ($certificate->status === 'issued')
                                        <x-filament::button size="sm" wire:click="download({{ $certificate->id }})" wire:loading.attr="disabled" wire:target="download">Download</x-filament::button>
                                        <x-filament::button size="sm" color="danger" wire:click="beginRevocation({{ $certificate->id }})">Revoke</x-filament::button>
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
                            <tr><td colspan="7" class="p-6 text-center text-gray-500">No certificate records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
        <div class="mt-3">{{ $rows->links() }}</div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
