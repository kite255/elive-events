@php
    $status = (string) $payout->status;
    $isPaid = $status === \App\Models\EventPayoutRequest::PAID;
@endphp
<div class="space-y-3 text-sm">
    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div><dt class="text-gray-500">Event</dt><dd class="font-medium">{{ $payout->event?->name ?? '—' }}</dd></div>
        <div><dt class="text-gray-500">Status</dt><dd class="font-medium">{{ ucfirst($status) }}</dd></div>
        <div><dt class="text-gray-500">Amount requested</dt><dd class="font-medium">{{ $payout->currency }} {{ number_format((float) $payout->amount, 2) }}</dd></div>
        <div><dt class="text-gray-500">Requested at</dt><dd>{{ $payout->created_at?->format('d M Y H:i') ?? '—' }}</dd></div>
        <div><dt class="text-gray-500">Payment destination type</dt><dd>{{ str_replace('_', ' ', ucfirst((string) $payout->destination_type)) }}</dd></div>
        <div><dt class="text-gray-500">Transfer status</dt><dd>{{ $isPaid ? 'Recorded as transferred' : 'Not transferred' }}</dd></div>
        @if ($isPaid)
            <div><dt class="text-gray-500">Transfer method</dt><dd>{{ $payout->transfer_method ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Transfer date</dt><dd>{{ $payout->transfer_date?->format('d M Y') ?? '—' }}</dd></div>
            <div><dt class="text-gray-500">Received</dt><dd>{{ $payout->received_at?->format('d M Y H:i') ?? 'Awaiting organizer confirmation' }}</dd></div>
        @endif
    </dl>
    @if ($status === \App\Models\EventPayoutRequest::REJECTED)
        <div class="rounded-lg border border-red-200 p-3">
            <strong>Rejection reason</strong>
            <p class="mt-1 whitespace-pre-line">{{ $payout->review_note ?: 'No reason was recorded.' }}</p>
        </div>
    @elseif ($status === \App\Models\EventPayoutRequest::REQUESTED)
        <p class="text-gray-500">Awaiting administrative review. Approval requires independently verified settlement.</p>
    @endif
</div>
