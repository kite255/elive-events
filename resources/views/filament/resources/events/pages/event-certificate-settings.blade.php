<x-filament-panels::page>
    @php($event = $this->event())
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold">{{ $event->name }}</h2>
            <p class="text-sm text-gray-500">Manage certificate availability and eligibility for this event.</p>
        </div>
        <x-filament::button color="gray" tag="a" :href="\App\Filament\Resources\Events\EventResource::getUrl('workspace', ['record' => $event])">Back to event</x-filament::button>
        <x-filament::button tag="a" :href="\App\Filament\Resources\Events\EventResource::getUrl('certificate-templates', ['record' => $event])">Certificate templates</x-filament::button>
    </div>

    <form wire:submit="saveSettings" class="space-y-4">
        <x-filament::section>
            <x-slot name="heading">Certificate availability</x-slot>
            <label class="flex items-center gap-3 text-sm font-medium">
                <input type="checkbox" wire:model.live="enabled" class="rounded border-gray-300">
                Enable certificates for this event
            </label>
            <p class="mt-2 text-sm text-gray-500">This setting does not automatically generate, issue or send certificates.</p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Eligibility</x-slot>
            <fieldset @disabled(! $enabled) class="space-y-3">
                @foreach ([
                    'registered' => ['Registered attendees', 'Accepted registrations, including approved and checked-in attendees.'],
                    'confirmed' => ['Confirmed attendees', 'Approved, confirmed or checked-in attendees.'],
                    'checked_in' => ['Checked-in attendees', 'Attendees with recorded event attendance.'],
                    'manual' => ['Manual selection', 'Select eligible attendees individually when issuing certificates.'],
                ] as $value => [$label, $description])
                    <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <input type="radio" wire:model="eligibilityRule" value="{{ $value }}" class="mt-1 border-gray-300">
                        <span><span class="block text-sm font-medium">{{ $label }}</span><span class="block text-sm text-gray-500">{{ $description }}</span></span>
                    </label>
                @endforeach
            </fieldset>
            @error('eligibility_rule') <p class="mt-2 text-sm text-danger-600">{{ $message }}</p> @enderror
        </x-filament::section>

        <div class="flex justify-end">
            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="saveSettings">Save settings</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
