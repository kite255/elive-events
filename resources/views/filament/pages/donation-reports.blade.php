<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section>
            <div class="text-sm text-gray-600">
                Select a campaign to view completed donation totals,
                payment-type breakdowns, and awaiting-verification counts.
            </div>
        </x-filament::section>

        @if ($this->summary() !== [])
            <x-filament::section>
                <pre>{{ json_encode($this->summary(), JSON_PRETTY_PRINT) }}</pre>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
