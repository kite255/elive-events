<x-filament-widgets::widget>
    <x-filament::section heading="Upcoming events">
        <x-slot name="headerEnd">
            @if (\App\Filament\Resources\Events\EventResource::canViewAny())
                <x-filament::link :href="\App\Filament\Resources\Events\EventResource::getUrl('index')">View all</x-filament::link>
            @endif
            @if (\App\Filament\Resources\Events\EventResource::canCreate())
                <x-filament::button size="sm" icon="heroicon-o-plus" :href="\App\Filament\Resources\Events\EventResource::getUrl('create')" tag="a">Create event</x-filament::button>
            @endif
        </x-slot>
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse ($this->upcomingEvents() as $event)
                <div class="flex items-center gap-3 py-3">
                    <x-filament::icon icon="heroicon-o-calendar-days" class="elive-overview-icon" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium">{{ $event->name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $event->starts_at?->format('d M Y, H:i') }} @if ($event->venue) · {{ $event->venue }} @endif</p>
                    </div>
                    @if (\App\Filament\Resources\Events\EventResource::canEdit($event))
                        <x-filament::link :href="\App\Filament\Resources\Events\EventResource::getUrl('edit', ['record' => $event])">Manage</x-filament::link>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">No upcoming active events.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
