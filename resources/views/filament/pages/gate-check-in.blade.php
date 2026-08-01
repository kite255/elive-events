<x-filament-panels::page>
    <div class="space-y-6">

        <div style="
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        ">
            <h2 style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 0;">
                QR Gate Scanner
            </h2>

            <p style="margin-top: 6px; color: #64748b; font-size: 14px;">
                Scan attendee QR codes or enter badge numbers for event check-in.
            </p>

            @if ($eventName)
                <div style="
                    margin-top: 14px;
                    display: inline-flex;
                    background: #eff6ff;
                    color: #1d4ed8;
                    border: 1px solid #bfdbfe;
                    border-radius: 999px;
                    padding: 6px 12px;
                    font-size: 13px;
                    font-weight: 700;
                ">
                    Event: {{ $eventName }}
                </div>
            @else
                <div style="
                    margin-top: 14px;
                    display: inline-flex;
                    background: #fef3c7;
                    color: #92400e;
                    border: 1px solid #fde68a;
                    border-radius: 999px;
                    padding: 6px 12px;
                    font-size: 13px;
                    font-weight: 700;
                ">
                    No event selected
                </div>
            @endif
        </div>

        <div style="
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        ">
            <div style="
                display: grid;
                grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr);
                gap: 20px;
                align-items: start;
            ">
                <div>
                    <div id="reader" style="
                        width: 100%;
                        min-height: 360px;
                        background: #0f172a;
                        border-radius: 18px;
                        overflow: hidden;
                        border: 1px solid #1e293b;
                    "></div>

                    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px;">
                        <button
                            type="button"
                            onclick="startScanner()"
                            style="
                                background: #16a34a;
                                color: white;
                                border-radius: 12px;
                                padding: 10px 16px;
                                font-weight: 800;
                                border: none;
                                cursor: pointer;
                            "
                        >
                            Start Scanner
                        </button>

                        <button
                            type="button"
                            onclick="stopScanner()"
                            style="
                                background: #475569;
                                color: white;
                                border-radius: 12px;
                                padding: 10px 16px;
                                font-weight: 800;
                                border: none;
                                cursor: pointer;
                            "
                        >
                            Stop Scanner
                        </button>
                    </div>

                    <p id="scanner-status" style="margin-top: 10px; color: #64748b; font-size: 13px;">
                        Scanner is ready. Click Start Scanner.
                    </p>
                </div>

                <div>
                    <form wire:submit.prevent="verifyCode" class="space-y-5">
                        {{ $this->form }}

                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <x-filament::button
                                type="submit"
                                icon="heroicon-o-check-circle"
                                color="success"
                            >
                                Verify and Check-In
                            </x-filament::button>

                            <x-filament::button
                                type="button"
                                color="gray"
                                icon="heroicon-o-arrow-path"
                                wire:click="resetScanner"
                            >
                                Reset
                            </x-filament::button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if ($checkInResult)
            <div style="
                background: #ffffff;
                border: 1px solid {{ $checkInResult['success'] ?? false ? '#bbf7d0' : '#fecaca' }};
                border-radius: 18px;
                padding: 22px;
                box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            ">
                <div style="display: flex; justify-content: space-between; gap: 18px; flex-wrap: wrap;">
                    <div>
                        <h3 style="
                            font-size: 20px;
                            font-weight: 800;
                            color: {{ $checkInResult['success'] ?? false ? '#166534' : '#991b1b' }};
                            margin: 0;
                        ">
                            @if (($checkInResult['status'] ?? null) === 'checked_in')
                                Check-in Successful
                            @elseif (($checkInResult['status'] ?? null) === 'already_checked_in')
                                Duplicate Check-in Blocked
                            @elseif (($checkInResult['status'] ?? null) === 'wrong_event')
                                Wrong Event
                            @else
                                Invalid Code
                            @endif
                        </h3>

                        <p style="margin-top: 8px; color: #475569; font-size: 14px;">
                            {{ $checkInResult['message'] ?? 'No message available.' }}
                        </p>
                    </div>

                    <div style="
                        background: {{ $checkInResult['success'] ?? false ? '#dcfce7' : '#fee2e2' }};
                        color: {{ $checkInResult['success'] ?? false ? '#166534' : '#991b1b' }};
                        border-radius: 999px;
                        padding: 8px 14px;
                        height: fit-content;
                        font-size: 13px;
                        font-weight: 800;
                    ">
                        {{ strtoupper(str_replace('_', ' ', $checkInResult['status'] ?? 'unknown')) }}
                    </div>
                </div>

                @if ($attendee)
                    <div style="
                        margin-top: 18px;
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                        gap: 12px;
                    ">
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px;">
                            <div style="font-size: 11px; color: #64748b; font-weight: 800; text-transform: uppercase;">Attendee</div>
                            <div style="font-size: 15px; font-weight: 800; color: #0f172a; margin-top: 5px;">
                                {{ $attendee->full_name }}
                            </div>
                        </div>

                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px;">
                            <div style="font-size: 11px; color: #64748b; font-weight: 800; text-transform: uppercase;">Badge Number</div>
                            <div style="font-size: 15px; font-weight: 800; color: #0f172a; margin-top: 5px;">
                                {{ $attendee->badge_number ?? 'N/A' }}
                            </div>
                        </div>

                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px;">
                            <div style="font-size: 11px; color: #64748b; font-weight: 800; text-transform: uppercase;">Category</div>
                            <div style="font-size: 15px; font-weight: 800; color: #0f172a; margin-top: 5px;">
                                {{ $attendee->category?->name ?? 'N/A' }}
                            </div>
                        </div>

                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px;">
                            <div style="font-size: 11px; color: #64748b; font-weight: 800; text-transform: uppercase;">Badge Type</div>
                            <div style="font-size: 15px; font-weight: 800; color: #0f172a; margin-top: 5px;">
                                {{ $attendee->badgeType?->name ?? 'N/A' }}
                            </div>
                        </div>

                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px;">
                            <div style="font-size: 11px; color: #64748b; font-weight: 800; text-transform: uppercase;">Phone</div>
                            <div style="font-size: 15px; font-weight: 800; color: #0f172a; margin-top: 5px;">
                                {{ $attendee->phone ?? 'N/A' }}
                            </div>
                        </div>

                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px;">
                            <div style="font-size: 11px; color: #64748b; font-weight: 800; text-transform: uppercase;">Checked In At</div>
                            <div style="font-size: 15px; font-weight: 800; color: #0f172a; margin-top: 5px;">
                                {{ $attendee->checked_in_at?->format('d M Y, H:i') ?? 'Not checked in' }}
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

        <script>
            let html5QrCode = null;
            let scannerRunning = false;
            let lastScannedCode = null;
            let lastScannedAt = 0;

            function setScannerStatus(message) {
                const status = document.getElementById('scanner-status');

                if (status) {
                    status.innerText = message;
                }
            }

            function findCodeInput() {
                return document.querySelector('input[id$="code"]')
                    || document.querySelector('input[name$="[code]"]')
                    || document.querySelector('input[wire\\:model$="data.code"]')
                    || document.querySelector('input[type="text"]');
            }

            async function startScanner() {
                if (scannerRunning) {
                    setScannerStatus('Scanner is already running.');
                    return;
                }

                const reader = document.getElementById('reader');

                if (!reader) {
                    setScannerStatus('Scanner area was not found.');
                    return;
                }

                html5QrCode = new Html5Qrcode('reader');

                const config = {
                    fps: 10,
                    qrbox: {
                        width: 260,
                        height: 260,
                    },
                    aspectRatio: 1.0,
                };

                try {
                    await html5QrCode.start(
                        {
                            facingMode: {
                                exact: 'environment',
                            },
                        },
                        config,
                        onScanSuccess,
                        onScanFailure
                    );

                    scannerRunning = true;
                    setScannerStatus('Scanner started using back camera.');
                } catch (error) {
                    try {
                        await html5QrCode.start(
                            {
                                facingMode: 'environment',
                            },
                            config,
                            onScanSuccess,
                            onScanFailure
                        );

                        scannerRunning = true;
                        setScannerStatus('Scanner started.');
                    } catch (fallbackError) {
                        setScannerStatus('Could not start camera. Check browser camera permission.');
                        console.error(fallbackError);
                    }
                }
            }

            async function stopScanner() {
                if (!html5QrCode || !scannerRunning) {
                    setScannerStatus('Scanner is not running.');
                    return;
                }

                try {
                    await html5QrCode.stop();
                    await html5QrCode.clear();

                    scannerRunning = false;
                    setScannerStatus('Scanner stopped.');
                } catch (error) {
                    setScannerStatus('Could not stop scanner.');
                    console.error(error);
                }
            }

            function onScanSuccess(decodedText) {
                const now = Date.now();

                if (decodedText === lastScannedCode && now - lastScannedAt < 3000) {
                    return;
                }

                lastScannedCode = decodedText;
                lastScannedAt = now;

                const input = findCodeInput();

                if (!input) {
                    setScannerStatus('Code input was not found.');
                    return;
                }

                input.value = decodedText;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));

                setScannerStatus('Code scanned. Submitting for verification.');

                setTimeout(() => {
                    const form = input.closest('form');

                    if (form) {
                        form.dispatchEvent(new Event('submit', {
                            bubbles: true,
                            cancelable: true,
                        }));
                    }
                }, 300);
            }

            function onScanFailure(error) {
                // Do not show repeated scan errors.
            }
        </script>

        <style>
            @media (max-width: 900px) {
                div[style*="grid-template-columns: minmax(0, 1.2fr)"] {
                    grid-template-columns: 1fr !important;
                }
            }
        </style>

    </div>
</x-filament-panels::page>