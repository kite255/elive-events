<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section>
            <div class="text-sm text-gray-600">
                This page surfaces donation payments that need provider
                re-synchronization or verified fulfillment retry.
            </div>
        </x-filament::section>

        @foreach ($this->reconciliationRows() as $row)
            <x-filament::section>
                <div class="space-y-2">
                    <div class="font-semibold">
                        {{ $row['reference'] }}
                    </div>
                    <div>
                        Donation: {{ $row['donation_reference'] ?? '—' }}
                    </div>
                    <div>
                        Status: {{ $row['local_status'] }}
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
