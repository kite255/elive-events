<x-filament-panels::page>
    @php($audits = $this->auditRows())
    @php($clearance = $this->clearanceRows())
    <x-filament::section>
        <x-slot name="heading">Payout activity</x-slot>
        <p class="text-sm text-gray-500">Read-only history of recorded payout events. Amounts reflect the associated request, not a new transaction.</p>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 my-4">
            <label class="text-sm">Payout reference
                <x-filament::input.wrapper><x-filament::input type="search" wire:model.live.debounce.400ms="reference" maxlength="255" /></x-filament::input.wrapper>
            </label>
            <label class="text-sm">Action
                <x-filament::input.wrapper><x-filament::input.select wire:model.live="action">
                    <option value="">All actions</option>
                    @foreach (['requested', 'approved', 'rejected', 'paid', 'receipt_confirmed'] as $item)
                        <option value="{{ $item }}">{{ ucfirst(str_replace('_', ' ', $item)) }}</option>
                    @endforeach
                </x-filament::input.select></x-filament::input.wrapper>
            </label>
            <label class="text-sm">From
                <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="from" /></x-filament::input.wrapper>
            </label>
            <label class="text-sm">To
                <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="to" /></x-filament::input.wrapper>
            </label>
        </div>
        @foreach (['reference', 'action', 'from', 'to'] as $field)
            @error($field)<p class="text-sm text-danger-600">{{ $message }}</p>@enderror
        @endforeach
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left">
                    @foreach (['Recorded', 'Payout reference', 'Request amount', 'Action', 'Previous status', 'New status', 'Actor ID'] as $heading)
                        <th class="p-2">{{ $heading }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                    @forelse ($audits as $row)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <td class="p-2">{{ $row->created_at }}</td><td class="p-2">{{ $row->reference }}</td>
                            <td class="p-2 whitespace-nowrap">{{ $row->currency }} {{ $row->amount }}</td>
                            <td class="p-2">{{ ucfirst(str_replace('_', ' ', $row->action)) }}</td>
                            <td class="p-2">{{ $row->from_status ?? '—' }}</td><td class="p-2">{{ $row->to_status ?? '—' }}</td>
                            <td class="p-2">{{ $row->actor_id ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-3">No matching payout activity.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $audits->links() }}</div>
    </x-filament::section>
    <x-filament::section collapsible>
        <x-slot name="heading">Clearance record history</x-slot>
        <p class="text-sm text-gray-500">Manual evidence records and review timestamps. This section is independent of the payout filters.</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left">
                    @foreach (['Entry', 'Amount', 'Recorded by / at', 'Independent review', 'Self-attestation', 'Voided'] as $heading)
                        <th class="p-2">{{ $heading }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                    @forelse ($clearance as $row)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <td class="p-2">#{{ $row->id }} · {{ ucfirst($row->entry_type) }}</td>
                            <td class="p-2 whitespace-nowrap">{{ $row->currency }} {{ $row->amount }}</td>
                            <td class="p-2">{{ $row->recorded_by }} / {{ $row->created_at }}</td>
                            <td class="p-2">{{ $row->independently_verified_at ? $row->independently_verified_by.' / '.$row->independently_verified_at : '—' }}</td>
                            <td class="p-2">{{ $row->self_attested_at ? $row->self_attested_by.' / '.$row->self_attested_at : '—' }}</td>
                            <td class="p-2">{{ $row->voided_at ? $row->voided_by.' / '.$row->voided_at : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-3">No clearance records.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $clearance->links() }}</div>
    </x-filament::section>
</x-filament-panels::page>
