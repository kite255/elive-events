<x-filament-panels::page>
    @php
        $events = $this->eventOptions();
        $metrics = $this->capacityMetrics();
        $summary = $metrics['summary'] ?? [];
        $types = $metrics['ticket_types'] ?? [];
        $number = fn ($value) => $value === null ? 'Unlimited' : number_format((int) $value);
    @endphp

    <div class="space-y-6">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Ticketing</p>
            <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Capacity Dashboard</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Live ticket inventory using the same sold and reservation rules as checkout.</p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <label for="capacity-event" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Event</label>
            <select id="capacity-event" wire:model.live="selectedEventId" class="mt-2 w-full max-w-xl rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950">
                <option value="">Select event</option>
                @foreach ($events as $eventId => $eventName)
                    <option value="{{ $eventId }}">{{ $eventName }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ([
                'capacity' => 'Event Capacity',
                'sold' => 'Tickets Sold',
                'reserved' => 'Active Reservations',
                'available' => 'Available Capacity',
                'checked_in' => 'Checked In',
            ] as $key => $label)
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ $number($summary[$key] ?? 0) }}</p>
                </div>
            @endforeach
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h2 class="font-semibold text-gray-950 dark:text-white">Capacity by Ticket Type</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-950/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3">Ticket Type</th>
                            <th class="px-5 py-3">Capacity</th>
                            <th class="px-5 py-3">Sold</th>
                            <th class="px-5 py-3">Reserved</th>
                            <th class="px-5 py-3">Available</th>
                            <th class="px-5 py-3">Checked In</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($types as $type)
                            <tr>
                                <td class="px-5 py-4 font-medium text-gray-950 dark:text-white">{{ $type['name'] }}</td>
                                <td class="px-5 py-4">{{ $number($type['capacity']) }}</td>
                                <td class="px-5 py-4">{{ number_format((int) $type['sold']) }}</td>
                                <td class="px-5 py-4">{{ number_format((int) $type['reserved']) }}</td>
                                <td class="px-5 py-4">{{ $number($type['available']) }}</td>
                                <td class="px-5 py-4">{{ number_format((int) $type['checked_in']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-8 text-center text-gray-500">No ticket types are available for the selected event.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
