<x-filament-widgets::widget>
    <div class="flex flex-wrap items-center gap-3">
        <label for="dashboard-event-switcher" class="text-sm font-medium">Event workspace</label>
        <div class="min-w-0 flex-1">
            <x-filament::input.wrapper>
                <x-filament::input.select id="dashboard-event-switcher" wire:change="openEvent($event.target.value)">
                    <option value="" selected disabled>Select an event</option>
                    @foreach ($this->eventOptions() as $event)
                        <option value="{{ $event->id }}">{{ $event->name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        @if (\App\Filament\Resources\Events\EventResource::canViewAny())
            <x-filament::link :href="\App\Filament\Resources\Events\EventResource::getUrl('index')">All events</x-filament::link>
        @endif
        @if (\App\Filament\Resources\Events\EventResource::canCreate())
            <x-filament::button tag="a" size="sm" icon="heroicon-o-plus" :href="\App\Filament\Resources\Events\EventResource::getUrl('create')">Create event</x-filament::button>
        @endif
    </div>
</x-filament-widgets::widget>
