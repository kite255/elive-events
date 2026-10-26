<x-filament-panels::page>
    @php
        $metrics = $this->financeMetrics();
        $currency = $metrics['currency'] ?? 'TZS';
    @endphp

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold">Ticket Finance</h1>
            <p class="mt-1 text-sm text-gray-500">Financial information for your authorized events.</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <label for="finance-event" class="mb-2 block text-sm font-semibold">Event</label>
            <select id="finance-event" wire:model.live="selectedEventId" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-800">
                @forelse ($this->eventOptions() as $eventId => $name)
                    <option value="{{ $eventId }}">{{ $name }}</option>
                @empty
                    <option value="">No authorized events</option>
                @endforelse
            </select>
        </div>
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <div class="grid gap-4 sm:grid-cols-2">
@foreach ([
    'Ticket Gross Sales' => $metrics['gross_sales'] ?? 0,
    'Ticket Charges' => $metrics['total_charges'] ?? 0,
    'Ticket Net Payable' => $metrics['net_payable'] ?? 0,
] as $label => $amount)
<div class="rounded-xl border border-gray-200 p-5 dark:border-gray-700"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $currency }} {{ number_format((float) $amount, 0) }}</p></div>
@endforeach
<div class="rounded-xl border border-gray-200 p-5 dark:border-gray-700"><p class="text-sm text-gray-500">Paid Ticket Orders</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($metrics['paid_orders'] ?? 0)) }}</p></div>
</div>
<p class="mt-4 text-sm text-gray-500">Ticket net payable is not a confirmed, cleared payout balance.</p>
        </section>
    </div>
</x-filament-panels::page>
