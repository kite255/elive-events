<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <x-filament::section heading="Administrator email notifications" description="Configure who receives new organizer payout request alerts. Only super administrators can update recipients.">
            <div class="space-y-4">
                <label class="flex items-center gap-3">
                    <input type="checkbox" wire:model="payoutEnabled" class="rounded border-gray-300">
                    <span>Send new payout request alerts to configured recipients</span>
                </label>
                <div class="space-y-2">
                    <label for="payout-emails" class="block text-sm font-medium">Finance / administrator recipients</label>
                    <textarea id="payout-emails" wire:model="payoutEmails" rows="5" class="w-full rounded-lg border-gray-300 dark:bg-gray-900" placeholder="finance@example.com&#10;admin@example.com"></textarea>
                    <p class="text-xs text-gray-500">One email per line, or separate with commas. Maximum 20. These emails include payout amounts and event details. Organizer status emails still go to the organizer's own account email.</p>
                    @error('payoutEmails') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @error('emails') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    @error('emails.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <p class="text-xs text-gray-500">SMTP server credentials and sender address remain in Coolify environment variables. Daily ticket report recipients are configured separately under Reports → Daily Ticket Email Settings.</p>
                <x-filament::button type="submit" icon="heroicon-o-check">Save email settings</x-filament::button>
            </div>
        </x-filament::section>
    </form>
    <x-filament::section heading="Verify email delivery" description="Send an explicitly marked test email to the saved payout alert recipients. This does not create or change any payout.">
        <div class="space-y-3">
            <p class="text-sm text-gray-600 dark:text-gray-300">First save your email settings, then send a test. The success message means jobs were queued, not that an email reached the inbox. Check your queue workers, failed jobs and spam folder if it does not arrive.</p>
            <x-filament::button type="button" color="gray" icon="heroicon-o-paper-airplane" wire:click="sendTest">Send Test Payout Alert</x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
