<x-filament-panels::page>
    @php($report = $this->report())
    <div class="space-y-4">
        <x-filament::section>
            <x-slot name="heading">Order status summary</x-slot>
            <p class="mb-3 text-sm text-gray-500">Recorded order amounts, not settled cash or amounts actually refunded. Currencies are reported separately.</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left">
                        @foreach (['Currency', 'Status', 'Orders', 'Ordered quantity', 'Order total'] as $label)
                            <th class="p-2">{{ $label }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                        @forelse ($report['orders'] as $row)
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="p-2">{{ $row->currency ?: 'Unknown' }}</td>
                                <td class="p-2">{{ ucfirst(str_replace('_', ' ', $row->status)) }}</td>
                                <td class="p-2">{{ $row->orders }}</td>
                                <td class="p-2">{{ $row->quantity }}</td>
                                <td class="p-2 whitespace-nowrap">{{ $row->total }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-2">No ticket orders.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Paid order items by purchased ticket type</x-slot>
            <p class="mb-3 text-sm text-gray-500">Original purchased quantities and item totals on currently paid orders. Ticket upgrades and later refund adjustments are not included in this breakdown.</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left">
                        @foreach (['Ticket type', 'Currency', 'Quantity', 'Item total'] as $label)
                            <th class="p-2">{{ $label }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                        @forelse ($report['types'] as $row)
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="p-2">{{ $row->ticket_type ?? 'Unknown type #'.$row->ticket_type_id }}</td>
                                <td class="p-2">{{ $row->currency ?: 'Unknown' }}</td>
                                <td class="p-2">{{ $row->quantity }}</td>
                                <td class="p-2 whitespace-nowrap">{{ $row->item_total }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-2">No paid order items.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Current ticket status</x-slot>
            @forelse ($report['tickets'] as $row)
                <div class="flex justify-between gap-3 py-2 text-sm">
                    <span>{{ ucfirst($row->status) }}</span>
                    <strong>{{ $row->quantity }}</strong>
                </div>
            @empty
                <p class="text-sm">No issued ticket records.</p>
            @endforelse
        </x-filament::section>
    </div>
</x-filament-panels::page>
