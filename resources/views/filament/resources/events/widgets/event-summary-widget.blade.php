<x-filament-widgets::widget>
    @if ($event)
        @php
            $registrationOpen =
                (bool) ($summary['registration_open'] ?? false);

            $capacity =
                $summary['capacity'] ?? null;

            $capacityUsed =
                $summary['capacity_used'] ?? null;

            $remainingCapacity =
                $summary['remaining_capacity'] ?? null;

            $todayDay =
                $summary['today_day'] ?? null;

            $nextDay =
                $summary['next_day'] ?? null;

            $communication =
                $summary['communication'] ?? [
                    'total' => 0,
                    'sent' => 0,
                    'failed' => 0,
                    'queued' => 0,
                ];

            $eventTypeLabel = ucfirst(
                str_replace(
                    '_',
                    ' ',
                    (string) ($event->event_type ?: 'Event')
                )
            );

            $eventStatus = (string) ($event->status ?? 'draft');

            $eventStatusLabel = ucfirst(
                str_replace('_', ' ', $eventStatus)
            );

            $eventStatusTone = match ($eventStatus) {
                'active', 'published' => 'success',
                'completed' => 'info',
                'cancelled' => 'danger',
                default => 'warning',
            };
        @endphp

        <style>
            .elive-event-summary {
                display: grid;
                gap: 1rem;
            }

            .elive-event-hero {
                position: relative;
                overflow: hidden;
                padding: 1.35rem;
                border: 1px solid rgb(219 234 254);
                border-radius: 1rem;
                background:
                    linear-gradient(
                        135deg,
                        rgb(239 246 255),
                        rgb(255 255 255) 58%,
                        rgb(238 242 255)
                    );
            }

            .dark .elive-event-hero {
                border-color: rgb(55 65 81);
                background:
                    linear-gradient(
                        135deg,
                        rgb(30 58 138 / 0.22),
                        rgb(17 24 39) 58%,
                        rgb(49 46 129 / 0.18)
                    );
            }

            .elive-event-hero::after {
                content: "";
                position: absolute;
                width: 14rem;
                height: 14rem;
                right: -5rem;
                top: -6rem;
                border-radius: 999px;
                background: rgb(99 102 241 / 0.08);
                pointer-events: none;
            }

            .elive-event-hero-content {
                position: relative;
                z-index: 1;
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 1.25rem;
                flex-wrap: wrap;
            }

            .elive-event-title-wrap {
                min-width: 0;
                flex: 1;
            }

            .elive-event-badges {
                display: flex;
                flex-wrap: wrap;
                gap: 0.45rem;
                margin-bottom: 0.65rem;
            }

            .elive-pill {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                padding: 0.32rem 0.62rem;
                border-radius: 999px;
                font-size: 0.72rem;
                font-weight: 800;
                border: 1px solid transparent;
            }

            .elive-pill-primary {
                background: rgb(239 246 255);
                color: rgb(29 78 216);
                border-color: rgb(191 219 254);
            }

            .elive-pill-success {
                background: rgb(236 253 245);
                color: rgb(4 120 87);
                border-color: rgb(167 243 208);
            }

            .elive-pill-warning {
                background: rgb(255 251 235);
                color: rgb(161 98 7);
                border-color: rgb(253 230 138);
            }

            .elive-pill-danger {
                background: rgb(254 242 242);
                color: rgb(185 28 28);
                border-color: rgb(254 202 202);
            }

            .elive-pill-info {
                background: rgb(239 246 255);
                color: rgb(3 105 161);
                border-color: rgb(186 230 253);
            }

            .dark .elive-pill-primary,
            .dark .elive-pill-success,
            .dark .elive-pill-warning,
            .dark .elive-pill-danger,
            .dark .elive-pill-info {
                border-color: rgb(255 255 255 / 0.1);
            }

            .elive-event-name {
                margin: 0;
                font-size: clamp(1.35rem, 3vw, 2rem);
                line-height: 1.15;
                font-weight: 900;
                color: rgb(15 23 42);
            }

            .dark .elive-event-name {
                color: white;
            }

            .elive-event-description {
                margin: 0.6rem 0 0;
                max-width: 52rem;
                font-size: 0.88rem;
                line-height: 1.6;
                color: rgb(100 116 139);
            }

            .dark .elive-event-description {
                color: rgb(148 163 184);
            }

            .elive-registration-state {
                min-width: 12rem;
                padding: 0.9rem 1rem;
                border: 1px solid rgb(226 232 240);
                border-radius: 0.9rem;
                background: rgb(255 255 255 / 0.82);
                backdrop-filter: blur(8px);
            }

            .dark .elive-registration-state {
                border-color: rgb(55 65 81);
                background: rgb(17 24 39 / 0.78);
            }

            .elive-state-label {
                font-size: 0.68rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                font-weight: 800;
                color: rgb(100 116 139);
            }

            .elive-state-value {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                margin-top: 0.35rem;
                font-size: 1rem;
                font-weight: 900;
                color: rgb(15 23 42);
            }

            .dark .elive-state-value {
                color: white;
            }

            .elive-state-dot {
                width: 0.55rem;
                height: 0.55rem;
                border-radius: 999px;
                background: rgb(34 197 94);
                box-shadow: 0 0 0 0.22rem rgb(34 197 94 / 0.12);
            }

            .elive-state-dot.closed {
                background: rgb(239 68 68);
                box-shadow: 0 0 0 0.22rem rgb(239 68 68 / 0.12);
            }

            .elive-info-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 0.85rem;
            }

            .elive-info-card {
                padding: 0.95rem;
                border: 1px solid rgb(226 232 240);
                border-radius: 0.9rem;
                background: white;
            }

            .dark .elive-info-card {
                border-color: rgb(55 65 81);
                background: rgb(17 24 39);
            }

            .elive-info-label {
                font-size: 0.68rem;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                font-weight: 800;
                color: rgb(100 116 139);
            }

            .elive-info-value {
                margin-top: 0.35rem;
                font-size: 0.88rem;
                font-weight: 800;
                color: rgb(15 23 42);
                word-break: break-word;
            }

            .dark .elive-info-value {
                color: white;
            }

            .elive-ops-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 0.85rem;
            }

            .elive-ops-card {
                padding: 0.95rem;
                border: 1px solid rgb(226 232 240);
                border-radius: 0.9rem;
                background: rgb(248 250 252);
            }

            .dark .elive-ops-card {
                border-color: rgb(55 65 81);
                background: rgb(31 41 55);
            }

            .elive-ops-label {
                font-size: 0.68rem;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                font-weight: 800;
                color: rgb(100 116 139);
            }

            .elive-ops-value {
                margin-top: 0.35rem;
                font-size: 1.3rem;
                line-height: 1;
                font-weight: 900;
                color: rgb(15 23 42);
            }

            .dark .elive-ops-value {
                color: white;
            }

            .elive-ops-help {
                margin-top: 0.35rem;
                font-size: 0.72rem;
                color: rgb(100 116 139);
            }

            .dark .elive-ops-help {
                color: rgb(148 163 184);
            }

            .elive-progress {
                height: 0.48rem;
                overflow: hidden;
                margin-top: 0.55rem;
                border-radius: 999px;
                background: rgb(226 232 240);
            }

            .dark .elive-progress {
                background: rgb(51 65 85);
            }

            .elive-progress-bar {
                height: 100%;
                border-radius: inherit;
                background: rgb(59 130 246);
            }

            .elive-next-day {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding: 0.95rem 1rem;
                border: 1px solid rgb(191 219 254);
                border-radius: 0.9rem;
                background: rgb(239 246 255);
            }

            .dark .elive-next-day {
                border-color: rgb(30 64 175);
                background: rgb(30 58 138 / 0.2);
            }

            .elive-next-day-title {
                font-size: 0.78rem;
                font-weight: 900;
                color: rgb(30 64 175);
            }

            .dark .elive-next-day-title {
                color: rgb(191 219 254);
            }

            .elive-next-day-detail {
                margin-top: 0.25rem;
                font-size: 0.78rem;
                color: rgb(71 85 105);
            }

            .dark .elive-next-day-detail {
                color: rgb(203 213 225);
            }

            @media (max-width: 1100px) {
                .elive-info-grid,
                .elive-ops-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 640px) {
                .elive-info-grid,
                .elive-ops-grid {
                    grid-template-columns: 1fr;
                }

                .elive-registration-state {
                    width: 100%;
                }

                .elive-next-day {
                    align-items: flex-start;
                    flex-direction: column;
                }
            }
        </style>

        <div class="elive-event-summary">
            <section class="elive-event-hero">
                <div class="elive-event-hero-content">
                    <div class="elive-event-title-wrap">
                        <div class="elive-event-badges">
                            <span class="elive-pill elive-pill-primary">
                                {{ $eventTypeLabel }}
                            </span>

                            <span class="elive-pill elive-pill-{{ $eventStatusTone }}">
                                {{ $eventStatusLabel }}
                            </span>

                            @if (($summary['event_days_count'] ?? 0) > 0)
                                <span class="elive-pill elive-pill-info">
                                    {{ number_format($summary['event_days_count']) }}
                                    event day{{ ($summary['event_days_count'] ?? 0) === 1 ? '' : 's' }}
                                </span>
                            @endif
                        </div>

                        <h2 class="elive-event-name">
                            {{ $event->name }}
                        </h2>

                        <p class="elive-event-description">
                            {{ $event->description ?: 'No event description added yet.' }}
                        </p>
                    </div>

                    <div class="elive-registration-state">
                        <div class="elive-state-label">
                            Public Registration
                        </div>

                        <div class="elive-state-value">
                            <span
                                class="elive-state-dot {{ $registrationOpen ? '' : 'closed' }}"
                            ></span>

                            {{ $registrationOpen ? 'Open' : 'Closed' }}
                        </div>
                    </div>
                </div>
            </section>

            <section class="elive-info-grid">
                <div class="elive-info-card">
                    <div class="elive-info-label">
                        Organization
                    </div>

                    <div class="elive-info-value">
                        {{ $event->organization?->name ?? 'No organization' }}
                    </div>
                </div>

                <div class="elive-info-card">
                    <div class="elive-info-label">
                        Venue
                    </div>

                    <div class="elive-info-value">
                        {{ $event->venue ?: 'No venue added' }}
                    </div>
                </div>

                <div class="elive-info-card">
                    <div class="elive-info-label">
                        Starts
                    </div>

                    <div class="elive-info-value">
                        {{ $event->starts_at?->format('D, d M Y · H:i') ?? 'No start date' }}
                    </div>
                </div>

                <div class="elive-info-card">
                    <div class="elive-info-label">
                        Ends
                    </div>

                    <div class="elive-info-value">
                        {{ $event->ends_at?->format('D, d M Y · H:i') ?? 'No end date' }}
                    </div>
                </div>
            </section>

            <section class="elive-ops-grid">
                <div class="elive-ops-card">
                    <div class="elive-ops-label">
                        Capacity
                    </div>

                    <div class="elive-ops-value">
                        {{ $capacity ? number_format($capacity) : '∞' }}
                    </div>

                    <div class="elive-ops-help">
                        @if ($capacity)
                            {{ number_format($summary['eligible_attendees'] ?? 0) }}
                            registered ·
                            {{ number_format($remainingCapacity ?? 0) }}
                            remaining
                        @else
                            Unlimited event capacity
                        @endif
                    </div>

                    @if ($capacity && $capacityUsed !== null)
                        <div class="elive-progress">
                            <div
                                class="elive-progress-bar"
                                style="width: {{ min(100, max(0, (float) $capacityUsed)) }}%;"
                            ></div>
                        </div>
                    @endif
                </div>

                <div class="elive-ops-card">
                    <div class="elive-ops-label">
                        Pending Approval
                    </div>

                    <div class="elive-ops-value">
                        {{ number_format($summary['pending_approval'] ?? 0) }}
                    </div>

                    <div class="elive-ops-help">
                        Registrations awaiting review
                    </div>
                </div>

                <div class="elive-ops-card">
                    <div class="elive-ops-label">
                        Waitlist
                    </div>

                    <div class="elive-ops-value">
                        {{ number_format($summary['waitlisted'] ?? 0) }}
                    </div>

                    <div class="elive-ops-help">
                        Waiting for available capacity
                    </div>
                </div>

                <div class="elive-ops-card">
                    <div class="elive-ops-label">
                        Communication
                    </div>

                    <div class="elive-ops-value">
                        {{ number_format($communication['sent'] ?? 0) }}
                    </div>

                    <div class="elive-ops-help">
                        Sent ·
                        {{ number_format($communication['queued'] ?? 0) }}
                        queued ·
                        {{ number_format($communication['failed'] ?? 0) }}
                        failed
                    </div>
                </div>
            </section>

            @if ($todayDay || $nextDay)
                @php
                    $dayToShow = $todayDay ?: $nextDay;
                @endphp

                <section class="elive-next-day">
                    <div>
                        <div class="elive-next-day-title">
                            {{ $todayDay ? 'Today’s Event Day' : 'Next Event Day' }}
                        </div>

                        <div class="elive-next-day-detail">
                            <strong>{{ $dayToShow->name }}</strong>

                            @if ($dayToShow->event_date)
                                · {{ $dayToShow->event_date->format('l, d M Y') }}
                            @endif

                            @if ($dayToShow->venue_name)
                                · {{ $dayToShow->venue_name }}
                            @endif
                        </div>
                    </div>

                    <div class="elive-pill elive-pill-primary">
                        {{ $dayToShow->status
                            ? ucfirst($dayToShow->status)
                            : 'Scheduled' }}
                    </div>
                </section>
            @endif
        </div>
    @endif
</x-filament-widgets::widget>
