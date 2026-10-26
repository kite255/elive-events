<x-filament-panels::page>
    @php
        $metrics = $this->financeMetrics();
        $currency = $metrics['currency'] ?? 'TZS';
        $formatMoney = static fn ($value) => $currency.' '.number_format((float) $value, 0);
    @endphp

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold">My Finance</h1>
            <p class="mt-1 text-sm text-gray-500">Event revenue overview. Ticket and merchandise sales are reported separately.</p>
        </div>
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <label for="selectedEventId" class="mb-2 block text-sm font-semibold">Event</label>
            <select id="selectedEventId" wire:model.live="selectedEventId" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-800">
                @forelse ($this->eventOptions() as $eventId => $name)
                    <option value="{{ $eventId }}">{{ $name }}</option>
                @empty
                    <option value="">No authorized events</option>
                @endforelse
            </select>
        </section>
        <div class="grid gap-4 md:grid-cols-2">
            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-lg font-semibold">Ticket Finance</h2>
                <dl class="mt-4 space-y-3">
                    <div class="flex justify-between gap-3"><dt>Ticket Gross Sales</dt><dd class="font-semibold">{{ $formatMoney($metrics['gross_sales'] ?? 0) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Ticket Charges</dt><dd class="font-semibold">{{ $formatMoney($metrics['total_charges'] ?? 0) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Ticket Net Payable</dt><dd class="font-semibold">{{ $formatMoney($metrics['net_payable'] ?? 0) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Paid Ticket Orders</dt><dd class="font-semibold">{{ number_format((int) ($metrics['paid_orders'] ?? 0)) }}</dd></div>
                </dl>
                <a href="{{ \App\Filament\Pages\OrganizerTicketFinance::getUrl() }}" class="mt-5 inline-block text-sm font-semibold text-primary-600">View Ticket Finance</a>
            </section>
            <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-lg font-semibold">Merchandise Finance</h2>
                <dl class="mt-4 space-y-3">
                    <div class="flex justify-between gap-3"><dt>Completed Merchandise Payments</dt><dd class="font-semibold">{{ number_format((int) ($metrics['completed_merchandise_payments'] ?? 0)) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>Merchandise Gross Receipts</dt><dd class="font-semibold">{{ $formatMoney($metrics['merchandise_gross_sales'] ?? 0) }}</dd></div>
                </dl>
                <p class="mt-3 text-sm text-gray-500">Merchandise gross receipts are not verified payout funds.</p>
                <a href="{{ \App\Filament\Pages\OrganizerMerchandiseFinance::getUrl() }}" class="mt-5 inline-block text-sm font-semibold text-primary-600">View Merchandise Finance</a>
            </section>
        </div>
        <p class="text-sm text-gray-500">These reports do not confirm payment-provider settlement or authorize a payout.</p>
    </div>
</x-filament-panels::page>
