<x-filament-widgets::widget>
    <section class="elive-sales-panel" aria-labelledby="dashboard-sales-heading">
        <div class="elive-sales-heading">
            <h2 id="dashboard-sales-heading">Ticket sales summary</h2>
            <label class="sr-only" for="dashboard-revenue-period">Revenue period</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="dashboard-revenue-period" wire:model.live="period">
                    <option value="7">Last 7 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="90">Last 90 days</option>
                    <option value="all">All time</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
        <div class="elive-sales-summary">
            @forelse ($this->revenue() as $row)
                <div class="elive-sales-row">
                    <div>
                        <span class="elive-overview-label">Gross sales ({{ $row['currency'] }})</span>
                        <strong>{{ number_format((float) $row['gross_sales'], 2) }}</strong>
                    </div>
                    <div>
                        <span class="elive-overview-label">Paid orders</span>
                        <strong>{{ number_format($row['paid_orders']) }}</strong>
                    </div>
                    <div>
                        <span class="elive-overview-label">Tickets sold</span>
                        <strong>{{ number_format($row['tickets_sold']) }}</strong>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">No paid ticket orders for this period.</p>
            @endforelse
            <p class="text-xs text-gray-500 dark:text-gray-400">Paid ticket orders only; excludes upgrades, refunds, donations and merchandise. Not a payout balance.</p>
        </div>
    </section>
</x-filament-widgets::widget>
