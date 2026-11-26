<x-filament-panels::page>
    @php($event = $this->event())
    <x-filament::section>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium">{{ ucfirst($event->status) }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $event->starts_at?->format('d M Y, H:i') }} @if ($event->venue) · {{ $event->venue }} @endif</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-filament::button tag="a" color="gray" :href="\App\Filament\Resources\Events\EventResource::getUrl('index')">All events</x-filament::button>
                @if (\App\Filament\Resources\Events\EventResource::canEdit($event))
                    <x-filament::button tag="a" :href="\App\Filament\Resources\Events\EventResource::getUrl('edit', ['record' => $event])">Manage event</x-filament::button>
                @endif
            </div>
        </div>
    </x-filament::section>
    <div class="elive-overview-grid">
        @foreach ($this->metrics() as $stat)
            <div class="elive-overview-stat">
                <x-filament::icon :icon="$stat['icon']" class="elive-overview-icon" />
                <span class="elive-overview-label">{{ $stat['label'] }}</span>
                <span class="elive-overview-value">{{ number_format($stat['value']) }}</span>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
