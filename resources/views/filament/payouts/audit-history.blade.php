<div class="space-y-3">
    @forelse($entries as $entry)
        <div class="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700">
            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ ucfirst($entry->action) }}</p>
            <p class="mt-1 text-gray-600 dark:text-gray-300">{{ $entry->from_status ?: 'New' }} → {{ $entry->to_status }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $entry->actor_name ?? 'Unknown administrator' }} · {{ $entry->created_at }}</p>
            @if($entry->note)
                <p class="mt-2 break-words text-gray-700 dark:text-gray-200">{{ $entry->note }}</p>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-500">No audit entries have been recorded for this payout.</p>
    @endforelse
</div>
