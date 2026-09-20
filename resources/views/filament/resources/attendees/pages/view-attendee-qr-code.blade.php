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

        .elive-badge-missing {
            background: #fee2e2;
            color: #991b1b;
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

        .elive-qr-placeholder {
            width: 320px;
            height: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
            color: #64748b;
            background: #f8fafc;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700;
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
            align-items: center;
        }

        .elive-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            border-radius: 12px;
            padding: 12px 18px;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition:
                transform 0.15s ease,
                box-shadow 0.15s ease,
                opacity 0.15s ease;
        }

        .elive-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(15, 23, 42, 0.12);
        }

        .elive-btn-primary {
            background: #161943;
            color: #ffffff;
        }

        .elive-btn-download {
            background: #007AB2;
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

        .elive-icon {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
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

            .elive-qr-box img,
            .elive-qr-placeholder {
                width: 260px;
                height: 260px;
            }

            .elive-details {
                grid-template-columns: 1fr;
            }

            .elive-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .elive-btn {
                width: 100%;
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

                    @if ($qrCodeUrl)
                        <div class="elive-badge">
                            QR Code Ready
                        </div>
                    @else
                        <div class="elive-badge elive-badge-missing">
                            QR Code Not Generated
                        </div>
                    @endif
                </div>

                <div class="elive-qr-box">
                    @if ($qrCodeUrl)
                        <img
                            src="{{ $qrCodeUrl }}"
                            alt="QR Code for {{ $record->full_name }}"
                        >
                    @else
                        <div class="elive-qr-placeholder">
                            QR code file is not currently available.
                        </div>
                    @endif
                </div>
            </div>

            <div class="elive-details">
                <div class="elive-detail">
                    <div class="elive-label">
                        Badge Number
                    </div>

                    <div class="elive-value">
                        {{ $record->badge_number ?? 'N/A' }}
                    </div>
                </div>

                <div class="elive-detail">
                    <div class="elive-label">
                        Phone
                    </div>

                    <div class="elive-value">
                        {{ $record->phone ?? 'N/A' }}
                    </div>
                </div>

                <div class="elive-detail">
                    <div class="elive-label">
                        Category
                    </div>

                    <div class="elive-value">
                        {{ $record->category?->name ?? 'N/A' }}
                    </div>
                </div>

                <div class="elive-detail">
                    <div class="elive-label">
                        Badge Type
                    </div>

                    <div class="elive-value">
                        {{ $record->badgeType?->name ?? 'N/A' }}
                    </div>
                </div>
            </div>

            <div class="elive-url">
                <div class="elive-label">
                    Secure QR Token
                </div>

                <div class="elive-url-value">
                    The secure check-in link is embedded inside this QR code.
                    PNG and SVG downloads use the same attendee QR token.
                </div>
            </div>

            <div class="elive-actions">

                @if ($qrCodeUrl)
                    <a
                        href="{{ $qrCodeUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="elive-btn elive-btn-primary"
                    >
                        <svg
                            class="elive-icon"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 10l4.553-4.553a1.5 1.5 0 00-2.121-2.121L12.879 7.879M14 4h6v6M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4"
                            />
                        </svg>

                        Open QR Code
                    </a>
                @endif

                <button
                    type="button"
                    wire:click="mountAction('downloadQrCode')"
                    class="elive-btn elive-btn-download"
                >
                    <svg
                        class="elive-icon"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 4v12m0 0l-4-4m4 4l4-4M5 20h14"
                        />
                    </svg>

                    Download QR Code
                </button>

                <button
                    type="button"
                    onclick="window.print()"
                    class="elive-btn elive-btn-dark"
                >
                    <svg
                        class="elive-icon"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 9V4h12v5M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v6H6v-6z"
                        />
                    </svg>

                    Print
                </button>

                <a
                    href="{{ \App\Filament\Resources\Attendees\AttendeeResource::getUrl('edit', ['record' => $record]) }}"
                    class="elive-btn elive-btn-light"
                >
                    Back to Attendee
                </a>
            </div>

        </div>
    </div>
</x-filament-panels::page>