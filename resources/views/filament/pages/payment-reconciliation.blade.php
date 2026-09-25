<x-filament-panels::page>
    @php
        $events = $this->eventOptions();
        $rows = $this->reconciliationRows();
        $summary = $this->summary();
    @endphp

    <div class="space-y-6">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Ticketing</p>
            <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Payment Reconciliation</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Investigate payment, order, and ticket inconsistencies without manually overriding payment status.</p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <label for="reconciliation-event" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Event</label>
            <select id="reconciliation-event" wire:model.live="selectedEventId" class="mt-2 w-full max-w-xl rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950">
                <option value="">Select event</option>
                @foreach ($events as $eventId => $eventName)
                    <option value="{{ $eventId }}">{{ $eventName }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ([
                'needs_attention' => 'Needs Attention',
                'unfulfilled_completed' => 'Unfulfilled Completed',
                'pending_verification' => 'Pending Verification',
                'amount_currency_mismatches' => 'Amount / Currency Mismatch',
                'resolved_today' => 'Recovery Actions Today',
            ] as $key => $label)
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ number_format((int) ($summary[$key] ?? 0)) }}</p>
                </div>
            @endforeach
        </div>

        <div class="space-y-4">
            @forelse ($rows as $row)
                <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-gray-950 dark:text-white">{{ $row['reference'] }}</span>
                                <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ str($row['local_status'])->replace('_', ' ')->headline() }}</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $row['customer'] }} · {{ $row['order_reference'] ?: 'No order reference' }}</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @if ($row['can_resync'] && $row['payment_id'])
                                <button
                                    type="button"
                                    wire:click="resyncPayment({{ $row['payment_id'] }})"
                                    wire:confirm="Re-sync this payment directly with Pesapal?"
                                    class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500"
                                >
                                    Re-sync with Pesapal
                                </button>
                            @endif

                            @if ($row['can_retry_fulfillment'] && $row['payment_id'])
                                <button
                                    type="button"
                                    wire:click="retryFulfillment({{ $row['payment_id'] }})"
                                    wire:confirm="Retry the verified payment fulfillment? This will not charge the customer again."
                                    class="rounded-lg bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900"
                                >
                                    Retry Fulfillment
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div><span class="text-xs text-gray-500">Provider Status</span><div class="font-medium">{{ $row['provider_status'] ?: 'Not synchronized' }}</div></div>
                        <div><span class="text-xs text-gray-500">Order Status</span><div class="font-medium">{{ str((string) ($row['order_status'] ?: '—'))->replace('_', ' ')->headline() }}</div></div>
                        <div><span class="text-xs text-gray-500">Amount</span><div class="font-medium">{{ $row['actual_currency'] ?: $row['expected_currency'] }} {{ $row['actual_amount'] === null ? '—' : number_format((float) $row['actual_amount'], 2) }}</div></div>
                        <div><span class="text-xs text-gray-500">Tickets</span><div class="font-medium">{{ $row['tickets_issued'] ?? 0 }} / {{ $row['expected_tickets'] ?? '—' }}</div></div>
                    </div>

                    <div class="mt-4 space-y-2">
                        @foreach ($row['issues'] as $issue)
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                                {{ $issue['message'] }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-xl bg-white p-8 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h2 class="font-semibold text-gray-950 dark:text-white">No reconciliation issues</h2>
                    <p class="mt-1 text-sm text-gray-500">No local payment inconsistencies were found for the selected event.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
