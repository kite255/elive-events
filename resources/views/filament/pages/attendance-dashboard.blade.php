<x-filament-panels::page>
    @php
        $events = $this->getAvailableEvents();
        $eventDays = $this->getAvailableEventDays();
        $selectedEvent = $this->getSelectedEvent();
        $selectedEventDay = $this->getSelectedEventDay();

        $maxPointCount = max(
            1,
            (int) ($checkInsByPoint->max('check_ins_count') ?? 0)
        );
    @endphp

    <style>
        .elive-attendance-dashboard {
            display: grid;
            gap: 1.25rem;
        }

        .elive-panel {
            overflow: hidden;
            border: 1px solid rgb(226 232 240);
            border-radius: 1rem;
            background: white;
            box-shadow: 0 1px 3px rgb(15 23 42 / 0.06);
        }

        .dark .elive-panel {
            border-color: rgb(55 65 81);
            background: rgb(17 24 39);
        }

        .elive-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgb(226 232 240);
        }

        .dark .elive-panel-header {
            border-color: rgb(55 65 81);
        }

        .elive-panel-body {
            padding: 1.25rem;
        }

        .elive-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
            color: rgb(15 23 42);
        }

        .dark .elive-title {
            color: white;
        }

        .elive-subtitle {
            margin: 0.25rem 0 0;
            font-size: 0.875rem;
            color: rgb(100 116 139);
        }

        .dark .elive-subtitle {
            color: rgb(148 163 184);
        }

        .elive-dashboard-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.25rem;
            border: 1px solid rgb(219 234 254);
            border-radius: 1rem;
            background:
                linear-gradient(135deg, rgb(239 246 255), rgb(255 255 255));
        }

        .dark .elive-dashboard-hero {
            border-color: rgb(55 65 81);
            background:
                linear-gradient(135deg, rgb(30 58 138 / 0.28), rgb(17 24 39));
        }

        .elive-hero-title {
            margin: 0;
            font-size: clamp(1.25rem, 2.5vw, 1.8rem);
            line-height: 1.15;
            font-weight: 900;
            color: rgb(15 23 42);
        }

        .dark .elive-hero-title {
            color: white;
        }

        .elive-hero-meta {
            margin-top: 0.45rem;
            color: rgb(100 116 139);
            font-size: 0.875rem;
        }

        .dark .elive-hero-meta {
            color: rgb(148 163 184);
        }

        .elive-live-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.45rem 0.75rem;
            border-radius: 999px;
            background: rgb(220 252 231);
            color: rgb(21 128 61);
            font-size: 0.78rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .elive-live-pill::before {
            content: "";
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 999px;
            background: rgb(34 197 94);
            box-shadow: 0 0 0 0.22rem rgb(34 197 94 / 0.15);
        }

        .elive-filter-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr) auto;
            gap: 1rem;
            align-items: end;
        }

        .elive-form-group {
            display: grid;
            gap: 0.45rem;
        }

        .elive-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: rgb(51 65 85);
        }

        .dark .elive-label {
            color: rgb(203 213 225);
        }

        .elive-select {
            width: 100%;
            min-height: 2.75rem;
            padding: 0.65rem 0.8rem;
            border: 1px solid rgb(203 213 225);
            border-radius: 0.75rem;
            background: white;
            color: rgb(15 23 42);
            outline: none;
        }

        .dark .elive-select {
            border-color: rgb(75 85 99);
            background: rgb(31 41 55);
            color: white;
        }

        .elive-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            min-height: 2.75rem;
            padding: 0.65rem 1rem;
            border: 0;
            border-radius: 0.75rem;
            font-size: 0.82rem;
            font-weight: 800;
            cursor: pointer;
        }

        .elive-button-primary {
            background: rgb(0 78 150);
            color: white;
        }

        .elive-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        .elive-kpi {
            position: relative;
            overflow: hidden;
            padding: 1.05rem 1.1rem;
            border: 1px solid rgb(226 232 240);
            border-radius: 0.95rem;
            background: white;
        }

        .dark .elive-kpi {
            border-color: rgb(55 65 81);
            background: rgb(17 24 39);
        }

        .elive-kpi::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            background: rgb(59 130 246);
        }

        .elive-kpi-success::before {
            background: rgb(34 197 94);
        }

        .elive-kpi-warning::before {
            background: rgb(245 158 11);
        }

        .elive-kpi-purple::before {
            background: rgb(139 92 246);
        }

        .elive-kpi-label {
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: rgb(100 116 139);
        }

        .elive-kpi-value {
            margin-top: 0.35rem;
            font-size: 1.8rem;
            line-height: 1;
            font-weight: 900;
            color: rgb(15 23 42);
        }

        .dark .elive-kpi-value {
            color: white;
        }

        .elive-kpi-help {
            margin-top: 0.45rem;
            font-size: 0.77rem;
            color: rgb(100 116 139);
        }

        .dark .elive-kpi-help {
            color: rgb(148 163 184);
        }

        .elive-progress {
            height: 0.7rem;
            overflow: hidden;
            border-radius: 999px;
            background: rgb(226 232 240);
        }

        .dark .elive-progress {
            background: rgb(51 65 85);
        }

        .elive-progress-bar {
            height: 100%;
            border-radius: inherit;
            background: rgb(34 197 94);
            transition: width 0.25s ease;
        }

        .elive-progress-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 0.7rem;
        }

        .elive-progress-value {
            font-size: 1.25rem;
            font-weight: 900;
            color: rgb(15 23 42);
        }

        .dark .elive-progress-value {
            color: white;
        }

        .elive-secondary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }

        .elive-mini-stat {
            padding: 0.95rem;
            border: 1px solid rgb(226 232 240);
            border-radius: 0.85rem;
            background: rgb(248 250 252);
        }

        .dark .elive-mini-stat {
            border-color: rgb(55 65 81);
            background: rgb(31 41 55);
        }

        .elive-mini-label {
            font-size: 0.72rem;
            font-weight: 800;
            color: rgb(100 116 139);
            text-transform: uppercase;
        }

        .elive-mini-value {
            margin-top: 0.3rem;
            font-size: 1.45rem;
            font-weight: 900;
            color: rgb(15 23 42);
        }

        .dark .elive-mini-value {
            color: white;
        }

        .elive-breakdown-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.25rem;
        }

        .elive-breakdown-list {
            display: grid;
            gap: 0.85rem;
        }

        .elive-breakdown-item {
            padding: 0.85rem;
            border: 1px solid rgb(226 232 240);
            border-radius: 0.85rem;
        }

        .dark .elive-breakdown-item {
            border-color: rgb(55 65 81);
        }

        .elive-breakdown-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .elive-breakdown-name {
            font-size: 0.88rem;
            font-weight: 800;
            color: rgb(15 23 42);
        }

        .dark .elive-breakdown-name {
            color: white;
        }

        .elive-breakdown-count {
            font-size: 0.8rem;
            font-weight: 800;
            color: rgb(71 85 105);
        }

        .dark .elive-breakdown-count {
            color: rgb(203 213 225);
        }

        .elive-small-progress {
            height: 0.45rem;
            overflow: hidden;
            margin-top: 0.6rem;
            border-radius: 999px;
            background: rgb(226 232 240);
        }

        .dark .elive-small-progress {
            background: rgb(51 65 85);
        }

        .elive-small-progress > div {
            height: 100%;
            border-radius: inherit;
            background: rgb(59 130 246);
        }

        .elive-table-wrap {
            overflow-x: auto;
        }

        .elive-table {
            width: 100%;
            border-collapse: collapse;
            white-space: nowrap;
        }

        .elive-table th,
        .elive-table td {
            padding: 0.8rem 1rem;
            border-bottom: 1px solid rgb(226 232 240);
            text-align: left;
            font-size: 0.82rem;
        }

        .dark .elive-table th,
        .dark .elive-table td {
            border-color: rgb(55 65 81);
        }

        .elive-table th {
            background: rgb(248 250 252);
            color: rgb(71 85 105);
            font-weight: 800;
        }

        .dark .elive-table th {
            background: rgb(15 23 42);
            color: rgb(203 213 225);
        }

        .elive-table td {
            color: rgb(51 65 85);
        }

        .dark .elive-table td {
            color: rgb(229 231 235);
        }

        .elive-empty {
            padding: 2.5rem 1.5rem;
            text-align: center;
            color: rgb(100 116 139);
        }

        @media (max-width: 1100px) {
            .elive-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .elive-breakdown-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            .elive-dashboard-hero,
            .elive-panel-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .elive-filter-grid,
            .elive-kpi-grid,
            .elive-secondary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div
        class="elive-attendance-dashboard"
        wire:poll.15s="refreshDashboard"
    >
        <section class="elive-dashboard-hero">
            <div>
                <h2 class="elive-hero-title">
                    {{ $selectedEvent?->name ?? 'Attendance Dashboard' }}
                </h2>

                <div class="elive-hero-meta">
                    @if ($selectedEventDay)
                        {{ $selectedEventDay->name }}
                        @if ($selectedEventDay->event_date)
                            · {{ $selectedEventDay->event_date->format('D, d M Y') }}
                        @endif
                    @elseif ($eventDays->isNotEmpty())
                        Overall event attendance
                    @else
                        Event-level attendance
                    @endif
                </div>
            </div>

            <div class="elive-live-pill">
                Live Attendance
            </div>
        </section>

        <section class="elive-panel">
            <div class="elive-panel-header">
                <div>
                    <h2 class="elive-title">Dashboard Filters</h2>
                    <p class="elive-subtitle">
                        View the whole event or select a specific event day.
                    </p>
                </div>

                <div wire:loading>
                    Updating...
                </div>
            </div>

            <div class="elive-panel-body">
                <div class="elive-filter-grid">
                    <div class="elive-form-group">
                        <label class="elive-label" for="attendance-dashboard-event">
                            Event
                        </label>

                        <select
                            id="attendance-dashboard-event"
                            class="elive-select"
                            wire:model.live="eventId"
                        >
                            @foreach ($events as $event)
                                <option value="{{ $event->getKey() }}">
                                    {{ $event->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="elive-form-group">
                        <label class="elive-label" for="attendance-dashboard-day">
                            Event Day
                        </label>

                        <select
                            id="attendance-dashboard-day"
                            class="elive-select"
                            wire:model.live="eventDayId"
                            @disabled(! $eventId || $eventDays->isEmpty())
                        >
                            <option value="">
                                {{ $eventDays->isEmpty() ? 'General event attendance' : 'Overall event' }}
                            </option>

                            @foreach ($eventDays as $day)
                                <option value="{{ $day->getKey() }}">
                                    {{ $day->name }}
                                    @if ($day->event_date)
                                        — {{ $day->event_date->format('d M Y') }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button
                        type="button"
                        class="elive-button elive-button-primary"
                        wire:click="refreshDashboard"
                        wire:loading.attr="disabled"
                    >
                        Refresh
                    </button>
                </div>
            </div>
        </section>

        <section class="elive-kpi-grid">
            <div class="elive-kpi">
                <div class="elive-kpi-label">Expected</div>
                <div class="elive-kpi-value">
                    {{ number_format($totalAttendees) }}
                </div>
                <div class="elive-kpi-help">
                    Eligible registered attendees
                </div>
            </div>

            <div class="elive-kpi elive-kpi-success">
                <div class="elive-kpi-label">Checked In</div>
                <div class="elive-kpi-value">
                    {{ number_format($checkedInAttendees) }}
                </div>
                <div class="elive-kpi-help">
                    Unique attendees checked in
                </div>
            </div>

            <div class="elive-kpi elive-kpi-warning">
                <div class="elive-kpi-label">Remaining</div>
                <div class="elive-kpi-value">
                    {{ number_format($notCheckedInAttendees) }}
                </div>
                <div class="elive-kpi-help">
                    Still expected to arrive
                </div>
            </div>

            <div class="elive-kpi elive-kpi-purple">
                <div class="elive-kpi-label">Attendance Rate</div>
                <div class="elive-kpi-value">
                    {{ number_format($attendanceRate, 1) }}%
                </div>
                <div class="elive-kpi-help">
                    Checked in ÷ expected
                </div>
            </div>
        </section>

        <section class="elive-panel">
            <div class="elive-panel-body">
                <div class="elive-progress-row">
                    <div>
                        <div class="elive-title">Attendance Progress</div>
                        <div class="elive-subtitle">
                            {{ number_format($checkedInAttendees) }} of
                            {{ number_format($totalAttendees) }} attendees
                        </div>
                    </div>

                    <div class="elive-progress-value">
                        {{ number_format($attendanceRate, 1) }}%
                    </div>
                </div>

                <div class="elive-progress">
                    <div
                        class="elive-progress-bar"
                        style="width: {{ min(100, max(0, $attendanceRate)) }}%;"
                    ></div>
                </div>
            </div>
        </section>

        <section class="elive-secondary-grid">
            <div class="elive-mini-stat">
                <div class="elive-mini-label">Checked In Today</div>
                <div class="elive-mini-value">
                    {{ number_format($todayCheckIns) }}
                </div>
            </div>

            <div class="elive-mini-stat">
                <div class="elive-mini-label">QR / Badge Scans</div>
                <div class="elive-mini-value">
                    {{ number_format($qrCheckIns) }}
                </div>
            </div>

            <div class="elive-mini-stat">
                <div class="elive-mini-label">Manual Check-ins</div>
                <div class="elive-mini-value">
                    {{ number_format($manualCheckIns) }}
                </div>
            </div>
        </section>

        <section class="elive-breakdown-grid">
            <div class="elive-panel">
                <div class="elive-panel-header">
                    <div>
                        <h2 class="elive-title">Attendance by Event Day</h2>
                        <p class="elive-subtitle">
                            Expected versus checked-in attendees for each day.
                        </p>
                    </div>
                </div>

                <div class="elive-panel-body">
                    @if ($attendanceByDay->isNotEmpty())
                        <div class="elive-breakdown-list">
                            @foreach ($attendanceByDay as $row)
                                <div class="elive-breakdown-item">
                                    <div class="elive-breakdown-head">
                                        <div>
                                            <div class="elive-breakdown-name">
                                                {{ $row['name'] }}
                                            </div>

                                            @if ($row['date'] instanceof \Carbon\CarbonInterface)
                                                <div class="elive-subtitle">
                                                    {{ $row['date']->format('D, d M Y') }}
                                                </div>
                                            @endif
                                        </div>

                                        <div class="elive-breakdown-count">
                                            {{ number_format($row['checked_in']) }}
                                            /
                                            {{ number_format($row['expected']) }}
                                            · {{ number_format($row['rate'], 1) }}%
                                        </div>
                                    </div>

                                    <div class="elive-small-progress">
                                        <div style="width: {{ min(100, max(0, $row['rate'])) }}%;"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="elive-empty">
                            No event-day attendance data available.
                        </div>
                    @endif
                </div>
            </div>

            <div class="elive-panel">
                <div class="elive-panel-header">
                    <div>
                        <h2 class="elive-title">Attendance by Category</h2>
                        <p class="elive-subtitle">
                            Attendance performance for VIPs, delegates, staff, and other categories.
                        </p>
                    </div>
                </div>

                <div class="elive-panel-body">
                    @if ($attendanceByCategory->isNotEmpty())
                        <div class="elive-breakdown-list">
                            @foreach ($attendanceByCategory as $row)
                                <div class="elive-breakdown-item">
                                    <div class="elive-breakdown-head">
                                        <div class="elive-breakdown-name">
                                            {{ $row['name'] }}
                                        </div>

                                        <div class="elive-breakdown-count">
                                            {{ number_format($row['checked_in']) }}
                                            /
                                            {{ number_format($row['expected']) }}
                                            · {{ number_format($row['rate'], 1) }}%
                                        </div>
                                    </div>

                                    <div class="elive-small-progress">
                                        <div style="width: {{ min(100, max(0, $row['rate'])) }}%;"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="elive-empty">
                            No category attendance data available.
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="elive-panel">
            <div class="elive-panel-header">
                <div>
                    <h2 class="elive-title">Check-in Points</h2>
                    <p class="elive-subtitle">
                        Number of attendance records processed through each entrance or station.
                    </p>
                </div>
            </div>

            <div class="elive-panel-body">
                @if ($checkInsByPoint->isNotEmpty())
                    <div class="elive-breakdown-list">
                        @foreach ($checkInsByPoint as $point)
                            @php
                                $pointCount = (int) ($point->check_ins_count ?? 0);
                                $pointRate = ($pointCount / $maxPointCount) * 100;
                            @endphp

                            <div class="elive-breakdown-item">
                                <div class="elive-breakdown-head">
                                    <div class="elive-breakdown-name">
                                        {{ $point->name }}
                                    </div>

                                    <div class="elive-breakdown-count">
                                        {{ number_format($pointCount) }}
                                    </div>
                                </div>

                                <div class="elive-small-progress">
                                    <div style="width: {{ min(100, max(0, $pointRate)) }}%;"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="elive-empty">
                        No check-in point activity is available.
                    </div>
                @endif
            </div>
        </section>

        <section class="elive-panel">
            <div class="elive-panel-header">
                <div>
                    <h2 class="elive-title">Recent Check-ins</h2>
                    <p class="elive-subtitle">
                        Latest attendance activity for the selected event context.
                    </p>
                </div>
            </div>

            @if ($recentCheckIns->isNotEmpty())
                <div class="elive-table-wrap">
                    <table class="elive-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Attendee</th>
                                <th>Category</th>
                                <th>Day</th>
                                <th>Badge</th>
                                <th>Point</th>
                                <th>Method</th>
                                <th>Officer</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($recentCheckIns as $checkIn)
                                <tr>
                                    <td>
                                        {{ $checkIn->checked_in_at?->format('H:i:s') ?? '—' }}
                                    </td>

                                    <td>
                                        <strong>
                                            {{ $checkIn->attendee?->full_name ?? 'Unknown attendee' }}
                                        </strong>
                                    </td>

                                    <td>
                                        {{ $checkIn->attendee?->category?->name ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $checkIn->eventDay?->name ?? 'General' }}
                                    </td>

                                    <td>
                                        {{ $checkIn->attendee?->badge_number ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $checkIn->checkInPoint?->name ?? '—' }}
                                    </td>

                                    <td>
                                        {{ ucwords(str_replace('_', ' ', $checkIn->method ?? '')) }}
                                    </td>

                                    <td>
                                        {{ $checkIn->checkedInBy?->name ?? 'System' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="elive-empty">
                    No check-ins have been recorded for this selection.
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
