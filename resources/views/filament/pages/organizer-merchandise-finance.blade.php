<x-filament-panels::page>
    @php
        $metrics = $this->financeMetrics();
        $currency = $metrics['currency'] ?? 'TZS';
    @endphp

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold">Merchandise Finance</h1>
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
<div class="rounded-xl border border-gray-200 p-5 dark:border-gray-700"><p class="text-sm text-gray-500">Completed Merchandise Payments</p><p class="mt-2 text-2xl font-semibold">{{ number_format((int) ($metrics['completed_merchandise_payments'] ?? 0)) }}</p></div>
<div class="rounded-xl border border-gray-200 p-5 dark:border-gray-700"><p class="text-sm text-gray-500">Merchandise Gross Receipts</p><p class="mt-2 text-2xl font-semibold">{{ $currency }} {{ number_format((float) ($metrics['merchandise_gross_sales'] ?? 0), 0) }}</p></div>
</div>
<p class="mt-4 text-sm text-gray-500">Only completed merchandise payment receipts are shown. These are not confirmed settlements or funds available for payout. Refund adjustments and fulfillment statuses are not yet included in this overview.</p>
        </section>
    </div>
</x-filament-panels::page>
