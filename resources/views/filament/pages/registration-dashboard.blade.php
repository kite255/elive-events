<x-filament-panels::page>
    <style>
        .er-grid{display:grid;gap:1rem;grid-template-columns:repeat(3,minmax(0,1fr))}
        .er-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:1.2rem;min-width:0}
        .dark .er-card{background:#111827;border-color:#374151}
        .er-hero{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;background:#161943;color:white;padding:1.5rem;border-radius:16px}
        .er-hero h2{font-size:1.5rem;font-weight:700}
        .er-label{font-size:.85rem;color:#64748b}
        .dark .er-label{color:#94a3b8}
        .er-value{font-size:1.9rem;font-weight:700;line-height:1.4;font-variant-numeric:tabular-nums}
        .er-heading{font-weight:700;font-size:1.05rem;margin-bottom:1rem}
        .er-actions{display:flex;gap:.65rem;flex-wrap:wrap;align-items:center}
        .er-select{max-width:100%;min-width:0;border:1px solid #cbd5e1;border-radius:8px;padding:.6rem;background:transparent}
        .dark .er-select{background:#111827;border-color:#475569}
        .er-progress{height:10px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin:.8rem 0}
        .dark .er-progress{background:#374151}
        .er-progress span{display:block;height:100%;background:#007ab2;border-radius:999px}
        .er-split{display:grid;grid-template-columns:2fr 1fr;gap:1rem}
        .er-table{width:100%;border-collapse:collapse;text-align:left;font-size:.875rem}
        .er-table th,.er-table td{padding:.75rem .5rem;border-bottom:1px solid #e2e8f0;vertical-align:middle}
        .dark .er-table th,.dark .er-table td{border-color:#374151}
        .er-pill{display:inline-block;padding:.2rem .55rem;border-radius:6px;background:#e2e8f0;color:#334155;font-size:.75rem;font-weight:600}
        .er-pill-ok{background:#dcfce7;color:#166534}
        .er-pill-wait{background:#fef3c7;color:#92400e}
        .er-row{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.6rem}
        .er-link{color:#007ab2;font-weight:600}
        .dark .er-link{color:#7dd3fc}
        @media(max-width:1000px){.er-split{grid-template-columns:1fr}}
        @media(max-width:650px){.er-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.er-card{padding:.85rem}.er-value{font-size:1.5rem}.er-hero{padding:1rem}}
    </style>

    <div style="display:grid;gap:1rem">
        <section class="er-hero">
            <div>
                <div style="font-size:.8rem;opacity:.8">eLive Events / Registration</div>
                <h2>{{ $selectedEvent->name }}</h2>
                <div style="font-size:.875rem;margin-top:.35rem">
                    {{ $selectedEvent->starts_at?->format('D, d M Y') ?? 'Date not set' }}
                    · {{ $selectedEvent->venue ?: 'Venue not set' }}
                </div>
            </div>
            <span class="er-pill {{ $selectedEvent->registration_is_open ? 'er-pill-ok' : '' }}">
                {{ $selectedEvent->registration_is_open ? 'Registration open' : 'Registration closed' }}
            </span>
        </section>

        <section class="er-card er-row" style="flex-wrap:wrap;margin-bottom:0">
            <div style="display:flex;align-items:center;gap:.7rem;flex-wrap:wrap">
                <label for="registration-dashboard-event" style="font-weight:600">Event</label>
                <select id="registration-dashboard-event" class="er-select" wire:model.live="eventId">
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}">{{ $event->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="er-actions">
                <x-filament::button color="gray" wire:click="$refresh" wire:loading.attr="disabled" icon="heroicon-o-arrow-path">Refresh</x-filament::button>
                <x-filament::button tag="a" href="{{ $registrationUrl }}" target="_blank" rel="noopener noreferrer" color="gray" icon="heroicon-o-arrow-top-right-on-square">Public form</x-filament::button>
                @if ($manageUrl)
                    <x-filament::button tag="a" href="{{ $manageUrl }}" icon="heroicon-o-cog-6-tooth">Event settings</x-filament::button>
                @endif
            </div>
        </section>

        <section class="er-grid" aria-label="Registration statistics">
            @foreach ($stats as $label => $value)
                <div class="er-card" wire:key="registration-stat-{{ $loop->index }}">
                    <div class="er-label">{{ $label }}</div>
                    <div class="er-value">{{ number_format($value) }}</div>
                </div>
            @endforeach
        </section>

        <section class="er-card">
            <div class="er-row">
                <h3 style="font-weight:700">Registration capacity</h3>
                <span class="er-label">{{ number_format($occupied) }} / {{ $capacity > 0 ? number_format($capacity) : 'Unlimited' }}</span>
            </div>
            @if ($capacity > 0)
                <div class="er-progress" role="progressbar" aria-label="Capacity used" aria-valuemin="0" aria-valuemax="{{ $capacity }}" aria-valuenow="{{ min($occupied, $capacity) }}">
                    <span style="width:{{ min(100, $occupied / $capacity * 100) }}%"></span>
                </div>
                <div class="er-label">{{ number_format(max(0, $capacity - $occupied)) }} available spaces · Pending approvals reserve spaces.</div>
                @if ($occupied > $capacity)
                    <div style="color:#dc2626;font-weight:600">Capacity exceeded by {{ number_format($occupied - $capacity) }}.</div>
                @endif
            @else
                <p class="er-label">No registration capacity limit configured.</p>
            @endif
        </section>

        <div class="er-split">
            <section class="er-card">
                <div class="er-row">
                    <h3 style="font-weight:700">Recent registrations</h3>
                    @if ($attendeeUrl)
                        <a class="er-link" href="{{ $attendeeUrl }}">Manage attendees</a>
                    @endif
                </div>
                <div style="overflow-x:auto">
                    <table class="er-table">
                        <thead><tr><th>Participant</th><th>Status</th><th>Registered</th></tr></thead>
                        <tbody>
                        @forelse ($recent as $attendee)
                            <tr wire:key="registration-recent-{{ $attendee->id }}">
                                <td><div style="font-weight:600">{{ $attendee->full_name }}</div><div class="er-label">{{ $attendee->category?->name ?? 'Uncategorized' }}</div></td>
                                <td><span class="er-pill {{ $attendee->isApproved() ? 'er-pill-ok' : (in_array($attendee->status, ['pending_approval','waitlisted'], true) ? 'er-pill-wait' : '') }}">{{ ucfirst(str_replace('_', ' ', $attendee->status ?? 'unknown')) }}</span></td>
                                <td>{{ $attendee->registered_at?->format('d M, H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="er-label">No registrations recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="er-card">
                <h3 class="er-heading">Registration sources</h3>
                @forelse ($sources as $source)
                    <div class="er-row">
                        <span class="er-label">{{ ucfirst(str_replace('_', ' ', $source->registration_source ?: 'Unknown')) }}</span>
                        <strong>{{ number_format($source->total) }}</strong>
                    </div>
                @empty
                    <p class="er-label">No registration sources yet.</p>
                @endforelse
                <p class="er-label" style="margin-top:1rem">Accepted status is not proof of payment. Badge records count stored badge paths, not verified files.</p>
            </section>
        </div>
    </div>
</x-filament-panels::page>
