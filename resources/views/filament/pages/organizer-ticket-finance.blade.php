<x-filament-panels::page>
    @php
        $metrics = $this->financeMetrics();
        $currency = $metrics['currency'] ?? 'TZS';
    @endphp

    <div class="space-y-6">
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
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900"><h2 class="mb-3 text-lg font-semibold">Recent Paid Ticket Orders</h2><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="p-2">Order</th><th class="p-2">Paid at</th><th class="p-2">Gross</th><th class="p-2">Charges</th><th class="p-2">Ticket Net</th></tr></thead><tbody>@forelse($this->ticketTransactions() as $order)<tr class="border-b"><td class="p-2">{{ $order->order_number }}</td><td class="p-2">{{ $order->paid_at?->format('d M Y H:i') ?? 'Not recorded' }}</td><td class="p-2">{{ $order->currency }} {{ number_format((float) $order->gross_amount, 0) }}</td><td class="p-2">{{ $order->currency }} {{ number_format((float) $order->total_charges, 0) }}</td><td class="p-2">{{ $order->currency }} {{ number_format((float) $order->organizer_net_amount, 0) }}</td></tr>@empty<tr><td colspan="5" class="p-3 text-gray-500">No paid ticket orders found.</td></tr>@endforelse</tbody></table></div><p class="mt-3 text-xs text-gray-500">Latest 50 paid orders; ticket earnings do not confirm settled payout funds.</p></section>
    </div>
</x-filament-panels::page>
