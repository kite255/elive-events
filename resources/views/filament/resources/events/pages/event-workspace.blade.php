<x-filament-panels::page>
    @php
        $event = $this->event();
        $stats = $this->metrics();
    @endphp
    <div class="elive-workspace-content">
        <section class="elive-sales-panel">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-medium">{{ $event->eventTypeLabel() }} · {{ ucfirst($event->status) }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $event->starts_at?->format('d M Y, H:i') }} @if ($event->venue) · {{ $event->venue }} @endif</p>
            </div>
        </section>
        @if ($stats !== [])
            <div class="elive-overview-grid">
                @foreach ($stats as $stat)
                    <div class="elive-overview-stat">
                        <x-filament::icon :icon="$stat['icon']" class="elive-overview-icon" />
                        <span class="elive-overview-label">{{ $stat['label'] }}</span>
                        <span class="elive-overview-value">{{ number_format($stat['value']) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
