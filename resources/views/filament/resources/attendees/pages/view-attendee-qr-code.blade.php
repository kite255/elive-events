<x-filament-panels::page>
    <style>
        .elive-qr-wrapper {
            max-width: 900px;
            margin: 0 auto;
        }

        .elive-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
        }

        .elive-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 32px;
            flex-wrap: wrap;
        }

        .elive-title {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }

        .elive-event {
            font-size: 16px;
            color: #64748b;
            margin-top: 8px;
        }

        .elive-badge {
            display: inline-block;
            margin-top: 16px;
            padding: 8px 14px;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-weight: 700;
            font-size: 14px;
        }

        .elive-qr-box {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 18px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
        }

        .elive-qr-box img {
            width: 320px;
            height: 320px;
            object-fit: contain;
            display: block;
        }

        .elive-details {
            margin-top: 32px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .elive-detail {
            background: #f8fafc;
            border-radius: 18px;
            padding: 18px;
            border: 1px solid #eef2f7;
        }

        .elive-label {
            color: #64748b;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .elive-value {
            color: #0f172a;
            font-size: 18px;
            font-weight: 800;
            word-break: break-word;
        }

        .elive-url {
            margin-top: 28px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 18px;
        }

        .elive-url-value {
            margin-top: 8px;
            color: #334155;
            font-size: 14px;
            word-break: break-all;
        }

        .elive-actions {
            margin-top: 28px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .elive-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            padding: 12px 18px;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .elive-btn-primary {
            background: #233F7E;
            color: #ffffff;
        }

        .elive-btn-dark {
            background: #0B1F3A;
            color: #ffffff;
        }

        .elive-btn-light {
            background: #f1f5f9;
            color: #0f172a;
        }

        @media print {
            .fi-sidebar,
            .fi-topbar,
            .elive-actions,
            nav {
                display: none !important;
            }

            .elive-card {
                box-shadow: none;
                border: none;
            }
        }

        @media (max-width: 768px) {
            .elive-card {
                padding: 20px;
            }

            .elive-title {
                font-size: 26px;
            }

            .elive-qr-box img {
                width: 260px;
                height: 260px;
            }

            .elive-details {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="elive-qr-wrapper">
        <div class="elive-card">
            <div class="elive-header">
                <div>
                    <h2 class="elive-title">
                        {{ $record->full_name }}
                    </h2>

                    <div class="elive-event">
                        {{ $record->event?->name ?? 'No event assigned' }}
                    </div>

                    <div class="elive-badge">
                        QR Code Ready
                    </div>
                </div>

                <div class="elive-qr-box">
                    <img src="{{ $qrCodeUrl }}" alt="QR Code">
                </div>
            </div>

            <div class="elive-details">
                <div class="elive-detail">
                    <div class="elive-label">Badge Number</div>
                    <div class="elive-value">{{ $record->badge_number ?? 'N/A' }}</div>
                </div>

                <div class="elive-detail">
                    <div class="elive-label">Phone</div>
                    <div class="elive-value">{{ $record->phone ?? 'N/A' }}</div>
                </div>

                <div class="elive-detail">
                    <div class="elive-label">Category</div>
                    <div class="elive-value">{{ $record->category?->name ?? 'N/A' }}</div>
                </div>

                <div class="elive-detail">
                    <div class="elive-label">Badge Type</div>
                    <div class="elive-value">{{ $record->badgeType?->name ?? 'N/A' }}</div>
                </div>
            </div>

<div class="elive-url">
    <div class="elive-label">Secure QR Token</div>
    <div class="elive-url-value">
        The secure check-in link is embedded inside this QR code.
    </div>
</div>

            <div class="elive-actions">
                <a href="{{ $qrCodeUrl }}" target="_blank" class="elive-btn elive-btn-primary">
                    Open QR Code
                </a>

                <button type="button" onclick="window.print()" class="elive-btn elive-btn-dark">
                    Print
                </button>

                <a href="{{ \App\Filament\Resources\Attendees\AttendeeResource::getUrl('edit', ['record' => $record]) }}" class="elive-btn elive-btn-light">
                    Back to Attendee
                </a>
            </div>
        </div>
    </div>
</x-filament-panels::page>
