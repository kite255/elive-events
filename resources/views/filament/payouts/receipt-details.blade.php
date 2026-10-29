<div class="space-y-3 text-sm">
    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Event</dt>
            <dd class="font-semibold text-gray-950 dark:text-white">{{ $payout->event?->name ?? 'Event' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Payout reference</dt>
            <dd class="font-semibold text-gray-950 dark:text-white">{{ $payout->reference }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Transferred amount</dt>
            <dd class="font-semibold text-gray-950 dark:text-white">{{ $payout->currency }} {{ number_format((float) $payout->amount, 2) }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Payment method</dt>
            <dd class="font-semibold text-gray-950 dark:text-white">{{ $payout->destination_type === 'mobile_money' ? 'Mobile money' : 'Bank' }}</dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-gray-500 dark:text-gray-400">Destination details</dt>
            <dd class="break-words font-semibold text-gray-950 dark:text-white">{{ $payout->destination_details }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Transfer reference</dt>
            <dd class="break-all font-semibold text-gray-950 dark:text-white">{{ $payout->transfer_reference ?: 'Not recorded' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Transfer recorded at</dt>
            <dd class="font-semibold text-gray-950 dark:text-white">{{ $payout->paid_at?->format('d M Y, H:i') ?? '—' }}</dd>
        </div>
    </dl>
    <p class="rounded-lg bg-amber-50 p-3 text-amber-900 dark:bg-amber-950 dark:text-amber-100">
        Only confirm after checking that the entire amount has actually arrived in your account. This acknowledgement does not initiate a transfer.
    </p>
</div>
