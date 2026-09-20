<x-filament-panels::page>
    @php
        $events = $this->events;
        $categories = $this->categories;
        $statusOptions = $this->statusOptions;
        $sourceOptions = $this->sourceOptions;
        $attendees = $this->attendees;
        $summary = $this->summary;
    @endphp

    <style>
        .elive-report {
            display: grid;
            gap: 1.25rem;
        }

        .elive-report-panel {
            overflow: hidden;
            border: 1px solid rgb(229 231 235);
            border-radius: 1rem;
            background: white;
            box-shadow: 0 1px 3px rgb(15 23 42 / 0.08);
        }

        .dark .elive-report-panel {
            border-color: rgb(55 65 81);
            background: rgb(17 24 39);
        }

        .elive-report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgb(229 231 235);
        }

        .dark .elive-report-header {
            border-color: rgb(55 65 81);
        }

        .elive-report-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
            color: rgb(17 24 39);
        }

        .dark .elive-report-title {
            color: white;
        }

        .elive-report-subtitle {
            margin: 0.25rem 0 0;
            font-size: 0.875rem;
            color: rgb(107 114 128);
        }

        .dark .elive-report-subtitle {
            color: rgb(156 163 175);
        }

        .elive-report-body {
            padding: 1.25rem;
        }

        .elive-filter-grid {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr)
                minmax(0, 0.8fr)
                minmax(0, 0.8fr)
                minmax(0, 0.8fr);
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

        .elive-input:focus,
        .elive-select:focus {
            border-color: rgb(79 70 229);
            box-shadow: 0 0 0 3px rgb(79 70 229 / 0.15);
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
            grid-template-columns: repeat(5, minmax(0, 1fr));
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
            background: rgb(238 242 255);
            color: rgb(67 56 202);
            font-size: 0.72rem;
            font-weight: 800;
        }

        .elive-status-registered,
        .elive-status-approved,
        .elive-status-checked-in {
            background: rgb(220 252 231);
            color: rgb(22 101 52);
        }

        .elive-status-pending,
        .elive-status-pending-approval {
            background: rgb(254 243 199);
            color: rgb(146 64 14);
        }

        .elive-status-waitlisted {
            background: rgb(219 234 254);
            color: rgb(30 64 175);
        }

        .elive-status-cancelled,
        .elive-status-rejected {
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
            .elive-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .elive-filter-search {
                grid-column: span 1;
            }

            .elive-stat-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .elive-filter-grid,
            .elive-stat-grid {
                grid-template-columns: 1fr;
            }

            .elive-report-header {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

    <div class="elive-report">
        <section class="elive-report-panel">
            <div class="elive-report-header">
                <div>
                    <h2 class="elive-report-title">
                        Registration & Attendee Filters
                    </h2>

                    <p class="elive-report-subtitle">
                        Review registration records, apply attendee filters, and export the current result to CSV.
                    </p>
                </div>

                <div
                    class="elive-loading"
                    wire:loading
                >
                    Updating report...
                </div>
            </div>

            <div class="elive-report-body">
                <div class="elive-filter-grid">
                    <div class="elive-form-group">
                        <label class="elive-label" for="report-event">
                            Event
                        </label>

                        <select
                            id="report-event"
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
                        <label class="elive-label" for="report-category">
                            Category
                        </label>

                        <select
                            id="report-category"
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
                        <label class="elive-label" for="report-status">
                            Registration Status
                        </label>

                        <select
                            id="report-status"
                            class="elive-select"
                            wire:model.live="status"
                        >
                            <option value="all">All statuses</option>

                            @foreach ($statusOptions as $statusOption)
                                <option value="{{ $statusOption }}">
                                    {{ ucwords(str_replace('_', ' ', $statusOption)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="elive-form-group">
                        <label class="elive-label" for="report-source">
                            Registration Source
                        </label>

                        <select
                            id="report-source"
                            class="elive-select"
                            wire:model.live="registrationSource"
                        >
                            <option value="all">All sources</option>

                            @foreach ($sourceOptions as $sourceOption)
                                <option value="{{ $sourceOption }}">
                                    {{ ucwords(str_replace('_', ' ', $sourceOption)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="elive-form-group elive-filter-search">
                        <label class="elive-label" for="report-search">
                            Search
                        </label>

                        <input
                            id="report-search"
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

        <section class="elive-stat-grid">
            <div class="elive-stat">
                <div class="elive-stat-label">Total Registered</div>
                <div class="elive-stat-value">
                    {{ number_format($summary['total']) }}
                </div>
            </div>

            <div class="elive-stat">
                <div class="elive-stat-label">Checked In</div>
                <div class="elive-stat-value">
                    {{ number_format($summary['checked_in']) }}
                </div>
            </div>

            <div class="elive-stat">
                <div class="elive-stat-label">Not Checked In</div>
                <div class="elive-stat-value">
                    {{ number_format($summary['not_checked_in']) }}
                </div>
            </div>

            <div class="elive-stat">
                <div class="elive-stat-label">Pending Approval</div>
                <div class="elive-stat-value">
                    {{ number_format($summary['pending_approval']) }}
                </div>
            </div>

            <div class="elive-stat">
                <div class="elive-stat-label">Waitlisted</div>
                <div class="elive-stat-value">
                    {{ number_format($summary['waitlisted']) }}
                </div>
            </div>
        </section>

        <section class="elive-report-panel">
            <div class="elive-report-header">
                <div>
                    <h2 class="elive-report-title">
                        Registered Attendees
                    </h2>

                    <p class="elive-report-subtitle">
                        Showing {{ number_format($attendees->total()) }} attendee registration records matching the selected filters.
                    </p>
                </div>

                <div class="elive-form-group" style="min-width: 8rem;">
                    <label class="elive-label" for="report-per-page">
                        Per Page
                    </label>

                    <select
                        id="report-per-page"
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
                                <th>Name</th>
                                <th>Badge</th>
                                <th>Category</th>
                                <th>Organization</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Source</th>
                                <th>Registered</th>
                                <th>Checked In</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($attendees as $attendee)
                                @php
                                    $statusValue = $attendee->status
                                        ?? 'unknown';

                                    $statusClass = 'elive-status-'
                                        . str($statusValue)
                                            ->lower()
                                            ->replace('_', '-')
                                            ->slug('-');
                                @endphp

                                <tr wire:key="attendee-report-{{ $attendee->id }}">
                                    <td>
                                        <strong>{{ $attendee->full_name }}</strong>

                                        @if ($attendee->event)
                                            <div style="margin-top: 0.2rem; color: rgb(100 116 139);">
                                                {{ $attendee->event->name }}
                                            </div>
                                        @endif
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
                                        {{ $attendee->phone ?? '—' }}
                                    </td>

                                    <td>
                                        <span class="elive-status {{ $statusClass }}">
                                            {{ ucwords(str_replace('_', ' ', $statusValue)) }}
                                        </span>
                                    </td>

                                    <td>
                                        {{ ucwords(str_replace('_', ' ', $attendee->registration_source ?? '—')) }}
                                    </td>

                                    <td>
                                        {{ $attendee->registered_at?->format('d/m/Y H:i') ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $attendee->checked_in_at?->format('d/m/Y H:i') ?? '—' }}
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
                    No attendee registration records matched the selected filters.
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
