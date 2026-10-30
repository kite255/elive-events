<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-4">
            <div>
                <label for="daily-event" class="block text-sm font-medium mb-1">Event</label>
                <select id="daily-event" wire:model.live="eventId" class="w-full rounded-lg border-gray-300 dark:bg-gray-900">
                    <option value="">All authorized events</option>
                    @foreach ($this->events as $event)
                        <option value="{{ $event->id }}">{{ $event->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="daily-start" class="block text-sm font-medium mb-1">From date (Tanzania)</label>
                <input id="daily-start" type="date" wire:model.live="startDate" class="w-full rounded-lg border-gray-300 dark:bg-gray-900">
            </div>
            <div>
                <label for="daily-end" class="block text-sm font-medium mb-1">To date (Tanzania)</label>
                <input id="daily-end" type="date" wire:model.live="endDate" class="w-full rounded-lg border-gray-300 dark:bg-gray-900">
            </div>
            <div class="flex items-end">
                <x-filament::button wire:click="exportCsv" icon="heroicon-o-arrow-down-tray">Export CSV</x-filament::button>
            </div>
        </div>

        <p class="text-sm text-gray-500">Paid ticket orders grouped by payment confirmation date in Africa/Dar_es_Salaam. Pending orders do not count. Gross ticket sales are not verified settlement funds or available payout balance. Refunded orders are excluded; partially refunded orders require separate reconciliation.</p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([
                'Paid orders' => number_format($this->totals['orders']),
                'Tickets' => number_format($this->totals['tickets']),
                'Free tickets' => number_format($this->totals['free_tickets']),
                'Gross sales (TZS)' => number_format($this->totals['gross'], 2),
                'Organizer net (TZS)' => number_format($this->totals['organizer_net'], 2),
            ] as $label => $value)
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-xs text-gray-500">{{ $label }}</p>
                    <p class="text-xl font-semibold mt-2">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 text-left">
                    <tr>
                        @foreach (['Date', 'Paid orders', 'Tickets', 'Free tickets', 'Gross sales (TZS)', 'Organizer net (TZS)'] as $label)
                            <th class="p-3">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->dailyRows as $row)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <td class="p-3">{{ $row['date'] }}</td>
                            <td class="p-3">{{ number_format($row['orders']) }}</td>
                            <td class="p-3">{{ number_format($row['tickets']) }}</td>
                            <td class="p-3">{{ number_format($row['free_tickets']) }}</td>
                            <td class="p-3">{{ number_format($row['gross'], 2) }}</td>
                            <td class="p-3">{{ number_format($row['organizer_net'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-5 text-center text-gray-500">No completed ticket orders for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
