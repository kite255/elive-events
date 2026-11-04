<x-filament-widgets::widget>
    <x-filament::section heading="Payout balance overview" description="Select an event to see requestable earnings and verified settlement separately.">
        @php($options = $this->eventOptions())
        <div class="mb-4">
            <label for="payout-balance-event" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Event</label>
            <select id="payout-balance-event" wire:model.live="selectedEventId" class="w-full max-w-lg rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                <option value="">Choose an event</option>
                @foreach ($options as $eventId => $eventName)
                    <option value="{{ $eventId }}">{{ $eventName }}</option>
                @endforeach
            </select>
        </div>
        @php($balance = $this->overview())
        @if ($balance)
            {{-- Earnings summary is intentionally separate from settlement verification. --}}
            <div class="mb-4 rounded-xl border-2 border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800 dark:bg-indigo-950/40" aria-label="Organizer remaining payout balance">
                <p class="text-sm font-semibold text-indigo-900 dark:text-indigo-100">Remaining organizer balance</p>
                <p class="mt-1 text-3xl font-bold tracking-tight text-indigo-950 dark:text-white">TZS {{ number_format($balance['remaining_unpaid_earnings'], 2) }}</p>
                <p class="mt-1 text-sm text-indigo-800 dark:text-indigo-200">Unpaid ticket earnings after completed bank transfers. Includes money reserved for requests that are not yet paid.</p>
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-900">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Reserved for pending / approved</p>
                        <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">TZS {{ number_format($balance['reserved_requests'], 2) }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Not yet transferred</p>
                    </div>
                    <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-900">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Available for a new request</p>
                        <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-400">TZS {{ number_format($balance['unreserved_earnings'], 2) }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Unreserved ticket earnings; settlement clearance required for transfer</p>
                    </div>
                    <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-900">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-300">Already paid out</p>
                        <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white">TZS {{ number_format($balance['paid_out'], 2) }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Completed transfers recorded in eLive</p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-indigo-800 dark:text-indigo-200">Calculation: Net ticket earnings − Paid out = Remaining unpaid. Remaining unpaid − Reserved requests = Available for new requests. These are payout ledger figures, not your actual bank-account balance.</p>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Available to request</p>
                    <p class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">TZS {{ number_format($balance['requestable_pending_verification_balance'], 2) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Not a guarantee of settled funds</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Net ticket earnings</p>
                    <p class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">TZS {{ number_format($balance['ticket_net_earnings'], 2) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Confirmed paid ticket orders</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pending &amp; approved requests</p>
                    <p class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">TZS {{ number_format($balance['reserved_requests'], 2) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Already reserved from earnings</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total paid out</p>
                    <p class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">TZS {{ number_format($balance['paid_out'], 2) }}</p>
                    <p class="mt-1 text-xs text-gray-500">Transfers recorded as paid</p>
                </div>
            </div>
            @if ($balance['is_admin'])
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Independently verified settlement</p>
                        <p class="mt-1 text-lg font-semibold">TZS {{ number_format($balance['verified_cleared_funds'], 2) }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Single-admin self-attested settlement (not independent)</p>
                        <p class="mt-1 text-lg font-semibold">TZS {{ number_format($balance['self_attested_cleared_funds'] ?? 0, 2) }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Unreserved reviewed settlement*</p>
                        <p class="mt-1 text-lg font-semibold">TZS {{ number_format($balance['requestable_ticket_balance'], 2) }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Awaiting review</p>
                        <p class="mt-1 text-lg font-semibold">{{ $balance['pending_count'] }} requests · TZS {{ number_format($balance['pending_amount'], 2) }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Approved, awaiting transfer</p>
                        <p class="mt-1 text-lg font-semibold">TZS {{ number_format($balance['approved_amount'], 2) }}</p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">*Indicative unreserved settlement headroom. Approval does not authorize payment. Recording a transfer requires verified evidence or an explicitly documented single-admin exception, plus sufficient funds and no unresolved exceptions.</p>
                @if ($balance['refund_exception_count'] > 0)
                    <p class="mt-2 text-sm font-medium text-red-600">Approval blocked: unresolved refund exceptions ({{ $balance['refund_exception_count'] }}).</p>
                @endif
            @else
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Requests can be submitted before settlement verification; a super administrator may approve conditionally, but money must be reviewed before a transfer is recorded. Select an event in Request Payout to see its maximum amount.</p>
            @endif
        @elseif (count($options))
            <p class="text-sm text-gray-500 dark:text-gray-400">Choose an event above to view its current payout figures.</p>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">No eligible events are available.</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
