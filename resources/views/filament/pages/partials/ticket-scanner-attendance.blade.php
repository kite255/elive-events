<section class="elive-scanner-attendance" aria-label="{{ $attendanceTab === 'checked_in' ? 'Checked In' : 'Pending' }}">
    @if ($this->attendanceEvents->isEmpty())
        <p class="text-sm elive-scanner-muted">No events with tickets are available to your account.</p>
    @else
        <div class="elive-scanner-filters">
            <div class="space-y-1">
                <label for="scanner-attendance-event" class="text-sm font-medium">Event</label>
                <select id="scanner-attendance-event" wire:model.live="attendanceEventId">
                    <option value="">Select an event</option>
                    @foreach ($this->attendanceEvents as $event)
                        <option value="{{ $event->id }}">{{ $event->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1">
                <label for="scanner-attendance-search" class="text-sm font-medium">Search tickets</label>
                <input
                    id="scanner-attendance-search"
                    type="search"
                    wire:model.live.debounce.300ms="attendanceSearch"
                    placeholder="Name or ticket number"
                    maxlength="120"
                >
            </div>
        </div>

        @if ($tickets = $this->attendanceTickets)
            <div class="elive-scanner-list-toolbar">
                <p class="text-sm elive-scanner-muted">
                    {{ number_format($tickets->total()) }} {{ \Illuminate\Support\Str::plural('ticket', $tickets->total()) }}{{ $attendanceSearch !== '' ? ' found' : '' }}
                </p>
                <x-filament::button type="button" size="sm" color="gray" wire:click="$refresh">
                    Refresh
                </x-filament::button>
            </div>

            <ul class="elive-scanner-list">
                @forelse ($tickets as $ticket)
                    <li wire:key="scanner-attendance-ticket-{{ $ticket->id }}" class="elive-scanner-list-row {{ $attendanceTab === 'checked_in' ? 'elive-scanner-list-row--checked' : '' }}">
                        <div class="elive-scanner-list-identity">
                            <p class="elive-scanner-holder">
                                {{ $ticket->holder_name ?: 'Name unavailable' }}
                            </p>
                            <p class="elive-scanner-number">{{ $ticket->ticket_number }}</p>
                        </div>
                        <div class="elive-scanner-list-meta">
                            @if ($ticket->ticketType)
                                <span @class(['elive-scanner-badge', 'elive-scanner-badge--vip' => strtolower(trim($ticket->ticketType->name)) === 'vip'])>{{ $ticket->ticketType->name }}</span>
                            @endif
                            @if ($ticket->used_at)
                                <p class="text-xs elive-scanner-muted">
                                    {{ $ticket->used_at->timezone('Africa/Dar_es_Salaam')->format('d M, H:i') }} EAT
                                </p>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="py-4 text-sm elive-scanner-muted">
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
            <p class="text-sm elive-scanner-muted">Select an event to see its tickets.</p>
        @endif
    @endif
</section>
