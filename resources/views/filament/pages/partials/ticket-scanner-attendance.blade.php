<x-filament::section>
    <x-slot name="heading">
        {{ $attendanceTab === 'checked_in' ? 'Checked In' : 'Pending Check-in' }}
    </x-slot>

    <div class="space-y-4">
        @if ($this->attendanceEvents->isEmpty())
            <p class="text-sm text-gray-600 dark:text-gray-400">No events with tickets are available to your account.</p>
        @else
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="space-y-1">
                    <label for="scanner-attendance-event" class="text-sm font-medium">Event</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="scanner-attendance-event" wire:model.live="attendanceEventId">
                            <option value="">Select an event</option>
                            @foreach ($this->attendanceEvents as $event)
                                <option value="{{ $event->id }}">{{ $event->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div class="space-y-1">
                    <label for="scanner-attendance-search" class="text-sm font-medium">Search tickets</label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            id="scanner-attendance-search"
                            type="search"
                            wire:model.live.debounce.300ms="attendanceSearch"
                            placeholder="Name or ticket number"
                            maxlength="120"
                        />
                    </x-filament::input.wrapper>
                </div>
            </div>

            @if ($tickets = $this->attendanceTickets)
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ number_format($tickets->total()) }} {{ \Illuminate\Support\Str::plural('ticket', $tickets->total()) }}{{ $attendanceSearch !== '' ? ' found' : '' }}
                    </p>
                    <x-filament::button type="button" size="sm" color="gray" wire:click="$refresh">
                        Refresh
                    </x-filament::button>
                </div>

                <ul class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($tickets as $ticket)
                        <li wire:key="scanner-attendance-ticket-{{ $ticket->id }}" class="flex flex-wrap items-start justify-between gap-2 py-3">
                            <div class="min-w-0">
                                <p class="break-words text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $ticket->holder_name ?: 'Name unavailable' }}
                                </p>
                                <p class="break-all text-xs text-gray-600 dark:text-gray-400">{{ $ticket->ticket_number }}</p>
                            </div>
                            <div class="space-y-1 text-right">
                                @if ($ticket->ticketType)
                                    <x-filament::badge color="info">{{ $ticket->ticketType->name }}</x-filament::badge>
                                @endif
                                @if ($ticket->used_at)
                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                        {{ $ticket->used_at->timezone('Africa/Dar_es_Salaam')->format('d M, H:i') }} EAT
                                    </p>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="py-4 text-sm text-gray-600 dark:text-gray-400">
                            {{ $attendanceSearch !== '' ? 'No tickets match your search.' : ($attendanceTab === 'checked_in' ? 'No tickets have checked in yet.' : 'No tickets are pending check-in.') }}
                        </li>
                    @endforelse
                </ul>

                @if ($tickets->hasPages())
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <x-filament::button type="button" color="gray" wire:click="previousPage('attendancePage')" :disabled="$tickets->onFirstPage()">
                            Previous
                        </x-filament::button>
                        <span class="text-sm">Page {{ $tickets->currentPage() }} of {{ $tickets->lastPage() }}</span>
                        <x-filament::button type="button" color="gray" wire:click="nextPage('attendancePage')" :disabled="! $tickets->hasMorePages()">
                            Next
                        </x-filament::button>
                    </div>
                @endif
            @else
                <p class="text-sm text-gray-600 dark:text-gray-400">Select an event to see its tickets.</p>
            @endif
        @endif
    </div>
</x-filament::section>
