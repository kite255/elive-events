<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 24px;">
        {{-- Filters --}}
        <div style="
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        ">
            <div style="
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 16px;
                align-items: end;
            ">
                <div>
                    <label style="display: block; margin-bottom: 6px; font-size: 13px; font-weight: 700; color: #374151;">
                        Event
                    </label>

                    <select
                        wire:model.live="eventId"
                        style="
                            width: 100%;
                            border: 1px solid #d1d5db;
                            border-radius: 10px;
                            padding: 10px 12px;
                            font-size: 14px;
                            background: #ffffff;
                            color: #111827;
                        "
                    >
                        <option value="">All Events</option>

                        @foreach ($this->events as $event)
                            <option value="{{ $event->id }}">
                                {{ $event->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display: block; margin-bottom: 6px; font-size: 13px; font-weight: 700; color: #374151;">
                        Badge Status
                    </label>

                    <select
                        wire:model.live="badgeStatus"
                        style="
                            width: 100%;
                            border: 1px solid #d1d5db;
                            border-radius: 10px;
                            padding: 10px 12px;
                            font-size: 14px;
                            background: #ffffff;
                            color: #111827;
                        "
                    >
                        <option value="generated">Generated</option>
                        <option value="printed">Printed</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                        <option value="all">All</option>
                    </select>
                </div>

                <div style="grid-column: span 2;">
                    <label style="display: block; margin-bottom: 6px; font-size: 13px; font-weight: 700; color: #374151;">
                        Search Attendee
                    </label>

                    <input
                        type="text"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Search name, phone, email, badge number, organization..."
                        style="
                            width: 100%;
                            border: 1px solid #d1d5db;
                            border-radius: 10px;
                            padding: 10px 12px;
                            font-size: 14px;
                            color: #111827;
                        "
                    />
                </div>
            </div>
        </div>

        {{-- Attendee Cards --}}
        <div style="
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        ">
            @forelse ($this->attendees as $attendee)
                @php
                    $badgeUrl = $this->badgeUrl($attendee);
                    $badgeExists = filled($badgeUrl);
                    $status = $attendee->badge_status ?? 'pending';

                    $statusStyle = match ($status) {
                        'generated' => 'background:#dcfce7;color:#166534;border-color:#bbf7d0;',
                        'printed' => 'background:#dbeafe;color:#1e40af;border-color:#bfdbfe;',
                        'failed' => 'background:#fee2e2;color:#991b1b;border-color:#fecaca;',
                        'generating' => 'background:#fef3c7;color:#92400e;border-color:#fde68a;',
                        default => 'background:#f3f4f6;color:#374151;border-color:#e5e7eb;',
                    };
                @endphp

                <div style="
                    background: #ffffff;
                    border: 1px solid #e5e7eb;
                    border-radius: 18px;
                    overflow: hidden;
                    box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
                ">
                    {{-- Header --}}
                    <div style="padding: 16px; border-bottom: 1px solid #f1f5f9;">
                        <div style="display: flex; justify-content: space-between; gap: 12px; align-items: flex-start;">
                            <div style="min-width: 0;">
                                <h3 style="
                                    margin: 0;
                                    font-size: 16px;
                                    line-height: 22px;
                                    font-weight: 800;
                                    color: #111827;
                                ">
                                    {{ $attendee->full_name }}
                                </h3>

                                <div style="margin-top: 4px; font-size: 13px; color: #64748b;">
                                    {{ $attendee->badge_number ?? 'No badge number' }}
                                </div>
                            </div>

                            <span style="
                                display: inline-flex;
                                align-items: center;
                                border: 1px solid;
                                border-radius: 999px;
                                padding: 4px 10px;
                                font-size: 12px;
                                font-weight: 800;
                                white-space: nowrap;
                                {{ $statusStyle }}
                            ">
                                {{ $this->badgeStatusLabel($status) }}
                            </span>
                        </div>

                        <div style="
                            display: grid;
                            grid-template-columns: repeat(2, minmax(0, 1fr));
                            gap: 8px;
                            margin-top: 14px;
                            font-size: 12px;
                            color: #475569;
                        ">
                            <div>
                                <strong>Category:</strong>
                                {{ $attendee->category?->name ?? '-' }}
                            </div>

                            <div>
                                <strong>Type:</strong>
                                {{ $attendee->badgeType?->name ?? '-' }}
                            </div>

                            <div style="grid-column: span 2;">
                                <strong>Organization:</strong>
                                {{ $attendee->organization_name ?? '-' }}
                            </div>
                        </div>
                    </div>

                    {{-- Badge Preview --}}
                    <div style="background: #f8fafc; padding: 16px;">
                        @if ($badgeExists)
                            <div style="
                                height: 420px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                background: #ffffff;
                                border-radius: 14px;
                                padding: 10px;
                                box-shadow: inset 0 1px 4px rgba(15, 23, 42, 0.08);
                                overflow: hidden;
                            ">
                                <div style="
                                    width: 100%;
                                    height: 100%;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                ">
                                    <object
                                        data="{{ $badgeUrl }}?v={{ $attendee->updated_at?->timestamp ?? time() }}"
                                        type="image/svg+xml"
                                        style="
                                            width: 100%;
                                            height: 100%;
                                            max-width: 290px;
                                            max-height: 400px;
                                            border-radius: 10px;
                                            display: block;
                                        "
                                    >
                                        <img
                                            src="{{ $badgeUrl }}?v={{ $attendee->updated_at?->timestamp ?? time() }}"
                                            alt="Badge for {{ $attendee->full_name }}"
                                            style="
                                                width: 100%;
                                                height: 100%;
                                                max-width: 290px;
                                                max-height: 400px;
                                                object-fit: contain;
                                                border-radius: 10px;
                                                display: block;
                                            "
                                        />
                                    </object>
                                </div>
                            </div>
                        @else
                            <div style="
                                height: 420px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                background: #ffffff;
                                border: 1px dashed #cbd5e1;
                                border-radius: 14px;
                                padding: 24px;
                                text-align: center;
                            ">
                                <div>
                                    <div style="
                                        width: 52px;
                                        height: 52px;
                                        margin: 0 auto;
                                        border-radius: 999px;
                                        background: #f1f5f9;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        color: #64748b;
                                        font-weight: 800;
                                    ">
                                        ID
                                    </div>

                                    <p style="margin: 12px 0 0; font-size: 14px; font-weight: 800; color: #334155;">
                                        No badge generated
                                    </p>

                                    <p style="margin: 4px 0 0; font-size: 12px; color: #64748b;">
                                        Generate badge before printing.
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div style="
                        display: flex;
                        flex-wrap: wrap;
                        gap: 8px;
                        padding: 14px 16px 16px;
                        border-top: 1px solid #f1f5f9;
                    ">
                        @if ($badgeExists)
                            <button
                                type="button"
                                onclick="printBadge('{{ $badgeUrl }}?v={{ $attendee->updated_at?->timestamp ?? time() }}')"
                                style="
                                    border: 0;
                                    border-radius: 10px;
                                    background: #004e96;
                                    color: #ffffff;
                                    padding: 9px 13px;
                                    font-size: 13px;
                                    font-weight: 800;
                                    cursor: pointer;
                                "
                            >
                                Print
                            </button>

                            <a
                                href="{{ $badgeUrl }}?v={{ $attendee->updated_at?->timestamp ?? time() }}"
                                target="_blank"
                                style="
                                    display: inline-flex;
                                    align-items: center;
                                    text-decoration: none;
                                    border-radius: 10px;
                                    background: #f1f5f9;
                                    color: #334155;
                                    padding: 9px 13px;
                                    font-size: 13px;
                                    font-weight: 800;
                                "
                            >
                                View
                            </a>

                            <a
                                href="{{ $badgeUrl }}?v={{ $attendee->updated_at?->timestamp ?? time() }}"
                                download
                                style="
                                    display: inline-flex;
                                    align-items: center;
                                    text-decoration: none;
                                    border-radius: 10px;
                                    background: #f1f5f9;
                                    color: #334155;
                                    padding: 9px 13px;
                                    font-size: 13px;
                                    font-weight: 800;
                                "
                            >
                                Download
                            </a>

                            <button
                                type="button"
                                wire:click="markPrinted({{ $attendee->id }})"
                                wire:loading.attr="disabled"
                                style="
                                    border: 0;
                                    border-radius: 10px;
                                    background: #dbeafe;
                                    color: #1e40af;
                                    padding: 9px 13px;
                                    font-size: 13px;
                                    font-weight: 800;
                                    cursor: pointer;
                                "
                            >
                                Mark Printed
                            </button>

                            <button
                                type="button"
                                wire:click="generateBadge({{ $attendee->id }})"
                                wire:loading.attr="disabled"
                                style="
                                    border: 0;
                                    border-radius: 10px;
                                    background: #fef3c7;
                                    color: #92400e;
                                    padding: 9px 13px;
                                    font-size: 13px;
                                    font-weight: 800;
                                    cursor: pointer;
                                "
                            >
                                Regenerate
                            </button>
                        @else
                            <button
                                type="button"
                                wire:click="generateBadge({{ $attendee->id }})"
                                wire:loading.attr="disabled"
                                style="
                                    border: 0;
                                    border-radius: 10px;
                                    background: #16a34a;
                                    color: #ffffff;
                                    padding: 9px 13px;
                                    font-size: 13px;
                                    font-weight: 800;
                                    cursor: pointer;
                                "
                            >
                                Generate Badge
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div style="
                    grid-column: 1 / -1;
                    background: #ffffff;
                    border: 1px dashed #cbd5e1;
                    border-radius: 16px;
                    padding: 48px;
                    text-align: center;
                ">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #111827;">
                        No attendees found
                    </h3>

                    <p style="margin: 6px 0 0; font-size: 14px; color: #64748b;">
                        Change the event, status, or search filter.
                    </p>
                </div>
            @endforelse
        </div>

        <div>
            {{ $this->attendees->links() }}
        </div>
    </div>

    <style>
        @media (max-width: 1279px) {
            div[style*="grid-template-columns: repeat(3"] {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 767px) {
            div[style*="grid-template-columns: repeat(3"],
            div[style*="grid-template-columns: repeat(4"] {
                grid-template-columns: 1fr !important;
            }

            div[style*="grid-column: span 2"] {
                grid-column: span 1 !important;
            }
        }
    </style>

    <script>
        function printBadge(url) {
            const printWindow = window.open('', '_blank', 'width=500,height=800');

            if (! printWindow) {
                alert('Please allow popups to print badges.');
                return;
            }

            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                    <head>
                        <title>Print Badge</title>
                        <style>
                            * {
                                box-sizing: border-box;
                            }

                            html,
                            body {
                                margin: 0;
                                padding: 0;
                                background: #ffffff;
                            }

                            body {
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                min-height: 100vh;
                            }

                            object,
                            img {
                                width: 420px;
                                height: 620px;
                                object-fit: contain;
                                display: block;
                            }

                            @page {
                                size: 420px 620px;
                                margin: 0;
                            }

                            @media print {
                                html,
                                body {
                                    width: 420px;
                                    height: 620px;
                                }

                                object,
                                img {
                                    width: 420px;
                                    height: 620px;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        <object data="${url}" type="image/svg+xml">
                            <img src="${url}" alt="Badge" />
                        </object>

                        <script>
                            window.onload = function () {
                                setTimeout(function () {
                                    window.focus();
                                    window.print();
                                }, 500);
                            };
                        <\/script>
                    </body>
                </html>
            `);

            printWindow.document.close();
        }
    </script>
</x-filament-panels::page>