<x-filament-panels::page>
    <div class="space-y-5">
        <div class="rounded-xl border-2 border-amber-400 p-5 space-y-2">
            <h2 class="text-lg font-bold">STAGING SIMULATION — NO REAL MONEY</h2>
            <p class="text-sm">Event 10: eLive Payout Testing - SIMULATED. This is a separate test ledger. It does not credit verified settlements, does not create real payout requests, and cannot transfer funds.</p>
            <p class="text-sm">Starting simulated balance: <strong>TZS 100,000.00</strong></p>
            <p class="text-xl font-bold">Available simulated balance: TZS {{ number_format($this->available, 2) }}</p>
        </div>
        <form wire:submit="submitSimulation" class="space-y-3 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <label for="simulation-amount" class="block text-sm font-medium">Simulated request amount (TZS)</label>
            <input id="simulation-amount" type="number" min="0.01" step="0.01" wire:model="amount" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" />
            @error('amount') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <x-filament::button type="submit">Create simulated request</x-filament::button>
        </form>
        <h3 class="font-semibold">Simulation history</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr><th class="p-3 text-left">ID</th><th class="p-3 text-left">Requester ID</th><th class="p-3 text-left">Amount (TZS)</th><th class="p-3 text-left">Status</th></tr></thead>
                <tbody>
                @forelse ($this->requests as $request)
                    <tr class="border-t"><td class="p-3">{{ $request->id }}</td><td class="p-3">{{ $request->requested_by }}</td><td class="p-3">{{ number_format($request->amount,2) }}</td><td class="p-3">{{ $request->status }}</td></tr>
                @empty
                    <tr><td colspan="4" class="p-3">No simulated requests yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
