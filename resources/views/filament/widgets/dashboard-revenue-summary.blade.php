<x-filament-widgets::widget>
    <x-filament::section heading="Ticket revenue">
        <x-slot name="headerEnd">
            <label class="sr-only" for="dashboard-revenue-period">Revenue period</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="dashboard-revenue-period" wire:model.live="period">
                    <option value="7">Last 7 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="90">Last 90 days</option>
                    <option value="all">All time</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </x-slot>
        <div class="space-y-3">
            @forelse ($this->revenue() as $row)
                <div class="elive-quick-actions-grid">
                    <div class="elive-overview-stat">
                        <x-filament::icon icon="heroicon-o-wallet" class="elive-overview-icon" />
                        <span class="elive-overview-label">Gross paid ticket orders</span>
                        <span class="elive-overview-value">{{ $row['currency'] }} {{ number_format((float) $row['gross_sales'], 2) }}</span>
                    </div>
                    <div class="elive-overview-stat">
                        <x-filament::icon icon="heroicon-o-shopping-cart" class="elive-overview-icon" />
                        <span class="elive-overview-label">Paid orders ({{ $row['currency'] }})</span>
                        <span class="elive-overview-value">{{ number_format($row['paid_orders']) }}</span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">No paid ticket orders for this period.</p>
            @endforelse
            <p class="text-xs text-gray-500 dark:text-gray-400">Paid ticket order totals only. Excludes upgrades, refunds, donations and merchandise. Not a verified settlement or payout balance.</p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
