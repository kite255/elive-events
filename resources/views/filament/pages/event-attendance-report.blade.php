<x-filament-panels::page>
    <style>
        .elive-report {
            display: grid;
            gap: 24px;
        }

        .elive-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 22px;
            padding: 24px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
        }

        .elive-actions {
            margin-top: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .elive-btn {
            border: none;
            border-radius: 12px;
            padding: 11px 16px;
            font-weight: 800;
            cursor: pointer;
            font-size: 14px;
        }

        .elive-btn-primary {
            background: #233F7E;
            color: #ffffff;
        }

        .elive-btn-green {
            background: #16A34A;
            color: #ffffff;
        }

        .elive-btn-orange {
            background: #F99A12;
            color: #ffffff;
        }

        .elive-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .elive-stat {
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
        }

        @media (max-width: 900px) {
            .elive-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .elive-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="elive-report">
        <div class="elive-card">
            <form wire:submit.prevent="loadReport">
                {{ $this->form }}

                <div class="elive-actions">
                    <button type="submit" class="elive-btn elive-btn-primary">
                        Load Report
                    </button>

                    @if ($eventId)
                        <button type="button" wire:click="exportAll" class="elive-btn elive-btn-primary">
                            Export All
                        </button>

                        <button type="button" wire:click="exportCheckedIn" class="elive-btn elive-btn-green">
                            Export Checked-In
                        </button>

                        <button type="button" wire:click="exportNotCheckedIn" class="elive-btn elive-btn-orange">
                            Export Not Checked-In
                        </button>
                    @endif
                </div>
            </form>
        </div>

        @if ($eventId)
            <div class="elive-stats">
                <div class="elive-stat">
                    <div class="elive-stat-label">Total Attendees</div>
                    <div class="elive-stat-value">{{ number_format($totalAttendees) }}</div>
                </div>

                <div class="elive-stat">
                    <div class="elive-stat-label">Checked In</div>
                    <div class="elive-stat-value">{{ number_format($checkedInAttendees) }}</div>
                </div>

                <div class="elive-stat">
                    <div class="elive-stat-label">Not Checked In</div>
                    <div class="elive-stat-value">{{ number_format($notCheckedInAttendees) }}</div>
                </div>

                <div class="elive-stat">
                    <div class="elive-stat-label">Attendance Rate</div>
                    <div class="elive-stat-value">{{ $attendanceRate }}%</div>
                </div>
            </div>

            <div class="elive-card">
                <h3 style="font-size: 20px; font-weight: 900; margin-bottom: 16px;">
                    Recent Attendees for Selected Event
                </h3>

                @if ($attendees->isEmpty())
                    <p>No attendees found for this event.</p>
                @else
                    <table class="elive-table">
                        <thead>
                            <tr>
                                <th>Attendee</th>
                                <th>Phone</th>
                                <th>Category</th>
                                <th>Badge Type</th>
                                <th>Status</th>
                                <th>Checked In At</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($attendees as $attendee)
                                <tr>
                                    <td>{{ $attendee->full_name }}</td>
                                    <td>{{ $attendee->phone ?? 'N/A' }}</td>
                                    <td>{{ $attendee->category?->name ?? 'N/A' }}</td>
                                    <td>{{ $attendee->badgeType?->name ?? 'N/A' }}</td>
                                    <td>{{ $attendee->status }}</td>
                                    <td>{{ $attendee->checked_in_at?->format('d M Y, H:i') ?? 'Not checked in' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>