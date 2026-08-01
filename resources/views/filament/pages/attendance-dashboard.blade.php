<x-filament-panels::page>
    <style>
        .elive-dashboard {
            display: grid;
            gap: 24px;
        }

        .elive-event-banner {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 22px;
            padding: 22px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
        }

        .elive-event-title {
            color: #0f172a;
            font-size: 22px;
            font-weight: 900;
            margin: 0;
        }

        .elive-event-subtitle {
            color: #64748b;
            font-size: 14px;
            margin-top: 6px;
        }

        .elive-event-badge {
            display: inline-flex;
            margin-top: 12px;
            border-radius: 999px;
            padding: 6px 12px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            font-size: 13px;
            font-weight: 800;
        }

        .elive-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .elive-stat-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
        }

        .elive-stat-label {
            color: #64748b;
            font-size: 14px;
            font-weight: 700;
        }

        .elive-stat-value {
            margin-top: 8px;
            color: #0f172a;
            font-size: 34px;
            font-weight: 900;
        }

        .elive-stat-blue {
            border-top: 5px solid #233F7E;
        }

        .elive-stat-green {
            border-top: 5px solid #16A34A;
        }

        .elive-stat-orange {
            border-top: 5px solid #F99A12;
        }

        .elive-stat-dark {
            border-top: 5px solid #0B1F3A;
        }

        .elive-stat-red {
            border-top: 5px solid #DC2626;
        }

        .elive-stat-purple {
            border-top: 5px solid #7C3AED;
        }

        .elive-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 20px;
        }

        .elive-panel {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 22px;
            padding: 22px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
        }

        .elive-panel-title {
            color: #0f172a;
            font-size: 20px;
            font-weight: 900;
            margin-bottom: 16px;
        }

        .elive-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .elive-table th {
            text-align: left;
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .elive-table td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #0f172a;
            vertical-align: top;
        }

        .elive-badge {
            display: inline-flex;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 800;
            background: #dcfce7;
            color: #166534;
        }

        .elive-badge-qr {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .elive-badge-manual {
            background: #fef3c7;
            color: #92400e;
        }

        .elive-empty {
            color: #64748b;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 16px;
            padding: 18px;
            text-align: center;
        }

        .elive-progress {
            background: #e5e7eb;
            border-radius: 999px;
            overflow: hidden;
            height: 12px;
            margin-top: 10px;
        }

        .elive-progress-fill {
            background: #16A34A;
            height: 100%;
            width: {{ $attendanceRate }}%;
        }

        @media (max-width: 1100px) {
            .elive-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .elive-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .elive-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="elive-dashboard">
        <div class="elive-event-banner">
            <h2 class="elive-event-title">Attendance Dashboard</h2>

            <div class="elive-event-subtitle">
                Live attendance summary, recent check-ins, and check-ins by point.
            </div>

            @if ($eventName)
                <div class="elive-event-badge">
                    Event: {{ $eventName }}
                </div>
            @else
                <div class="elive-event-badge" style="background:#fef3c7;color:#92400e;border-color:#fde68a;">
                    Showing all events
                </div>
            @endif
        </div>

        <div class="elive-stats">
            <div class="elive-stat-card elive-stat-blue">
                <div class="elive-stat-label">Total Attendees</div>
                <div class="elive-stat-value">{{ number_format($totalAttendees) }}</div>
            </div>

            <div class="elive-stat-card elive-stat-green">
                <div class="elive-stat-label">Checked In</div>
                <div class="elive-stat-value">{{ number_format($checkedInAttendees) }}</div>
            </div>

            <div class="elive-stat-card elive-stat-orange">
                <div class="elive-stat-label">Not Checked In</div>
                <div class="elive-stat-value">{{ number_format($notCheckedInAttendees) }}</div>
            </div>

            <div class="elive-stat-card elive-stat-dark">
                <div class="elive-stat-label">Attendance Rate</div>
                <div class="elive-stat-value">{{ $attendanceRate }}%</div>
                <div class="elive-progress">
                    <div class="elive-progress-fill"></div>
                </div>
            </div>

            <div class="elive-stat-card elive-stat-purple">
                <div class="elive-stat-label">Today Check-ins</div>
                <div class="elive-stat-value">{{ number_format($todayCheckIns) }}</div>
            </div>

            <div class="elive-stat-card elive-stat-green">
                <div class="elive-stat-label">QR Check-ins</div>
                <div class="elive-stat-value">{{ number_format($qrCheckIns) }}</div>
            </div>

            <div class="elive-stat-card elive-stat-blue">
                <div class="elive-stat-label">Manual Check-ins</div>
                <div class="elive-stat-value">{{ number_format($manualCheckIns) }}</div>
            </div>

            <div class="elive-stat-card elive-stat-red">
                <div class="elive-stat-label">Duplicate Protection</div>
                <div class="elive-stat-value">ON</div>
            </div>
        </div>

        <div class="elive-grid">
            <div class="elive-panel">
                <div class="elive-panel-title">Recent Check-ins</div>

                @if ($recentCheckIns->isEmpty())
                    <div class="elive-empty">No check-ins recorded yet.</div>
                @else
                    <table class="elive-table">
                        <thead>
                            <tr>
                                <th>Attendee</th>
                                <th>Event</th>
                                <th>Point</th>
                                <th>Method</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentCheckIns as $checkIn)
                                <tr>
                                    <td>
                                        <strong>{{ $checkIn->attendee?->full_name ?? 'N/A' }}</strong><br>
                                        <small>{{ $checkIn->attendee?->phone ?? '' }}</small>
                                    </td>
                                    <td>{{ $checkIn->event?->name ?? 'N/A' }}</td>
                                    <td>{{ $checkIn->checkInPoint?->name ?? 'N/A' }}</td>
                                    <td>
                                        <span class="elive-badge {{ $checkIn->method === 'qr' ? 'elive-badge-qr' : 'elive-badge-manual' }}">
                                            {{ strtoupper($checkIn->method ?? 'N/A') }}
                                        </span>
                                    </td>
                                    <td>{{ $checkIn->checked_in_at?->format('d M Y, H:i') ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="elive-panel">
                <div class="elive-panel-title">Check-ins by Point</div>

                @if ($checkInsByPoint->isEmpty())
                    <div class="elive-empty">No check-in points created yet.</div>
                @else
                    <table class="elive-table">
                        <thead>
                            <tr>
                                <th>Point</th>
                                <th>Location</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($checkInsByPoint as $point)
                                <tr>
                                    <td>{{ $point->name }}</td>
                                    <td>{{ $point->location ?? 'N/A' }}</td>
                                    <td><strong>{{ number_format($point->check_ins_count) }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>