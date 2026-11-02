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
                        <p class="text-sm text-gray-500 dark:text-gray-400">Available for approval*</p>
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
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">*Indicative unreserved settlement headroom. The approval service independently checks each request, existing commitments, reversals, and refund exceptions; a zero balance here does not by itself imply a pending request cannot be approved.</p>
                @if ($balance['refund_exception_count'] > 0)
                    <p class="mt-2 text-sm font-medium text-red-600">Approval blocked: unresolved refund exceptions ({{ $balance['refund_exception_count'] }}).</p>
                @endif
            @else
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Requests can be submitted before settlement verification; administrators must independently verify received funds before approval. Select an event in Request Payout to see its maximum amount.</p>
            @endif
        @elseif (count($options))
            <p class="text-sm text-gray-500 dark:text-gray-400">Choose an event above to view its current payout figures.</p>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">No eligible events are available.</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
