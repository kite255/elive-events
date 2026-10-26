<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <label for="finance-event" class="mb-2 block text-sm font-semibold text-gray-800 dark:text-gray-100">Select event</label>
            <select id="finance-event" wire:model.live="eventId" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option value="">Choose an event</option>
                @foreach($this->eventOptions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        @php($report = $this->financeReport())

        @if($report)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
                Ticket and merchandise figures are separate. Merchandise gross receipts are not payout-eligible funds. Settlement entries require independent evidence review; manual attestations are not bank/provider confirmation. Payout requests are disabled by default.
                @if($report['overallocated'])
                    <p class="mt-2 font-semibold">Attention: recorded payouts and reservations exceed ticket net earnings by TZS {{ number_format($report['overallocated_amount'], 2) }}.</p>
                @endif
                @if($report['refunded_or_partially_refunded_orders'] || $report['refunded_or_partially_refunded_payments'])
                    <p class="mt-2 font-semibold">Refund exceptions require manual investigation before any payout.</p>
                @endif
            </div>

            <section class="space-y-3" aria-label="Ticket Finance">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Ticket Finance</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ticket earnings and payment exceptions. Paid orders do not prove settlement.</p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach([
                    'Paid ticket orders' => number_format($report['paid_orders']),
                    'Net ticket earnings' => 'TZS '.number_format($report['net_ticket_earnings'], 2),
                    'Completed payment attempts' => number_format($report['completed_payment_attempts']),
                    'Refunded / partially refunded orders' => number_format($report['refunded_or_partially_refunded_orders']),
                    'Refunded / partially refunded payments' => number_format($report['refunded_or_partially_refunded_payments']),
                    ] as $label => $value)
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="space-y-3" aria-label="Merchandise Finance">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Merchandise Finance</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Completed merchandise receipts and refund exceptions, reported separately from ticket earnings.</p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach([
                    'Completed merchandise payments' => number_format($report['completed_merchandise_payments']),
                    'Completed merchandise sales (gross)' => 'TZS '.number_format($report['completed_merchandise_payment_gross'], 2),
                    'Merchandise refund exceptions' => number_format($report['merchandise_refund_exceptions']),
                    ] as $label => $value)
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="space-y-3" aria-label="Settlement and Reconciliation">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Settlement & Reconciliation</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Administrator-attested entries are subject to independent review; these figures are indicative only.</p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach([
                    'Manually attested cleared funds' => 'TZS '.number_format($report['manually_attested_cleared_funds'], 2),
                    'Recorded refund / chargeback reversals' => 'TZS '.number_format($report['manually_attested_reversals'], 2),
                    'Clearance-limited indicative balance' => 'TZS '.number_format($report['indicative_clearance_limited_balance'], 2),
                    ] as $label => $value)
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="space-y-3" aria-label="Payout Management">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Payout Management</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Recorded ticket payouts and reserved requests. This report does not authorize transfers.</p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach([
                    'Recorded payouts' => 'TZS '.number_format($report['recorded_payouts'], 2),
                    'Pending requests' => 'TZS '.number_format($report['reserved_requests'], 2),
                    'Indicative unallocated balance' => 'TZS '.number_format($report['indicative_unallocated_balance'], 2),
                    ] as $label => $value)
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">Select an event to view its finance report.</p>
        @endif
    </div>
</x-filament-panels::page>
