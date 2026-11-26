<x-filament-widgets::widget>
    <div class="elive-dashboard-toolbar">
        <label for="dashboard-event-switcher" class="text-sm font-medium">Event workspace</label>
        <div class="elive-dashboard-select">
            <x-filament::input.wrapper>
                <x-filament::input.select id="dashboard-event-switcher" wire:change="openEvent($event.target.value)">
                    <option value="" selected disabled>Select an event</option>
                    @foreach ($this->eventOptions() as $event)
                        <option value="{{ $event->id }}">{{ $event->name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        @if (\App\Filament\Resources\Events\EventResource::canCreate())
            <x-filament::button tag="a" color="primary" icon="heroicon-o-plus" class="elive-dashboard-primary" :href="\App\Filament\Resources\Events\EventResource::getUrl('create')">Create event</x-filament::button>
        @endif
    </div>
</x-filament-widgets::widget>
