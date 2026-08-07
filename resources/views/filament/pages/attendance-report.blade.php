<x-filament-panels::page>
    @php
        $events = $this->events;
        $eventDays = $this->eventDays;
        $selectedEventDay = $this->selectedEventDay;
        $categories = $this->categories;
        $checkInPoints = $this->checkInPoints;
        $methods = $this->methods;
        $officers = $this->officers;
        $attendees = $this->attendees;
        $latestCheckIns = $this->latestCheckIns;
        $summary = $this->summary;
    @endphp

    <style>
        .elive-attendance-report {
            display: grid;
            gap: 1.25rem;
        }

        .elive-panel {
            overflow: hidden;
            border: 1px solid rgb(229 231 235);
            border-radius: 1rem;
            background: white;
            box-shadow: 0 1px 3px rgb(15 23 42 / 0.08);
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
            border-bottom: 1px solid rgb(229 231 235);
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
            color: rgb(17 24 39);
        }

        .dark .elive-title {
            color: white;
        }

        .elive-subtitle {
            margin: 0.25rem 0 0;
            font-size: 0.875rem;
            color: rgb(107 114 128);
        }

        .dark .elive-subtitle {
            color: rgb(156 163 175);
        }

        .elive-filter-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        .elive-filter-search {
            grid-column: span 3;
        }

        .elive-form-group {
            display: grid;
            gap: 0.45rem;
        }

        .elive-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: rgb(55 65 81);
        }

        .dark .elive-label {
            color: rgb(209 213 219);
        }

        .elive-input,
        .elive-select {
            width: 100%;
            min-height: 2.75rem;
            padding: 0.65rem 0.8rem;
            border: 1px solid rgb(209 213 219);
            border-radius: 0.75rem;
            background: white;
            color: rgb(17 24 39);
            outline: none;
        }

        .dark .elive-input,
        .dark .elive-select {
            border-color: rgb(75 85 99);
            background: rgb(31 41 55);
            color: white;
        }

        .elive-loading {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.82rem;
            font-weight: 700;
            color: rgb(79 70 229);
        }

        .elive-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 0.75rem;
        }

        .elive-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.75rem;
            padding: 0.65rem 1rem;
            border: 0;
            border-radius: 0.75rem;
            font-size: 0.82rem;
            font-weight: 800;
            cursor: pointer;
        }

        .elive-button-primary {
            background: rgb(22 163 74);
            color: white;
        }

        .elive-button-secondary {
            background: rgb(241 245 249);
            color: rgb(51 65 85);
        }

        .dark .elive-button-secondary {
            background: rgb(51 65 85);
            color: white;
        }

        .elive-stat-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        .elive-stat {
            padding: 1rem;
            border: 1px solid rgb(229 231 235);
            border-radius: 0.9rem;
            background: white;
        }

        .dark .elive-stat {
            border-color: rgb(55 65 81);
            background: rgb(17 24 39);
        }

        .elive-stat-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: rgb(107 114 128);
        }

        .elive-stat-value {
            margin-top: 0.35rem;
            font-size: 1.65rem;
            font-weight: 900;
            color: rgb(17 24 39);
        }

        .dark .elive-stat-value {
            color: white;
        }

        .elive-day-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.9rem 1rem;
            border: 1px solid rgb(191 219 254);
            border-radius: 0.9rem;
            background: rgb(239 246 255);
            color: rgb(30 64 175);
        }

        .dark .elive-day-banner {
            border-color: rgb(30 64 175);
            background: rgb(30 58 138 / 0.25);
            color: rgb(191 219 254);
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
            border-bottom: 1px solid rgb(229 231 235);
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

        .elive-status {
            display: inline-flex;
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 800;
        }

        .elive-status-in {
            background: rgb(220 252 231);
            color: rgb(22 101 52);
        }

        .elive-status-out {
            background: rgb(254 226 226);
            color: rgb(153 27 27);
        }

        .elive-empty {
            padding: 3rem 1.5rem;
            text-align: center;
            color: rgb(107 114 128);
        }

        .elive-pagination {
            padding: 1rem 1.25rem;
        }

        @media (max-width: 1100px) {
            .elive-filter-grid,
            .elive-stat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .elive-filter-search {
                grid-column: span 1;
            }
        }

        @media (max-width: 640px) {
            .elive-filter-grid,
            .elive-stat-grid {
                grid-template-columns: 1fr;
            }

            .elive-panel-header,
            .elive-day-banner {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

    <div class="elive-attendance-report">
        <section class="elive-panel">
            <div class="elive-panel-header">
                <div>
                    <h2 class="elive-title">
                        Check-in Attendance Filters
                    </h2>

                    <p class="elive-subtitle">
                        Review attendance by event day, category, point, method, officer, and date range.
                    </p>
                </div>

                <div class="elive-loading" wire:loading>
                    Updating report...
                </div>
            </div>

            <div class="elive-panel-body">
                <div class="elive-filter-grid">
                    <div class="elive-form-group">
                        <label class="elive-label" for="attendance-event">
                            Event
                        </label>

                        <select
                            id="attendance-event"
                            class="elive-select"
                            wire:model.live="eventId"
                        >
                            <option value="">All events</option>

                            @foreach ($events as $event)
                                <option value="{{ $event->id }}">
                                    {{ $event->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="elive-form-group">
                        <label class="elive-label" for="attendance-day">
                            Event Day
                        </label>

                        <select
                            id="attendance-day"
                            class="elive-select"
                            wire:model.live="eventDayId"
                            @disabled(! $eventId || $eventDays->isEmpty())
                        >
                            <option value="">All event days</option>

                            @foreach ($eventDays as $day)
                                <option value="{{ $day->id }}">
                                    {{ $day->name }}
                                    @if ($day->event_date)
                                        — {{ $day->event_date->format('d M Y') }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="elive-form-group">
                        <label class="elive-label" for="attendance-category">
                            Category
                        </label>

                        <select
                            id="attendance-category"
                            class="elive-select"
                            wire:model.live="categoryId"
                            @disabled(! $eventId)
                        >
                            <option value="">All categories</option>

                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="elive-form-group">
                        <label class="elive-label" for="attendance-status">
                            Attendance Status
                        </label>

                        <select
                            id="attendance-status"
                            class="elive-select"
                            wire:model.live="attendanceStatus"
                        >
                            <option value="all">All attendees</option>
                            <option value="checked_in">Checked in</option>
                            <option value="not_checked_in">Not checked in</option>
                        </select>
                    </div>

                    <div class="elive-form-group">
                        <label class="elive-label" for="attendance-point">
                            Check-in Point
                        </label>

                        <select
                            id="attendance-point"
                            class="elive-select"
                            wire:model.live="checkInPointId"
                            @disabled(! $eventId)
                        >
                            <option value="">All points</option>

                            @foreach ($checkInPoints as $point)
                                <option value="{{ $point->id }}">
                                    {{ $point->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="elive-form-group">
                        <label class="elive-label" for="attendance-method">
                            Method
                        </label>

                        <select
                            id="attendance-method"
                            class="elive-select"
                            wire:model.live="method"
                        >
                            <option value="all">All methods</option>

                            @foreach ($methods as $methodOption)
                                <option value="{{ $methodOption }}">
                                    {{ ucwords(str_replace('_', ' ', $methodOption)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="elive-form-group">
                        <label class="elive-label" for="attendance-officer">
                            Officer
                        </label>

                        <select
                            id="attendance-officer"
                            class="elive-select"
                            wire:model.live="officerId"
                        >
                            <option value="">All officers</option>

                            @foreach ($officers as $officer)
                                <option value="{{ $officer->id }}">
                                    {{ $officer->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="elive-form-group">
                        <label class="elive-label" for="attendance-from">
                            Date From
                        </label>

                        <input
                            id="attendance-from"
                            type="date"
                            class="elive-input"
                            wire:model.live="dateFrom"
                        >
                    </div>

                    <div class="elive-form-group">
                        <label class="elive-label" for="attendance-to">
                            Date To
                        </label>

                        <input
                            id="attendance-to"
                            type="date"
                            class="elive-input"
                            wire:model.live="dateTo"
                        >
                    </div>

                    <div class="elive-form-group elive-filter-search">
                        <label class="elive-label" for="attendance-search">
                            Search
                        </label>

                        <input
                            id="attendance-search"
                            type="search"
                            class="elive-input"
                            wire:model.live.debounce.400ms="search"
                            placeholder="Name, phone, email, badge number, organization..."
                        >
                    </div>

                    <div class="elive-actions">
                        <button
                            type="button"
                            class="elive-button elive-button-secondary"
                            wire:click="clearFilters"
                        >
                            Clear Filters
                        </button>

                        <button
                            type="button"
                            class="elive-button elive-button-primary"
                            wire:click="exportCsv"
                            wire:loading.attr="disabled"
                            wire:target="exportCsv"
                        >
                            <span wire:loading.remove wire:target="exportCsv">
                                Export CSV
                            </span>

                            <span wire:loading wire:target="exportCsv">
                                Exporting...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        @if ($selectedEventDay)
            <div class="elive-day-banner">
                <div>
                    <strong>{{ $selectedEventDay->name }}</strong>

                    @if ($selectedEventDay->event_date)
                        <span>
                            · {{ $selectedEventDay->event_date->format('l, d M Y') }}
                        </span>
                    @endif
                </div>

                <div>
                    Reporting expected attendance and actual check-ins for this day.
                </div>
            </div>
        @endif

        <section class="elive-stat-grid">
            <div class="elive-stat">
                <div class="elive-stat-label">
                    {{ $selectedEventDay ? 'Expected for Day' : 'Registered Attendees' }}
                </div>

                <div class="elive-stat-value">
                    {{ number_format($summary['total']) }}
                </div>
            </div>

            <div class="elive-stat">
                <div class="elive-stat-label">
                    {{ $selectedEventDay ? 'Checked In for Day' : 'Actually Checked In' }}
                </div>

                <div class="elive-stat-value">
                    {{ number_format($summary['checked_in']) }}
                </div>
            </div>

            <div class="elive-stat">
                <div class="elive-stat-label">
                    {{ $selectedEventDay ? 'Not Checked In for Day' : 'Not Yet Checked In' }}
                </div>

                <div class="elive-stat-value">
                    {{ number_format($summary['not_checked_in']) }}
                </div>
            </div>

            <div class="elive-stat">
                <div class="elive-stat-label">
                    Attendance Rate
                </div>

                <div class="elive-stat-value">
                    {{ number_format($summary['attendance_rate'], 1) }}%
                </div>
            </div>
        </section>

        <section class="elive-panel">
            <div class="elive-panel-header">
                <div>
                    <h2 class="elive-title">
                        Check-in Attendance Records
                    </h2>

                    <p class="elive-subtitle">
                        Showing {{ number_format($attendees->total()) }} attendees matching the selected attendance filters.
                    </p>
                </div>

                <div class="elive-form-group" style="min-width: 8rem;">
                    <label class="elive-label" for="attendance-per-page">
                        Per Page
                    </label>

                    <select
                        id="attendance-per-page"
                        class="elive-select"
                        wire:model.live="perPage"
                    >
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>

            @if ($attendees->isNotEmpty())
                <div class="elive-table-wrap">
                    <table class="elive-table">
                        <thead>
                            <tr>
                                <th>Attendee</th>
                                <th>Day</th>
                                <th>Badge</th>
                                <th>Category</th>
                                <th>Organization</th>
                                <th>Status</th>
                                <th>Check-in Point</th>
                                <th>Method</th>
                                <th>Officer</th>
                                <th>Checked-in Time</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($attendees as $attendee)
                                @php
                                    $checkIn = $latestCheckIns->get(
                                        $attendee->id
                                    );
                                @endphp

                                <tr wire:key="attendance-report-{{ $attendee->id }}">
                                    <td>
                                        <strong>{{ $attendee->full_name }}</strong>

                                        @if ($attendee->event)
                                            <div style="margin-top: 0.2rem; color: rgb(100 116 139);">
                                                {{ $attendee->event->name }}
                                            </div>
                                        @endif
                                    </td>

                                    <td>
                                        {{ $checkIn?->eventDay?->name
                                            ?? ($selectedEventDay?->name ?? '—') }}
                                    </td>

                                    <td>
                                        {{ $attendee->badge_number ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $attendee->category?->name ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $attendee->organization_name ?? '—' }}
                                    </td>

                                    <td>
                                        <span class="elive-status {{ $checkIn ? 'elive-status-in' : 'elive-status-out' }}">
                                            {{ $checkIn ? 'Checked In' : 'Not Checked In' }}
                                        </span>
                                    </td>

                                    <td>
                                        {{ $checkIn?->checkInPoint?->name ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $checkIn
                                            ? ucwords(str_replace('_', ' ', $checkIn->method ?? ''))
                                            : '—' }}
                                    </td>

                                    <td>
                                        {{ $checkIn?->checkedInBy?->name
                                            ?? ($checkIn ? 'System' : '—') }}
                                    </td>

                                    <td>
                                        {{ $checkIn?->checked_in_at?->format('d/m/Y H:i:s') ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="elive-pagination">
                    {{ $attendees->links() }}
                </div>
            @else
                <div class="elive-empty">
                    No attendee check-in records matched the selected filters.
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
