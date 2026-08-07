<x-filament-panels::page>
    @php
        $events = $this->events;
        $officers = $this->officers;
        $logs = $this->printLogs;
        $summary = $this->summary;
    @endphp

    <style>
        .elive-print-report {
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

        .elive-type {
            display: inline-flex;
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 800;
        }

        .elive-type-first {
            background: rgb(220 252 231);
            color: rgb(22 101 52);
        }

        .elive-type-reprint {
            background: rgb(254 243 199);
            color: rgb(146 64 14);
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

            .elive-panel-header {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

    <div class="elive-print-report">
        <section class="elive-panel">
            <div class="elive-panel-header">
                <div>
                    <h2 class="elive-title">
                        Badge Print Filters
                    </h2>

                    <p class="elive-subtitle">
                        Review first prints, reprints, copies, printers, officers, and reprint reasons.
                    </p>
                </div>

                <div wire:loading>
                    Updating report...
                </div>
            </div>

            <div class="elive-panel-body">
                <div class="elive-filter-grid">
                    <div class="elive-form-group">
                        <label class="elive-label" for="print-report-event">
                            Event
                        </label>

                        <select
                            id="print-report-event"
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
                        <label class="elive-label" for="print-report-type">
                            Print Type
                        </label>

                        <select
                            id="print-report-type"
                            class="elive-select"
                            wire:model.live="printType"
                        >
                            <option value="all">All print types</option>
                            <option value="first_print">First print</option>
                            <option value="reprint">Reprint</option>
                        </select>
                    </div>

                    <div class="elive-form-group">
                        <label class="elive-label" for="print-report-officer">
                            Printed By
                        </label>

                        <select
                            id="print-report-officer"
                            class="elive-select"
                            wire:model.live="printedBy"
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
                        <label class="elive-label" for="print-report-from">
                            Date From
                        </label>

                        <input
                            id="print-report-from"
                            type="date"
                            class="elive-input"
                            wire:model.live="dateFrom"
                        >
                    </div>

                    <div class="elive-form-group">
                        <label class="elive-label" for="print-report-to">
                            Date To
                        </label>

                        <input
                            id="print-report-to"
                            type="date"
                            class="elive-input"
                            wire:model.live="dateTo"
                        >
                    </div>

                    <div class="elive-form-group elive-filter-search">
                        <label class="elive-label" for="print-report-search">
                            Search
                        </label>

                        <input
                            id="print-report-search"
                            type="search"
                            class="elive-input"
                            wire:model.live.debounce.400ms="search"
                            placeholder="Attendee, badge number, organization, printer, reason..."
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
                <div class="elive-stat-label">Print Actions</div>
                <div class="elive-stat-value">
                    {{ number_format($summary['actions']) }}
                </div>
            </div>

            <div class="elive-stat">
                <div class="elive-stat-label">First Prints</div>
                <div class="elive-stat-value">
                    {{ number_format($summary['first_prints']) }}
                </div>
            </div>

            <div class="elive-stat">
                <div class="elive-stat-label">Reprints</div>
                <div class="elive-stat-value">
                    {{ number_format($summary['reprints']) }}
                </div>
            </div>

            <div class="elive-stat">
                <div class="elive-stat-label">Total Copies</div>
                <div class="elive-stat-value">
                    {{ number_format($summary['copies']) }}
                </div>
            </div>
        </section>

        <section class="elive-panel">
            <div class="elive-panel-header">
                <div>
                    <h2 class="elive-title">
                        Badge Print Records
                    </h2>

                    <p class="elive-subtitle">
                        Showing {{ number_format($logs->total()) }} matching print records.
                    </p>
                </div>

                <div class="elive-form-group" style="min-width: 8rem;">
                    <label class="elive-label" for="print-report-per-page">
                        Per Page
                    </label>

                    <select
                        id="print-report-per-page"
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

            @if ($logs->isNotEmpty())
                <div class="elive-table-wrap">
                    <table class="elive-table">
                        <thead>
                            <tr>
                                <th>Attendee</th>
                                <th>Badge</th>
                                <th>Type</th>
                                <th>Copies</th>
                                <th>Printer</th>
                                <th>Reprint Reason</th>
                                <th>Printed By</th>
                                <th>Printed At</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($logs as $log)
                                <tr wire:key="badge-print-log-{{ $log->id }}">
                                    <td>
                                        <strong>
                                            {{ $log->attendee?->full_name ?? 'Deleted attendee' }}
                                        </strong>

                                        @if ($log->event)
                                            <div style="margin-top: 0.2rem; color: rgb(100 116 139);">
                                                {{ $log->event->name }}
                                            </div>
                                        @endif
                                    </td>

                                    <td>
                                        {{ $log->attendee?->badge_number ?? '—' }}
                                    </td>

                                    <td>
                                        <span class="elive-type {{ $log->print_type === 'reprint' ? 'elive-type-reprint' : 'elive-type-first' }}">
                                            {{ $log->print_type === 'reprint' ? 'Reprint' : 'First Print' }}
                                        </span>
                                    </td>

                                    <td>
                                        {{ number_format($log->copies ?? 1) }}
                                    </td>

                                    <td>
                                        {{ $log->printer_name ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $log->reprint_reason ?? '—' }}
                                    </td>

                                    <td>
                                        {{ $log->printedBy?->name ?? 'System' }}
                                    </td>

                                    <td>
                                        {{ $log->printed_at?->format('d/m/Y H:i:s') ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="elive-pagination">
                    {{ $logs->links() }}
                </div>
            @else
                <div class="elive-empty">
                    No badge print records matched the selected filters.
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
