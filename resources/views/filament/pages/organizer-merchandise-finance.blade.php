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
<div class="rounded-xl border border-gray-200 p-5 dark:border-gray-700"><p class="text-sm text-gray-500">Completed Merchandise Payments</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($metrics['completed_merchandise_payments'] ?? 0)) }}</p></div>
<div class="rounded-xl border border-gray-200 p-5 dark:border-gray-700"><p class="text-sm text-gray-500">Merchandise Gross Receipts</p><p class="mt-2 text-2xl font-semibold">{{ $currency }} {{ number_format((float) ($metrics['merchandise_gross_sales'] ?? 0), 0) }}</p></div>
</div>
<p class="mt-4 text-sm text-gray-500">Gross receipts count completed payments only. Recent transactions below also show other payment statuses.</p>
        </section>
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900"><h2 class="mb-3 text-lg font-semibold">Recent Merchandise Payments</h2><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="p-2">Order</th><th class="p-2">Date</th><th class="p-2">Amount</th><th class="p-2">Payment</th><th class="p-2">Fulfillment</th></tr></thead><tbody>@forelse($this->merchandiseTransactions() as $payment)<tr class="border-b"><td class="p-2">{{ $payment->merchandiseOrder?->order_number ?? 'Unknown' }}</td><td class="p-2">{{ ($payment->paid_at ?? $payment->created_at)?->format('d M Y H:i') ?? 'Not recorded' }}</td><td class="p-2">{{ $payment->currency }} {{ number_format((float) $payment->amount, 0) }}</td><td class="p-2">{{ ucfirst(str_replace('_', ' ', $payment->status)) }}</td><td class="p-2">{{ ucfirst(str_replace('_', ' ', $payment->merchandiseOrder?->fulfillment_status ?? 'Unknown')) }}</td></tr>@empty<tr><td colspan="5" class="p-3 text-gray-500">No merchandise payments found.</td></tr>@endforelse</tbody></table></div><p class="mt-3 text-xs text-gray-500">Latest 50 payment records, including pending, expired and refunded attempts. Receipts do not indicate verified settlement.</p></section>
    </div>
</x-filament-panels::page>
