<x-filament-panels::page>
    @php($summary = $this->summary())
    <div class="space-y-4">
        <x-filament::section>
            <x-slot name="heading">Financial records</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300">Recorded earnings and completed payment attempts do not establish settled funds or payout eligibility. Amounts are separated by recorded currency. Refunds and reconciliation exceptions require independent review.</p>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Paid ticket orders</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left">
                        @foreach (['Currency', 'Orders', 'Gross', 'Commission', 'Gateway fees', 'Total charges', 'Organizer net'] as $label)
                            <th class="p-2">{{ $label }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                        @forelse ($summary['ticket_sales'] as $row)
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="p-2">{{ $row['currency'] ?: 'Unknown' }}</td>
                                <td class="p-2">{{ $row['paid_orders'] }}</td>
                                @foreach (['gross', 'commission', 'gateway_fees', 'charges', 'net'] as $key)
                                    <td class="p-2 whitespace-nowrap">{{ $row[$key] ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="7" class="p-2">No paid ticket orders.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
        <div class="grid gap-4 lg:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Completed payment attempts</x-slot>
                @forelse ($summary['payments'] as $row)
                    <div class="flex justify-between gap-3 py-2 text-sm">
                        <span>{{ $row['currency'] ?: 'Unknown' }} · {{ $row['completed_attempts'] }} attempts</span>
                        <strong>{{ $row['recorded_amount'] }}</strong>
                    </div>
                @empty
                    <p class="text-sm">No completed payment attempts.</p>
                @endforelse
                <p class="mt-2 text-xs text-gray-500">Includes all recorded payment types; may contain multiple attempts per order. Do not add these amounts to ticket revenue.</p>
            </x-filament::section>
            <x-filament::section>
                <x-slot name="heading">Payout request records</x-slot>
                @forelse ($summary['payouts'] as $row)
                    <div class="flex justify-between gap-3 py-2 text-sm">
                        <span>{{ ucfirst($row['status']) }} · {{ $row['requests'] }} requests</span>
                        <strong>{{ $row['currency'] ?: 'Unknown' }} {{ $row['amount'] }}</strong>
                    </div>
                @empty
                    <p class="text-sm">No payout requests.</p>
                @endforelse
                <p class="mt-2 text-xs text-gray-500">Read-only history. No transfer or payout approval is available here.</p>
            </x-filament::section>
        </div>
        <x-filament::section>
            <x-slot name="heading">Reconciliation exceptions</x-slot>
            @foreach ($summary['exceptions'] as $label => $count)
                <div class="flex justify-between gap-3 py-2 text-sm">
                    <span>{{ $label }}</span>
                    <strong>{{ $count }}</strong>
                </div>
            @endforeach
        </x-filament::section>
    </div>
</x-filament-panels::page>
