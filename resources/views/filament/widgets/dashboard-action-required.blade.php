<x-filament-widgets::widget>
    <x-filament::section heading="Action required">
        @php($issues = $this->issues())
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <div class="flex items-center gap-3 py-3">
                <x-filament::icon icon="heroicon-o-calendar-days" class="elive-overview-icon" />
                <span class="flex-1 text-sm">Draft events</span>
                <span class="font-medium">{{ number_format($issues['draft_events']) }}</span>
                @if (\App\Filament\Resources\Events\EventResource::canViewAny())
                    <x-filament::link :href="\App\Filament\Resources\Events\EventResource::getUrl('index')">Events</x-filament::link>
                @endif
            </div>
            <div class="flex items-center gap-3 py-3">
                <x-filament::icon icon="heroicon-o-exclamation-circle" class="elive-overview-icon" />
                <div class="flex-1">
                    <p class="text-sm">Completed payments awaiting fulfillment</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Last updated at least 15 minutes ago</p>
                </div>
                <span class="font-medium">{{ number_format($issues['unfulfilled_payments']) }}</span>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
