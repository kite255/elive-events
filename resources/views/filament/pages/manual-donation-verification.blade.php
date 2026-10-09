<x-filament-panels::page>
    <div class="space-y-4">
        @forelse ($this->pendingDonations() as $donation)
            <x-filament::section>
                <div class="space-y-2">
                    <div class="font-semibold">
                        {{ $donation->reference }}
                    </div>
                    <div>
                        {{ $donation->currency }}
                        {{ number_format((float) $donation->amount, 2) }}
                    </div>
                    <div>
                        {{ $donation->campaign?->title }}
                    </div>
                    <div>
                        Reference:
                        {{ $donation->manualSubmission?->transaction_reference ?: '—' }}
                    </div>
                </div>
            </x-filament::section>
        @empty
            <x-filament::section>
                No donations are awaiting manual verification.
            </x-filament::section>
        @endforelse
    </div>
</x-filament-panels::page>
