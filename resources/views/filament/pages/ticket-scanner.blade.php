@vite('resources/js/ticket-scanner.js')

<x-filament-panels::page>
    <div
        class="elive-scanner"
        x-data="{
            manualQr: '',
            manualOpen: false,
            result: null,
            busy: false,

            cameraActive: false,
            cameraStarting: false,
            cameraError: null,
            scanGuideSize: 0,
            html5QrCode: null,
            lastDetectedValue: null,
            lastDetectedAt: 0,
            scanCooldownMs: 2500,

            async submitQr(value) {
                const qr = (value || '').trim();

                if (! qr || this.busy || this.result) {
                    return;
                }

                this.busy = true;
                this.result = null;

                try {
                    const response = await fetch(
                        @js($this->getScanUrl()),
                        {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document
                                    .querySelector('meta[name=csrf-token]')
                                    ?.getAttribute('content') ?? '',
                            },
                            body: JSON.stringify({
                                qr_token: qr,
                            }),
                        }
                    );

                    if (response.status === 401 || response.status === 419) {
                        this.result = {
                            success: false,
                            status: 'unauthenticated',
                            message: 'Your session has expired. Please sign in again.',
                        };

                        return;
                    }

                    if (response.status === 403) {
                        this.result = {
                            success: false,
                            status: 'forbidden',
                            message: 'You are not authorized to scan tickets for this event.',
                        };

                        return;
                    }

                    if (response.status === 422) {
                        this.result = {
                            success: false,
                            status: 'validation_error',
                            message: 'The scanned QR value is invalid.',
                        };

                        return;
                    }

                    const payload = await response.json();

                    this.result = payload;
                    this.manualOpen = false;

                    if (payload?.status === 'checked_in') {
                        this.manualQr = '';
                    }
                } catch (error) {
                    console.error('Ticket scanner request failed:', error);

                    this.result = {
                        success: false,
                        status: 'network_error',
                        message: 'Unable to reach the ticket scanner service.',
                    };
                } finally {
                    this.busy = false;
                }
            },

            scanNext() {
                if (this.busy) {
                    return;
                }

                this.result = null;
                this.manualQr = '';
                this.manualOpen = false;
                this.lastDetectedAt = Date.now();
            },

            openManual() {
                this.scanNext();
                this.manualOpen = true;
                this.$nextTick(() => this.$refs.ticketNumber.focus());
            },

            async startCamera() {
                if (this.$wire.attendanceTab !== 'scan' || this.cameraActive || this.cameraStarting) {
                    return;
                }

                this.cameraError = null;
                this.cameraStarting = true;

                try {
                    if (
                        typeof window.Html5Qrcode === 'undefined'
                        || typeof window.Html5QrcodeSupportedFormats === 'undefined'
                    ) {
                        throw new Error('html5-qrcode is not loaded');
                    }

                    this.html5QrCode = new window.Html5Qrcode(
                        'ticket-scanner-camera-reader',
                        {
                            formatsToSupport: [
                                window.Html5QrcodeSupportedFormats.QR_CODE,
                            ],
                            verbose: false,
                        }
                    );

                    await this.html5QrCode.start(
                        {
                            facingMode: 'environment',
                        },
                        {
                            fps: 10,
                            qrbox: (viewfinderWidth, viewfinderHeight) => {
                                const edge = Math.min(
                                    viewfinderWidth,
                                    viewfinderHeight
                                );

                                const size = Math.floor(edge * 0.72);
                                this.scanGuideSize = size;

                                return {
                                    width: size,
                                    height: size,
                                };
                            },
                        },
                        async (decodedText) => {
                            await this.handleDetectedQr(decodedText);
                        },
                        () => {
                            // Normal frames without a readable QR are ignored.
                        }
                    );

                    this.cameraActive = true;

                    if (this.$wire.attendanceTab !== 'scan') {
                        await this.stopCamera();
                    }
                } catch (error) {
                    console.error(
                        'Unable to start ticket scanner camera:',
                        error
                    );

                    const errorText = String(
                        error?.message ?? error ?? ''
                    ).toLowerCase();

                    if (
                        error?.name === 'NotAllowedError'
                        || errorText.includes('permission')
                        || errorText.includes('notallowed')
                    ) {
                        this.cameraError =
                            'Camera permission was denied. Allow camera access or enter the ticket number.';
                    } else if (
                        error?.name === 'NotFoundError'
                        || errorText.includes('notfound')
                        || errorText.includes('no camera')
                    ) {
                        this.cameraError =
                            'No usable camera was found on this device.';
                    } else if (
                        errorText.includes('html5-qrcode')
                    ) {
                        this.cameraError =
                            'The QR scanner could not load. Refresh the page or enter the ticket number.';
                    } else {
                        this.cameraError =
                            'Unable to start the camera. Enter the ticket number to check in the guest.';
                    }

                    await this.stopCamera();
                } finally {
                    this.cameraStarting = false;
                }
            },

            async stopCamera() {
                if (this.html5QrCode) {
                    try {
                        if (this.cameraActive) {
                            await this.html5QrCode.stop();
                        }
                    } catch (error) {
                        console.debug(
                            'Ticket scanner camera stop skipped:',
                            error
                        );
                    }

                    try {
                        this.html5QrCode.clear();
                    } catch (error) {
                        console.debug(
                            'Ticket scanner clear skipped:',
                            error
                        );
                    }

                    this.html5QrCode = null;
                }

                this.cameraActive = false;
            },

            async handleDetectedQr(value) {
                const qr = (value || '').trim();

                if (this.$wire.attendanceTab !== 'scan' || this.manualOpen || ! qr || this.busy || this.result) {
                    return;
                }

                const now = Date.now();

                if (
                    this.lastDetectedValue === qr
                    && (
                        now - this.lastDetectedAt
                    ) < this.scanCooldownMs
                ) {
                    return;
                }

                this.lastDetectedValue = qr;
                this.lastDetectedAt = now;

                await this.submitQr(qr);
            },

            resultTone() {
                if (this.result?.status === 'checked_in') {
                    return 'elive-scanner-result--approved';
                }

                if (this.result?.status === 'already_used') {
                    return 'elive-scanner-result--duplicate';
                }

                return 'elive-scanner-result--error';
            },

            resultTitle() {
                if (this.busy) {
                    return 'Checking ticket…';
                }

                if (! this.result) {
                    return 'Ready to scan';
                }

                if (this.result.status === 'checked_in') {
                    return 'Entry approved';
                }

                if (this.result.status === 'already_used') {
                    return 'Already checked in';
                }

                if (this.result.status === 'ticket_not_found') {
                    return 'Invalid ticket';
                }

                if (this.result.status === 'forbidden') {
                    return 'Access denied';
                }

                if (this.result.status === 'unauthenticated') {
                    return 'Session expired';
                }

                if (this.result.status === 'ticket_not_usable') {
                    return 'Entry unavailable';
                }

                return 'Scan failed';
            },
        }"
        x-init="
            $watch('$wire.attendanceTab', tab => {
                if (tab !== 'scan' && cameraActive) stopCamera()
            });
            window.addEventListener(
                'beforeunload',
                () => stopCamera()
            )
        "
    >
        <header class="elive-scanner-heading">
            <span class="elive-scanner-brand"><strong>eLive</strong> Events</span>
            <h1>Ticket Scanner</h1>
        </header>

        @if ($assignedEvent = $this->getAssignedEventLabel())
            <p class="elive-scanner-muted">{{ $assignedEvent }}</p>
        @endif

        <nav aria-label="Ticket scanner views" class="elive-scanner-tabs">
            @foreach (['scan' => 'Scan', 'checked_in' => 'Checked In', 'pending' => 'Pending'] as $tab => $label)
                <button
                    type="button"
                    wire:click="showAttendance('{{ $tab }}')"
                    wire:loading.attr="disabled"
                    wire:target="showAttendance"
                    aria-pressed="{{ $attendanceTab === $tab ? 'true' : 'false' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>

        @if ($attendanceTab !== 'scan')
            <div wire:key="scanner-attendance-{{ $attendanceTab }}">
                @include('filament.pages.partials.ticket-scanner-attendance')
            </div>
        @endif

        <div wire:ignore wire:key="scanner-camera-workspace" x-show="$wire.attendanceTab === 'scan'" class="elive-scanner-workspace">
            <div
                id="ticket-scanner-camera"
                class="elive-scanner-camera"
                :class="{ 'elive-scanner-camera--result': !!result }"
            >
                <div id="ticket-scanner-camera-reader"></div>
                <div class="elive-scanner-camera-idle" x-show="! cameraActive">
                    <span x-text="cameraStarting ? 'Starting camera…' : 'Camera is off'"></span>
                </div>

                <span class="elive-scanner-camera-status" x-show="cameraActive && ! result" x-cloak>Camera active</span>
                <div
                    class="elive-scanner-guides"
                    :class="{ 'elive-scanner-guides--live': cameraActive && ! result && scanGuideSize }"
                    :style="{ '--scanner-guide-size': scanGuideSize + 'px' }"
                    aria-hidden="true"
                >
                    <span></span><span></span><span></span><span></span>
                </div>
            </div>

            <p class="elive-scanner-camera-error" x-show="cameraError" x-text="cameraError" x-cloak role="alert"></p>
            <p class="elive-scanner-instruction" x-show="! result" x-text="busy ? 'Checking ticket…' : 'Align the ticket QR code'" role="status"></p>

            <div class="elive-scanner-controls" x-show="! result">
                <button
                    type="button"
                    class="elive-scanner-button"
                    x-show="! cameraActive"
                    x-bind:disabled="cameraStarting"
                    x-on:click="startCamera()"
                >Start Camera</button>
                <button
                    type="button"
                    class="elive-scanner-link"
                    x-show="cameraActive"
                    x-on:click="stopCamera()"
                    x-cloak
                >Stop Camera</button>
            </div>

            <div
                id="ticket-scanner-result"
                class="elive-scanner-result"
                :class="resultTone()"
                x-show="result"
                x-cloak
                role="status"
                aria-atomic="true"
            >
                <h2 class="elive-scanner-result-title" x-text="resultTitle()"></h2>

                <div class="elive-scanner-result-details">
                    <div class="elive-scanner-ticket" x-show="result?.ticket">
                        <div>
                            <p class="elive-scanner-holder" x-text="result?.ticket?.holder_name || 'Name unavailable'"></p>
                            <p class="elive-scanner-number" x-text="result?.ticket?.ticket_number"></p>
                        </div>
                        <div class="elive-scanner-ticket-type" x-show="result?.ticket_type">
                            <p>Ticket type</p>
                            <span
                                class="elive-scanner-badge"
                                :class="{ 'elive-scanner-badge--vip': result?.ticket_type?.trim().toLowerCase() === 'vip' }"
                                x-text="result?.ticket_type"
                            ></span>
                        </div>
                    </div>
                    <div class="elive-scanner-duplicate-details" x-show="result?.status === 'already_used'">
                        <p x-show="result?.checked_in_time_eat">Previous check-in <strong x-text="result?.checked_in_time_eat"></strong></p>
                        <p class="elive-scanner-warning">Do not admit again</p>
                    </div>
                    <p x-show="result && ! ['checked_in', 'already_used'].includes(result.status)" x-text="result?.message"></p>
                </div>
            </div>

            <button type="button" class="elive-scanner-button" x-show="result" x-on:click="scanNext()" x-cloak>
                Scan Next
            </button>
            <button
                type="button"
                class="elive-scanner-manual-toggle"
                :class="{ 'elive-scanner-link': !!result }"
                x-show="! manualOpen"
                x-bind:disabled="busy"
                x-on:click="openManual()"
            >Enter Ticket Number</button>

            <form id="ticket-scanner-manual-input" class="elive-scanner-manual" x-show="manualOpen" x-on:submit.prevent="submitQr(manualQr)" x-cloak>
                <label for="ticket-scanner-manual-value">Ticket number</label>
                <input
                    id="ticket-scanner-manual-value"
                    type="text"
                    x-ref="ticketNumber"
                    x-model="manualQr"
                    x-bind:disabled="busy || !!result"
                    maxlength="255"
                    autocomplete="off"
                    autocapitalize="off"
                    spellcheck="false"
                    placeholder="Enter the number printed on the ticket"
                >
                <div class="elive-scanner-manual-actions">
                    <button type="button" class="elive-scanner-link" x-on:click="manualOpen = false">Cancel</button>
                    <button type="submit" class="elive-scanner-button" x-bind:disabled="busy || !!result || ! manualQr.trim()">Check In Ticket</button>
                </div>
            </form>
        </div>
    </div>
</x-filament-panels::page>
