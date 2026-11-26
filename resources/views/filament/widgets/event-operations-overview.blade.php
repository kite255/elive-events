<x-filament-widgets::widget>
    <div class="elive-operations-overview">
        <div class="elive-overview-grid">
            @foreach ($this->getCachedStats() as $stat)
                <div class="elive-overview-stat">
                    @if ($stat->getIcon())
                        <x-filament::icon :icon="$stat->getIcon()" class="elive-overview-icon" />
                    @endif
                    <span class="elive-overview-label">{{ $stat->getLabel() }}</span>
                    <span class="elive-overview-value">{{ $stat->getValue() }}</span>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
