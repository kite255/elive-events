<x-filament-widgets::widget>
    <x-filament::section heading="Upcoming events">
        <x-slot name="headerEnd">
            @if (\App\Filament\Resources\Events\EventResource::canViewAny())
                <x-filament::link :href="\App\Filament\Resources\Events\EventResource::getUrl('index')">View all</x-filament::link>
            @endif
        </x-slot>
        <div class="elive-upcoming-events">
            @forelse ($this->upcomingEvents() as $event)
                <div class="elive-upcoming-row">
                    <a class="elive-upcoming-name" href="{{ \App\Filament\Resources\Events\EventResource::getUrl('workspace', ['record' => $event->id]) }}">
                        {{ $event->name }}
                    </a>
                    <time class="elive-upcoming-date" datetime="{{ $event->starts_at?->toIso8601String() }}">{{ $event->starts_at?->format('d M Y, H:i') }}</time>
                    <span class="elive-upcoming-status">{{ ucfirst($event->status) }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">No upcoming events.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
