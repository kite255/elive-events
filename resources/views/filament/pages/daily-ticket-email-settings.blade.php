<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-5">
            <h2 class="text-lg font-semibold">Administrator daily ticket email</h2>
            <p class="text-sm text-gray-500">Send the previous day's platform-wide ticket summary at 12:10 AM Tanzania time. Only super administrators may change these settings.</p>
            <label class="flex items-center gap-3">
                <input type="checkbox" wire:model="adminEnabled" class="rounded border-gray-300">
                <span>Enable daily administrator summary emails</span>
            </label>
            <div class="space-y-2">
                <label for="summary-emails" class="block font-medium text-sm">Recipient email addresses</label>
                <textarea id="summary-emails" wire:model="emails" rows="6" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" placeholder="admin@example.com&#10;finance@example.com"></textarea>
                <p class="text-xs text-gray-500">Enter one address per line (or separate with commas). Maximum 20. These recipients receive all events' financial summaries.</p>
                @error('emails') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @error('emails.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <x-filament::button type="submit" icon="heroicon-o-check">Save settings</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
