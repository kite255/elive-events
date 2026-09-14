@vite('resources/js/ticket-scanner.js')

<x-filament-panels::page>
    <div
        class="space-y-6"
        x-data="{
            manualQr: '',
            result: null,
            busy: false,

            cameraActive: false,
            cameraStarting: false,
            cameraError: null,
            html5QrCode: null,
            lastDetectedValue: null,
            lastDetectedAt: 0,
            scanCooldownMs: 2500,

            async submitQr(value) {
                const qr = (value || '').trim();

                if (! qr || this.busy) {
                    return;
                }

                this.busy = true;
                this.result = null;

                try {
                    const response = await fetch(
                        @js(route('admin.ticket-scanner.scan')),
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

                    if (response.status === 401) {
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

            async startCamera() {
                if (this.cameraActive || this.cameraStarting) {
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
                            'Camera permission was denied. Allow camera access or use the manual QR input.';
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
                            'The QR scanner could not load. Rebuild the frontend assets and refresh this page.';
                    } else {
                        this.cameraError =
                            'Unable to start the camera. Use the manual QR input if needed.';
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

                if (! qr || this.busy) {
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

            resultClass() {
                if (! this.result) {
                    return 'border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900';
                }

                if (this.result.status === 'checked_in') {
                    return 'border-success-300 bg-success-50 dark:border-success-700 dark:bg-success-950/40';
                }

                if (this.result.status === 'already_used') {
                    return 'border-warning-300 bg-warning-50 dark:border-warning-700 dark:bg-warning-950/40';
                }

                return 'border-danger-300 bg-danger-50 dark:border-danger-700 dark:bg-danger-950/40';
            },

            resultTitle() {
                if (! this.result) {
                    return 'Ready to scan';
                }

                if (this.result.status === 'checked_in') {
                    return 'Entry approved';
                }

                if (this.result.status === 'already_used') {
                    return 'Already used';
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

                return 'Scan failed';
            },
        }"
        x-init="
            window.addEventListener(
                'beforeunload',
                () => stopCamera()
            )
        "
    >
        <x-filament::section>
            <x-slot name="heading">
                Ticket Scanner
            </x-slot>

            <x-slot name="description">
                Scan a secure eLive ticket QR code or enter the ticket credential manually.
            </x-slot>

            <div class="grid gap-6 xl:grid-cols-2">
                <div
                    id="ticket-scanner-camera"
                    class="rounded-2xl border border-gray-200 bg-gray-950 p-4 shadow-sm dark:border-white/10"
                >
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-white">
                                Camera Scanner
                            </h3>

                            <p class="mt-1 text-xs text-gray-300">
                                Position the QR code inside the scanner frame.
                            </p>
                        </div>

                        <span
                            class="rounded-full px-3 py-1 text-xs font-medium"
                            :class="cameraActive
                                ? 'bg-success-500/20 text-success-300'
                                : 'bg-gray-800 text-gray-300'"
                            x-text="cameraActive ? 'Camera active' : 'Camera stopped'"
                        ></span>
                    </div>

                    <div
                        id="ticket-scanner-camera-reader"
                        class="min-h-[360px] overflow-hidden rounded-xl border border-gray-700 bg-black"
                    >
                        <div
                            class="flex min-h-[360px] items-center justify-center"
                            x-show="! cameraActive && ! cameraStarting"
                        >
                            <div class="max-w-sm px-6 text-center">
                                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10 text-white">
                                    <x-filament::icon
                                        icon="heroicon-o-qr-code"
                                        class="h-9 w-9"
                                    />
                                </div>

                                <p class="mt-4 text-sm font-medium text-white">
                                    Camera is not running
                                </p>

                                <p class="mt-2 text-xs leading-5 text-gray-400">
                                    Start the camera to scan ticket QR codes automatically.
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex min-h-[360px] items-center justify-center text-sm text-gray-300"
                            x-show="cameraStarting"
                        >
                            Starting camera...
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <button
                            type="button"
                            class="fi-btn fi-btn-size-md fi-btn-color-primary inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
                            x-on:click="startCamera()"
                            x-bind:disabled="cameraActive || cameraStarting"
                        >
                            <span x-show="! cameraStarting">
                                Start Camera
                            </span>

                            <span x-show="cameraStarting">
                                Starting...
                            </span>
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-gray-600 px-4 py-2 text-sm font-semibold text-gray-200 transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50"
                            x-on:click="stopCamera()"
                            x-bind:disabled="! cameraActive"
                        >
                            Stop Camera
                        </button>
                    </div>

                    <div
                        class="mt-4 rounded-xl border border-danger-500/30 bg-danger-500/10 px-4 py-3 text-sm text-danger-200"
                        x-show="cameraError"
                        x-text="cameraError"
                    ></div>
                </div>

                <div class="space-y-6">
                    <div
                        id="ticket-scanner-manual-input"
                        class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900"
                    >
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                            Manual QR Credential
                        </h3>

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Paste or type the secure QR credential as a fallback.
                        </p>

                        <form
                            class="mt-4 space-y-3"
                            x-on:submit.prevent="submitQr(manualQr)"
                        >
                            <label
                                for="ticket-scanner-manual-value"
                                class="sr-only"
                            >
                                Ticket QR credential
                            </label>

                            <textarea
                                id="ticket-scanner-manual-value"
                                x-model="manualQr"
                                rows="4"
                                autocomplete="off"
                                spellcheck="false"
                                placeholder="Paste scanned QR credential here"
                                class="block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-950 shadow-sm outline-none transition focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                            ></textarea>

                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    class="fi-btn fi-btn-size-md fi-btn-color-primary inline-flex items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
                                    x-bind:disabled="busy || ! manualQr.trim()"
                                >
                                    <span x-show="! busy">
                                        Validate Ticket
                                    </span>

                                    <span x-show="busy">
                                        Validating...
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div
                        id="ticket-scanner-result"
                        class="rounded-2xl border p-5 shadow-sm transition"
                        x-bind:class="resultClass()"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                                :class="{
                                    'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-300': !result,
                                    'bg-success-100 text-success-700 dark:bg-success-900/50 dark:text-success-300': result?.status === 'checked_in',
                                    'bg-warning-100 text-warning-700 dark:bg-warning-900/50 dark:text-warning-300': result?.status === 'already_used',
                                    'bg-danger-100 text-danger-700 dark:bg-danger-900/50 dark:text-danger-300':
                                        result && !['checked_in', 'already_used'].includes(result.status),
                                }"
                            >
                                <x-filament::icon
                                    icon="heroicon-o-check-circle"
                                    class="h-6 w-6"
                                    x-show="result?.status === 'checked_in'"
                                />

                                <x-filament::icon
                                    icon="heroicon-o-exclamation-triangle"
                                    class="h-6 w-6"
                                    x-show="result?.status === 'already_used'"
                                />

                                <x-filament::icon
                                    icon="heroicon-o-qr-code"
                                    class="h-6 w-6"
                                    x-show="! result"
                                />

                                <x-filament::icon
                                    icon="heroicon-o-x-circle"
                                    class="h-6 w-6"
                                    x-show="result && !['checked_in', 'already_used'].includes(result.status)"
                                />
                            </div>

                            <div class="min-w-0 flex-1">
                                <h3
                                    class="text-base font-semibold text-gray-950 dark:text-white"
                                    x-text="resultTitle()"
                                ></h3>

                                <p
                                    class="mt-1 text-sm text-gray-600 dark:text-gray-300"
                                    x-text="result?.message ?? 'Waiting for a ticket QR code.'"
                                ></p>

                                <dl
                                    class="mt-4 grid gap-3 sm:grid-cols-2"
                                    x-show="result?.ticket"
                                >
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Ticket
                                        </dt>
                                        <dd
                                            class="mt-1 text-sm font-semibold text-gray-950 dark:text-white"
                                            x-text="result?.ticket?.ticket_number ?? '—'"
                                        ></dd>
                                    </div>

                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Holder
                                        </dt>
                                        <dd
                                            class="mt-1 text-sm font-semibold text-gray-950 dark:text-white"
                                            x-text="result?.ticket?.holder_name ?? '—'"
                                        ></dd>
                                    </div>

                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Status
                                        </dt>
                                        <dd
                                            class="mt-1 text-sm font-semibold text-gray-950 dark:text-white"
                                            x-text="result?.ticket?.status ?? result?.status ?? '—'"
                                        ></dd>
                                    </div>

                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            Check-in Time
                                        </dt>
                                        <dd
                                            class="mt-1 text-sm font-semibold text-gray-950 dark:text-white"
                                            x-text="result?.checked_in_at ?? '—'"
                                        ></dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
