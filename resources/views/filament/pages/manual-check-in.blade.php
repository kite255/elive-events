<x-filament-panels::page>
    <div class="space-y-6">

        <form wire:submit.prevent="searchAttendee" class="space-y-6">
            {{ $this->form }}

            <div class="flex gap-3">
                <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                    Search Attendee
                </x-filament::button>

                <x-filament::button
                    type="button"
                    color="gray"
                    wire:click="resetSearch"
                    icon="heroicon-o-arrow-path"
                >
                    Reset
                </x-filament::button>
            </div>
        </form>

        @if ($attendee)
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ $attendee->full_name }}
                        </h2>

                        <div class="mt-3 grid gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <p><strong>Phone:</strong> {{ $attendee->phone ?? 'N/A' }}</p>
                            <p><strong>Email:</strong> {{ $attendee->email ?? 'N/A' }}</p>
                            <p><strong>Organization:</strong> {{ $attendee->organization_name ?? 'N/A' }}</p>
                            <p><strong>Position:</strong> {{ $attendee->position ?? 'N/A' }}</p>
                            <p><strong>Badge Number:</strong> {{ $attendee->badge_number ?? 'N/A' }}</p>
                            <p><strong>Status:</strong> {{ $attendee->status ?? 'N/A' }}</p>
                            <p><strong>Checked In At:</strong> {{ $attendee->checked_in_at ?? 'Not checked in' }}</p>
                        </div>
                    </div>

                    <div>
                        @if ($alreadyCheckedIn)
                            <span class="rounded-full bg-red-100 px-4 py-2 text-sm font-semibold text-red-700">
                                Already Checked In
                            </span>
                        @else
                            <span class="rounded-full bg-green-100 px-4 py-2 text-sm font-semibold text-green-700">
                                Ready for Check-In
                            </span>
                        @endif
                    </div>
                </div>

                <div class="mt-6">
                    @if (! $alreadyCheckedIn)
                        <x-filament::button
                            wire:click="confirmCheckIn"
                            color="success"
                            icon="heroicon-o-check-circle"
                        >
                            Confirm Check-In
                        </x-filament::button>
                    @else
                        <x-filament::button disabled color="gray">
                            Check-In Completed
                        </x-filament::button>
                    @endif
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>