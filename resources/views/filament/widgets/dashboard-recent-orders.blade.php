<x-filament-widgets::widget>
    <x-filament::section heading="Recent ticket purchases" description="Five latest confirmed purchases for events you can access.">
        @php($orders = $this->recentOrders())
        @if (count($orders) === 0)
            <p class="text-sm text-gray-500 dark:text-gray-400">No confirmed ticket purchases yet.</p>
        @else
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($orders as $order)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $order['number'] }}</p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $order['event'] }} · {{ $order['date'] }}</p>
                        </div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $order['amount'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
