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
    <x-filament::section heading="Send a test email" description="Preview a branded system or ticket delivery email in one inbox.">
        <form wire:submit="sendTestEmail" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1">
                    <label for="system-test-email" class="block text-sm font-medium">Test recipient</label>
                    <x-filament::input.wrapper>
                        <x-filament::input id="system-test-email" type="email" wire:model="testEmail" required maxlength="254" />
                    </x-filament::input.wrapper>
                    @error('testEmail') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="space-y-1">
                    <label for="system-test-layout" class="block text-sm font-medium">Email layout</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="system-test-layout" wire:model.live="testEmailLayout">
                            <option value="system">System email</option>
                            <option value="ticket">Ticket Delivery</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    @error('testEmailLayout') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
            @if ($testEmailLayout === 'ticket')
                <div class="space-y-1">
                    <label for="system-test-event" class="block text-sm font-medium">Event template</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="system-test-event" wire:model="testEventId">
                            <option value="">Use sample event</option>
                            @foreach ($this->testEvents as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    <p class="text-xs text-gray-500">The preview uses a sample order and the ticket recovery page as its link.</p>
                    @error('testEventId') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif
            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="sendTestEmail">
                Send Test Email
            </x-filament::button>
        </form>
    </x-filament::section>

    <x-filament::section heading="Verify payout alert delivery" description="Send an explicitly marked test email to the saved payout alert recipients. This does not create or change any payout.">
        <div class="space-y-3">
            <p class="text-sm text-gray-600 dark:text-gray-300">First save your email settings, then send a test. The success message means jobs were queued, not that an email reached the inbox. Check your queue workers, failed jobs and spam folder if it does not arrive.</p>
            <x-filament::button type="button" color="gray" icon="heroicon-o-paper-airplane" wire:click="sendTest">Send Test Payout Alert</x-filament::button>
        </div>
    </x-filament::section>
</x-filament-panels::page>
