<x-filament-widgets::widget>
    <x-filament::section heading="Action required">
        @php($issues = $this->issues())
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <div class="flex items-center gap-2 py-2">
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
