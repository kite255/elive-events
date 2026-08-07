<x-filament-panels::page>
    @php
        $events = $this->events;
        $attendees = $this->attendees;
    @endphp

    <style>
        .elive-print-station {
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
            grid-template-columns:
                minmax(0, 1fr)
                minmax(0, 0.8fr)
                minmax(0, 1.6fr)
                minmax(8rem, 0.4fr);
            gap: 1rem;
            align-items: end;
        }

        .elive-form-group {
            display: grid;
            gap: 0.45rem;
        }

        .elive-label {
            font-size: 0.82rem;
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

        .elive-badge-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1.25rem;
        }

        .elive-badge-card {
            overflow: hidden;
            border: 1px solid rgb(229 231 235);
            border-radius: 1rem;
            background: white;
            box-shadow: 0 1px 4px rgb(15 23 42 / 0.08);
        }

        .dark .elive-badge-card {
            border-color: rgb(55 65 81);
            background: rgb(17 24 39);
        }

        .elive-badge-card-header {
            padding: 1rem;
            border-bottom: 1px solid rgb(241 245 249);
        }

        .dark .elive-badge-card-header {
            border-color: rgb(55 65 81);
        }

        .elive-badge-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .elive-attendee-name {
            margin: 0;
            font-size: 1rem;
            line-height: 1.4;
            font-weight: 800;
            color: rgb(17 24 39);
        }

        .dark .elive-attendee-name {
            color: white;
        }

        .elive-badge-number {
            margin-top: 0.2rem;
            font-size: 0.8rem;
            color: rgb(100 116 139);
        }

        .elive-status {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.6rem;
            border: 1px solid;
            border-radius: 999px;
            white-space: nowrap;
            font-size: 0.72rem;
            font-weight: 800;
        }

        .elive-status-generated {
            border-color: rgb(187 247 208);
            background: rgb(220 252 231);
            color: rgb(22 101 52);
        }

        .elive-status-printed {
            border-color: rgb(191 219 254);
            background: rgb(219 234 254);
            color: rgb(30 64 175);
        }

        .elive-status-generating {
            border-color: rgb(253 230 138);
            background: rgb(254 243 199);
            color: rgb(146 64 14);
        }

        .elive-status-failed {
            border-color: rgb(254 202 202);
            background: rgb(254 226 226);
            color: rgb(153 27 27);
        }

        .elive-status-pending {
            border-color: rgb(229 231 235);
            background: rgb(243 244 246);
            color: rgb(55 65 81);
        }

        .elive-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.55rem;
            margin-top: 0.9rem;
            font-size: 0.78rem;
            color: rgb(71 85 105);
        }

        .dark .elive-meta-grid {
            color: rgb(203 213 225);
        }

        .elive-meta-wide {
            grid-column: span 2;
        }

        .elive-preview-wrap {
            padding: 1rem;
            background: rgb(248 250 252);
        }

        .dark .elive-preview-wrap {
            background: rgb(15 23 42);
        }

        .elive-preview {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 26rem;
            padding: 0.65rem;
            overflow: hidden;
            border-radius: 0.875rem;
            background: white;
            box-shadow: inset 0 1px 4px rgb(15 23 42 / 0.08);
        }

        .dark .elive-preview {
            background: rgb(31 41 55);
        }

        .elive-preview object,
        .elive-preview img {
            display: block;
            width: 100%;
            height: 100%;
            max-width: 18rem;
            max-height: 25rem;
            object-fit: contain;
            border-radius: 0.65rem;
        }

        .elive-no-preview {
            display: grid;
            place-items: center;
            min-height: 26rem;
            padding: 1.5rem;
            border: 1px dashed rgb(203 213 225);
            border-radius: 0.875rem;
            background: white;
            text-align: center;
            color: rgb(100 116 139);
        }

        .dark .elive-no-preview {
            border-color: rgb(71 85 105);
            background: rgb(31 41 55);
        }

        .elive-no-preview-icon {
            display: grid;
            place-items: center;
            width: 3.25rem;
            height: 3.25rem;
            margin: 0 auto;
            border-radius: 999px;
            background: rgb(241 245 249);
            font-weight: 900;
        }

        .dark .elive-no-preview-icon {
            background: rgb(51 65 85);
        }

        .elive-card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            padding: 0.9rem 1rem 1rem;
            border-top: 1px solid rgb(241 245 249);
        }

        .elive-print-details {
            display: grid;
            grid-template-columns: minmax(0, 0.35fr) minmax(0, 0.65fr);
            gap: 0.75rem;
            padding: 0.9rem 1rem;
            border-top: 1px solid rgb(241 245 249);
            background: rgb(248 250 252);
        }

        .dark .elive-print-details {
            border-color: rgb(55 65 81);
            background: rgb(15 23 42);
        }

        .elive-print-details-wide {
            grid-column: 1 / -1;
        }

        .elive-textarea {
            width: 100%;
            min-height: 5rem;
            padding: 0.65rem 0.8rem;
            border: 1px solid rgb(209 213 219);
            border-radius: 0.75rem;
            background: white;
            color: rgb(17 24 39);
            resize: vertical;
            outline: none;
        }

        .dark .elive-textarea {
            border-color: rgb(75 85 99);
            background: rgb(31 41 55);
            color: white;
        }

        .elive-error {
            font-size: 0.75rem;
            color: rgb(220 38 38);
        }

        .dark .elive-card-actions {
            border-color: rgb(55 65 81);
        }

        .elive-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.4rem;
            padding: 0.55rem 0.8rem;
            border: 0;
            border-radius: 0.65rem;
            font-size: 0.78rem;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }

        .elive-button:disabled {
            cursor: not-allowed;
            opacity: 0.55;
        }

        .elive-button-primary {
            background: rgb(0 78 150);
            color: white;
        }

        .elive-button-success {
            background: rgb(22 163 74);
            color: white;
        }

        .elive-button-warning {
            background: rgb(254 243 199);
            color: rgb(146 64 14);
        }

        .elive-button-info {
            background: rgb(219 234 254);
            color: rgb(30 64 175);
        }

        .elive-button-secondary {
            background: rgb(241 245 249);
            color: rgb(51 65 85);
        }

        .dark .elive-button-secondary {
            background: rgb(51 65 85);
            color: white;
        }

        .elive-empty {
            grid-column: 1 / -1;
            display: grid;
            place-items: center;
            min-height: 16rem;
            padding: 3rem 1.5rem;
            border: 1px dashed rgb(203 213 225);
            border-radius: 1rem;
            background: white;
            text-align: center;
            color: rgb(100 116 139);
        }

        .dark .elive-empty {
            border-color: rgb(71 85 105);
            background: rgb(17 24 39);
        }

        .elive-pagination {
            overflow-x: auto;
        }

        @media (max-width: 1279px) {
            .elive-badge-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .elive-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            .elive-badge-grid,
            .elive-filter-grid {
                grid-template-columns: 1fr;
            }

            .elive-panel-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .elive-meta-grid,
            .elive-print-details {
                grid-template-columns: 1fr;
            }

            .elive-meta-wide {
                grid-column: span 1;
            }
        }
    </style>

    <div class="elive-print-station">
        <section class="elive-panel">
            <div class="elive-panel-header">
                <div>
                    <h2 class="elive-title">
                        Badge Print Filters
                    </h2>

                    <p class="elive-subtitle">
                        Find generated, pending, failed, or previously printed attendee badges.
                    </p>
                </div>

                <div
                    class="elive-loading"
                    wire:loading
                >
                    Updating...
                </div>
            </div>

            <div class="elive-panel-body">
                <div class="elive-filter-grid">
                    <div class="elive-form-group">
                        <label
                            class="elive-label"
                            for="badge-print-event"
                        >
                            Event
                        </label>

                        <select
                            id="badge-print-event"
                            class="elive-select"
                            wire:model.live="eventId"
                        >
                            <option value="">
                                All events
                            </option>

                            @foreach ($events as $event)
                                <option value="{{ $event->id }}">
                                    {{ $event->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="elive-form-group">
                        <label
                            class="elive-label"
                            for="badge-print-status"
                        >
                            Badge Status
                        </label>

                        <select
                            id="badge-print-status"
                            class="elive-select"
                            wire:model.live="badgeStatus"
                        >
                            <option value="generated">Generated</option>
                            <option value="printed">Printed</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                            <option value="all">All statuses</option>
                        </select>
                    </div>

                    <div class="elive-form-group">
                        <label
                            class="elive-label"
                            for="badge-print-search"
                        >
                            Search Attendee
                        </label>

                        <input
                            id="badge-print-search"
                            type="search"
                            class="elive-input"
                            wire:model.live.debounce.400ms="search"
                            placeholder="Name, phone, email, badge number, organization..."
                            autocomplete="off"
                        >
                    </div>

                    <div class="elive-form-group">
                        <label
                            class="elive-label"
                            for="badge-print-per-page"
                        >
                            Per Page
                        </label>

                        <select
                            id="badge-print-per-page"
                            class="elive-select"
                            wire:model.live="perPage"
                        >
                            <option value="6">6</option>
                            <option value="12">12</option>
                            <option value="24">24</option>
                            <option value="48">48</option>
                        </select>
                    </div>
                </div>
            </div>
        </section>

        <section class="elive-badge-grid">
            @forelse ($attendees as $attendee)
                @php
                    $badgeUrl = $this->badgeUrl($attendee);
                    $badgeExists = filled($badgeUrl);
                    $status = $attendee->badge_status ?? 'pending';

                    $statusClass = match ($status) {
                        'generated' => 'elive-status-generated',
                        'printed' => 'elive-status-printed',
                        'generating' => 'elive-status-generating',
                        'failed' => 'elive-status-failed',
                        default => 'elive-status-pending',
                    };

                    $version = $attendee->updated_at?->timestamp ?? time();
                    $versionedBadgeUrl = $badgeExists
                        ? $badgeUrl . '?v=' . $version
                        : null;
                @endphp

                <article
                    class="elive-badge-card"
                    wire:key="badge-card-{{ $attendee->id }}-{{ $version }}"
                >
                    <div class="elive-badge-card-header">
                        <div class="elive-badge-heading">
                            <div>
                                <h3 class="elive-attendee-name">
                                    {{ $attendee->full_name }}
                                </h3>

                                <div class="elive-badge-number">
                                    {{ $attendee->badge_number ?? 'No badge number' }}
                                </div>
                            </div>

                            <span class="elive-status {{ $statusClass }}">
                                {{ $this->badgeStatusLabel($status) }}
                            </span>
                        </div>

                        <div class="elive-meta-grid">
                            <div>
                                <strong>Category:</strong>
                                {{ $attendee->category?->name ?? '—' }}
                            </div>

                            <div>
                                <strong>Badge Type:</strong>
                                {{ $attendee->badgeType?->name ?? '—' }}
                            </div>

                            <div class="elive-meta-wide">
                                <strong>Organization:</strong>
                                {{ $attendee->organization_name ?? '—' }}
                            </div>

                            <div class="elive-meta-wide">
                                <strong>Event:</strong>
                                {{ $attendee->event?->name ?? '—' }}
                            </div>
                        </div>
                    </div>

                    <div class="elive-preview-wrap">
                        @if ($badgeExists)
                            <div class="elive-preview">
                                <object
                                    data="{{ $versionedBadgeUrl }}"
                                    type="image/svg+xml"
                                    aria-label="Badge for {{ $attendee->full_name }}"
                                >
                                    <img
                                        src="{{ $versionedBadgeUrl }}"
                                        alt="Badge for {{ $attendee->full_name }}"
                                    >
                                </object>
                            </div>
                        @else
                            <div class="elive-no-preview">
                                <div>
                                    <div class="elive-no-preview-icon">
                                        ID
                                    </div>

                                    <p style="margin: 0.8rem 0 0; font-weight: 800;">
                                        No badge generated
                                    </p>

                                    <p style="margin: 0.25rem 0 0; font-size: 0.8rem;">
                                        Generate the attendee badge before printing.
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if ($badgeExists)
                        <div class="elive-print-details">
                            <div class="elive-form-group">
                                <label
                                    class="elive-label"
                                    for="print-copies-{{ $attendee->id }}"
                                >
                                    Copies
                                </label>

                                <input
                                    id="print-copies-{{ $attendee->id }}"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="elive-input"
                                    wire:model="printCopies.{{ $attendee->id }}"
                                    placeholder="1"
                                >

                                @error("printCopies.{$attendee->id}")
                                    <span class="elive-error">
                                        {{ $message }}
                                    </span>
                                @enderror
                            </div>

                            <div class="elive-form-group">
                                <label
                                    class="elive-label"
                                    for="printer-name-{{ $attendee->id }}"
                                >
                                    Printer Name
                                </label>

                                <input
                                    id="printer-name-{{ $attendee->id }}"
                                    type="text"
                                    class="elive-input"
                                    wire:model="printerNames.{{ $attendee->id }}"
                                    placeholder="Example: Zebra ZD421"
                                >

                                @error("printerNames.{$attendee->id}")
                                    <span class="elive-error">
                                        {{ $message }}
                                    </span>
                                @enderror
                            </div>

                            @if ($status === 'printed')
                                <div class="elive-form-group elive-print-details-wide">
                                    <label
                                        class="elive-label"
                                        for="reprint-reason-{{ $attendee->id }}"
                                    >
                                        Reprint Reason
                                    </label>

                                    <textarea
                                        id="reprint-reason-{{ $attendee->id }}"
                                        class="elive-textarea"
                                        wire:model="reprintReasons.{{ $attendee->id }}"
                                        placeholder="Enter the reason for reprinting this badge"
                                    ></textarea>

                                    @error("reprintReasons.{$attendee->id}")
                                        <span class="elive-error">
                                            {{ $message }}
                                        </span>
                                    @enderror
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="elive-card-actions">
                        @if ($badgeExists)
                            <button
                                type="button"
                                class="elive-button elive-button-primary"
                                onclick='window.elivePrintBadge(
                                    @json($versionedBadgeUrl),
                                    @json($attendee->full_name)
                                )'
                            >
                                Print Badge
                            </button>

                            <a
                                href="{{ $versionedBadgeUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="elive-button elive-button-secondary"
                            >
                                View
                            </a>

                            <a
                                href="{{ $versionedBadgeUrl }}"
                                download
                                class="elive-button elive-button-secondary"
                            >
                                Download
                            </a>

                            <button
                                type="button"
                                class="elive-button elive-button-info"
                                wire:click="markPrinted({{ $attendee->id }})"
                                wire:loading.attr="disabled"
                                wire:target="markPrinted({{ $attendee->id }})"
                            >
                                <span
                                    wire:loading.remove
                                    wire:target="markPrinted({{ $attendee->id }})"
                                >
                                    {{ $status === 'printed'
                                        ? 'Record Reprint'
                                        : 'Record First Print' }}
                                </span>

                                <span
                                    wire:loading
                                    wire:target="markPrinted({{ $attendee->id }})"
                                >
                                    Saving...
                                </span>
                            </button>

                            <button
                                type="button"
                                class="elive-button elive-button-warning"
                                wire:click="generateBadge({{ $attendee->id }})"
                                wire:loading.attr="disabled"
                                wire:target="generateBadge({{ $attendee->id }})"
                            >
                                <span
                                    wire:loading.remove
                                    wire:target="generateBadge({{ $attendee->id }})"
                                >
                                    Regenerate
                                </span>

                                <span
                                    wire:loading
                                    wire:target="generateBadge({{ $attendee->id }})"
                                >
                                    Generating...
                                </span>
                            </button>
                        @else
                            <button
                                type="button"
                                class="elive-button elive-button-success"
                                wire:click="generateBadge({{ $attendee->id }})"
                                wire:loading.attr="disabled"
                                wire:target="generateBadge({{ $attendee->id }})"
                            >
                                <span
                                    wire:loading.remove
                                    wire:target="generateBadge({{ $attendee->id }})"
                                >
                                    Generate Badge
                                </span>

                                <span
                                    wire:loading
                                    wire:target="generateBadge({{ $attendee->id }})"
                                >
                                    Generating...
                                </span>
                            </button>
                        @endif
                    </div>
                </article>
            @empty
                <div class="elive-empty">
                    <div>
                        <h3 style="margin: 0; font-size: 1rem; font-weight: 800;">
                            No attendees found
                        </h3>

                        <p style="margin: 0.4rem 0 0;">
                            Change the event, badge status, or search filter.
                        </p>
                    </div>
                </div>
            @endforelse
        </section>

        <div class="elive-pagination">
            {{ $attendees->links() }}
        </div>
    </div>

    @script
        <script>
            window.elivePrintBadge = (
                badgeUrl,
                attendeeName = 'Attendee'
            ) => {
                if (! badgeUrl) {
                    return;
                }

                const printWindow = window.open(
                    '',
                    '_blank',
                    'width=560,height=860'
                );

                if (! printWindow) {
                    window.alert(
                        'Please allow popups in your browser to print badges.'
                    );

                    return;
                }

                const safeTitle = String(attendeeName)
                    .replace(/[<>&"']/g, '');

                const escapedUrl = String(badgeUrl)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');

                printWindow.document.open();

                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                        <head>
                            <meta charset="utf-8">
                            <title>Badge - ${safeTitle}</title>

                            <style>
                                * {
                                    box-sizing: border-box;
                                }

                                html,
                                body {
                                    width: 100%;
                                    min-height: 100%;
                                    margin: 0;
                                    padding: 0;
                                    background: #ffffff;
                                }

                                body {
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                }

                                .badge-sheet {
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    width: 420px;
                                    height: 620px;
                                    overflow: hidden;
                                    background: #ffffff;
                                }

                                object,
                                img {
                                    display: block;
                                    width: 420px;
                                    height: 620px;
                                    object-fit: contain;
                                }

                                @page {
                                    size: 420px 620px;
                                    margin: 0;
                                }

                                @media print {
                                    html,
                                    body,
                                    .badge-sheet {
                                        width: 420px;
                                        height: 620px;
                                    }
                                }
                            </style>
                        </head>

                        <body>
                            <div class="badge-sheet">
                                <object
                                    id="badge-object"
                                    data="${escapedUrl}"
                                    type="image/svg+xml"
                                >
                                    <img
                                        src="${escapedUrl}"
                                        alt="Badge"
                                    >
                                </object>
                            </div>

                            <script>
                                const printBadgeDocument = () => {
                                    window.setTimeout(() => {
                                        window.focus();
                                        window.print();
                                    }, 500);
                                };

                                window.addEventListener(
                                    'load',
                                    printBadgeDocument,
                                    { once: true }
                                );

                                window.addEventListener(
                                    'afterprint',
                                    () => window.close(),
                                    { once: true }
                                );
                            <\/script>
                        </body>
                    </html>
                `);

                printWindow.document.close();
            };
        </script>
    @endscript
</x-filament-panels::page>
