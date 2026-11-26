<x-filament-panels::page>
    @php
        $event = $this->event();
        $links = collect($this->moduleLinks())->keyBy('label');
        $groups = [
            'Event management' => ['Attendees', 'Ticket orders'],
            'Operations' => ['Check-in station', 'Communications'],
            'Configuration' => ['Event settings'],
        ];
        $stats = $this->metrics();
    @endphp

    <div class="elive-workspace-layout">
        <nav class="elive-workspace-nav" aria-label="Event workspace">
            <p class="elive-workspace-type">{{ $event->eventTypeLabel() }}</p>
            <a class="elive-workspace-link elive-workspace-active" aria-current="page"
                href="{{ \App\Filament\Resources\Events\EventResource::getUrl('workspace', ['record' => $event->id]) }}">
                <x-filament::icon icon="heroicon-o-squares-2x2" />
                Overview
            </a>
            @foreach ($groups as $heading => $labels)
                @php($items = $links->only($labels))
                @if ($items->isNotEmpty())
                    <p class="elive-workspace-group">{{ $heading }}</p>
                    @foreach ($items as $link)
                        <a class="elive-workspace-link" href="{{ $link['url'] }}">
                            <x-filament::icon :icon="$link['icon']" />
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                @endif
            @endforeach
            <a class="elive-workspace-link elive-workspace-back" href="{{ \App\Filament\Resources\Events\EventResource::getUrl('index') }}">
                <x-filament::icon icon="heroicon-o-arrow-left" />
                All events
            </a>
        </nav>
        <div class="elive-workspace-content">
            <section class="elive-sales-panel">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-medium">{{ ucfirst($event->status) }}</p>
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
    </div>
</x-filament-panels::page>
