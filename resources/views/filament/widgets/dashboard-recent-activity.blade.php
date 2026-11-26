<x-filament-widgets::widget>
    <x-filament::section heading="Recent activity">
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse ($this->recentEvents() as $event)
                <div class="flex items-center gap-3 py-3">
                    <x-filament::icon icon="heroicon-o-clock" class="elive-overview-icon" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium">{{ $event->name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Event updated · {{ $event->updated_at?->diffForHumans() }}</p>
                    </div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ ucfirst($event->status) }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">No recent event updates.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
