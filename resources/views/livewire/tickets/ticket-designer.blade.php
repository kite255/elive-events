@php
    /*
     * Use a harmless, static credential for the designer preview. Rendering
     * it through the same QR library and settings as the ticket exporter
     * keeps the editor's quiet zone, proportions and alignment accurate
     * without exposing a real ticket credential.
     */
    $ticketDesignerQrPreview = (string) \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')
        ->size(800)
        ->margin(4)
        ->generate('ELIVE-TICKET-DESIGNER-PREVIEW');
@endphp

<div
    data-testid="ticket-designer"
    class="space-y-4"
>
    <style>
        .elive-ticket-designer {
            --elive-navy: #161943;
            --elive-blue: #007ab2;
            --elive-orange: #ff9800;
            --elive-bg: #f6f8fc;
            --elive-border: #e5e7eb;
            --elive-muted: #64748b;
            --elive-white: #ffffff;
        }

        .elive-ticket-designer * {
            box-sizing: border-box;
        }

        .elive-ticket-designer .designer-shell {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr) 280px;
            min-height: 760px;
            border: 1px solid var(--elive-border);
            border-radius: 18px;
            overflow: hidden;
            background: var(--elive-white);
        }

        .elive-ticket-designer .designer-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border: 1px solid var(--elive-border);
            border-radius: 16px;
            background: var(--elive-white);
        }

        .elive-ticket-designer .designer-title {
            min-width: 0;
        }

        .elive-ticket-designer .designer-title h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: var(--elive-navy);
        }

        .elive-ticket-designer .designer-title p {
            margin: .25rem 0 0;
            font-size: .8rem;
            color: var(--elive-muted);
        }

        .elive-ticket-designer .toolbar-actions {
            display: flex;
            align-items: center;
            gap: .75rem;
            flex-wrap: wrap;
        }

        .elive-ticket-designer .save-state {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .4rem .7rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 600;
            background: #f1f5f9;
            color: #475569;
        }

        .elive-ticket-designer .save-state.dirty {
            background: #fff7ed;
            color: #c2410c;
        }

        .elive-ticket-designer .designer-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            min-height: 38px;
            padding: .55rem .85rem;
            border: 1px solid var(--elive-border);
            border-radius: 10px;
            background: var(--elive-white);
            color: #334155;
            font-size: .8rem;
            font-weight: 600;
            cursor: pointer;
            transition: .15s ease;
        }

        .elive-ticket-designer .designer-button:hover:not(:disabled) {
            background: #f8fafc;
        }

        .elive-ticket-designer .designer-button.primary {
            border-color: var(--elive-blue);
            background: var(--elive-blue);
            color: #fff;
        }

        .elive-ticket-designer .designer-button.primary:hover:not(:disabled) {
            background: #00699a;
        }

        .elive-ticket-designer .designer-button:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .elive-ticket-designer .designer-button.danger {
            color: #b91c1c;
        }

        .elive-ticket-designer .designer-panel {
            padding: 1rem;
            background: #fff;
        }

        .elive-ticket-designer .tools-panel {
            border-right: 1px solid var(--elive-border);
        }

        .elive-ticket-designer .properties-panel {
            border-left: 1px solid var(--elive-border);
        }

        .elive-ticket-designer .panel-heading {
            margin: 0 0 .85rem;
            font-size: .8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #475569;
        }

        .elive-ticket-designer .background-section {
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--elive-border);
        }

        .elive-ticket-designer .background-current-label,
        .elive-ticket-designer .background-selected-label {
            margin-bottom: .4rem;
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #64748b;
        }

        .elive-ticket-designer .background-upload {
            display: block;
            width: 100%;
            margin-bottom: .55rem;
            font-size: .72rem;
            color: #475569;
        }

        .elive-ticket-designer .background-preview {
            display: block;
            width: 100%;
            max-height: 150px;
            margin-bottom: .65rem;
            object-fit: contain;
            border: 1px solid var(--elive-border);
            border-radius: 10px;
            background: #f8fafc;
        }

        .elive-ticket-designer .background-selected-file {
            display: flex;
            align-items: center;
            gap: .45rem;
            padding: .55rem .65rem;
            margin-bottom: .65rem;
            border: 1px solid #bae6fd;
            border-radius: 9px;
            background: #f0f9ff;
            color: #075985;
            font-size: .72rem;
            font-weight: 600;
            word-break: break-word;
        }

        .elive-ticket-designer .background-selected-file::before {
            content: "âœ“";
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 999px;
            background: #0284c7;
            color: #fff;
            font-size: .65rem;
            font-weight: 800;
        }

        .elive-ticket-designer .background-empty {
            margin-bottom: .65rem;
            padding: .55rem .65rem;
            border: 1px dashed #cbd5e1;
            border-radius: 9px;
            background: #f8fafc;
            color: #64748b;
            font-size: .7rem;
            text-align: center;
        }

        .elive-ticket-designer .background-actions {
            display: grid;
            gap: .5rem;
        }

        .elive-ticket-designer .background-help {
            margin-top: .55rem;
            font-size: .68rem;
            line-height: 1.45;
            color: #64748b;
        }

        .elive-ticket-designer .upload-progress {
            margin-top: .55rem;
            padding: .5rem .6rem;
            border-radius: 8px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: .7rem;
            font-weight: 600;
        }

        .elive-ticket-designer .tool-grid {
            display: grid;
            gap: .55rem;
        }

        .elive-ticket-designer .tool-button {
            width: 100%;
            justify-content: flex-start;
        }

        .elive-ticket-designer .canvas-area {
            display: flex;
            flex-direction: column;
            min-width: 0;
            background: var(--elive-bg);
        }

        .elive-ticket-designer .canvas-scroll {
            flex: 1;
            overflow: auto;
            padding: 2rem;
        }

        .elive-ticket-designer .canvas-frame {
            position: relative;
            margin: 0 auto;
            overflow: hidden;
            background: #fff;
            border: 1px solid #cbd5e1;
            box-shadow: 0 14px 36px rgba(15, 23, 42, .10);
            transform-origin: top left;
        }

        .elive-ticket-designer .designer-background {
            position: absolute;
            inset: 0;
            z-index: 0;
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
            pointer-events: none;
            user-select: none;
        }

        .elive-ticket-designer .designer-element {
            position: absolute;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            cursor: move;
            border: 1px dashed transparent;
            touch-action: none;
        }

        .elive-ticket-designer .designer-element.selected {
            overflow: visible;
            border-color: var(--elive-blue);
            box-shadow: 0 0 0 2px rgba(0, 122, 178, .10);
        }

        .elive-ticket-designer .designer-element.dragging,
        .elive-ticket-designer .designer-element.resizing {
            z-index: 50;
            cursor: grabbing;
        }

        .elive-ticket-designer .element-resize-handle {
            position: absolute;
            right: -1px;
            bottom: -1px;
            z-index: 60;
            width: 16px;
            height: 16px;
            border: 2px solid #fff;
            border-radius: 4px 0 0 0;
            background: var(--elive-blue);
            box-shadow: 0 1px 4px rgba(15, 23, 42, .25);
            cursor: nwse-resize;
            touch-action: none;
        }

        .elive-ticket-designer .designer-element.text {
            padding: .35rem;
        }

        .elive-ticket-designer .designer-element.qr {
            background-color: #fff;
            overflow: hidden;
        }

        .elive-ticket-designer .designer-element.qr > svg {
            display: block;
            width: 100%;
            height: 100%;
            pointer-events: none;
            user-select: none;
        }

        .elive-ticket-designer .designer-element.placeholder {
            border: 1px dashed #94a3b8;
            background: rgba(248, 250, 252, .92);
            color: #475569;
            font-size: .72rem;
            text-align: center;
            padding: .4rem;
        }

        .elive-ticket-designer .designer-element .element-asset {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
            pointer-events: none;
            user-select: none;
        }

        .elive-ticket-designer .element-image-controls {
            display: grid;
            gap: .55rem;
        }

        .elive-ticket-designer .pages-bar {
            display: flex;
            align-items: center;
            gap: .5rem;
            overflow-x: auto;
            padding: .85rem 1rem;
            border-top: 1px solid var(--elive-border);
            background: #fff;
        }

        .elive-ticket-designer .page-tab {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            white-space: nowrap;
            border: 1px solid var(--elive-border);
            border-radius: 9px;
            padding: .5rem .7rem;
            background: #fff;
            color: #475569;
            font-size: .75rem;
            cursor: pointer;
        }

        .elive-ticket-designer .page-tab.active {
            border-color: var(--elive-blue);
            color: var(--elive-blue);
            background: #f0f9ff;
            font-weight: 700;
        }

        .elive-ticket-designer .property-section {
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid #eef2f7;
        }

        .elive-ticket-designer .property-section:last-child {
            border-bottom: 0;
            margin-bottom: 0;
        }

        .elive-ticket-designer .property-section-title {
            margin: 0 0 .65rem;
            font-size: .75rem;
            font-weight: 700;
            color: #334155;
        }

        .elive-ticket-designer .property-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .55rem;
        }

        .elive-ticket-designer .property-field {
            display: grid;
            gap: .25rem;
        }

        .elive-ticket-designer .property-field.full {
            grid-column: 1 / -1;
        }

        .elive-ticket-designer .property-field label {
            font-size: .68rem;
            font-weight: 600;
            color: #64748b;
        }

        .elive-ticket-designer .property-field input,
        .elive-ticket-designer .property-field select {
            width: 100%;
            min-height: 36px;
            border: 1px solid #dbe2ea;
            border-radius: 8px;
            padding: .45rem .55rem;
            background: #fff;
            font-size: .75rem;
        }

        .elive-ticket-designer .layer-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .5rem;
        }

        .elive-ticket-designer .empty-properties {
            padding: 1rem;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            color: #64748b;
            font-size: .75rem;
            text-align: center;
        }

        .elive-ticket-designer .error-box {
            padding: .75rem 1rem;
            border-radius: 10px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: .78rem;
        }

        @media (max-width: 1100px) {
            .elive-ticket-designer .designer-shell {
                grid-template-columns: 190px minmax(0, 1fr);
            }

            .elive-ticket-designer .properties-panel {
                grid-column: 1 / -1;
                border-left: 0;
                border-top: 1px solid var(--elive-border);
            }
        }

        @media (max-width: 760px) {
            .elive-ticket-designer .designer-toolbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .elive-ticket-designer .designer-shell {
                display: block;
            }

            .elive-ticket-designer .tools-panel {
                border-right: 0;
                border-bottom: 1px solid var(--elive-border);
            }

            .elive-ticket-designer .tool-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .elive-ticket-designer .canvas-scroll {
                padding: 1rem;
            }
        }
    </style>

    <script>
        window.ticketDesignerElement = window.ticketDesignerElement
            || function (config) {
                return {
                    id: config.id,
                    type: config.type,
                    x: Number(config.x),
                    y: Number(config.y),
                    width: Number(config.width),
                    height: Number(config.height),
                    rotation: Number(config.rotation),
                    scale: Number(config.scale) || 1,
                    canvasWidth: Number(config.canvasWidth),
                    canvasHeight: Number(config.canvasHeight),
                    dragging: false,
                    resizing: false,
                    moved: false,
                    startClientX: 0,
                    startClientY: 0,
                    startX: 0,
                    startY: 0,
                    startWidth: 0,
                    startHeight: 0,
                    moveListener: null,
                    upListener: null,
                    animationFrame: null,
                    pendingPointer: null,

                    get elementStyle() {
                        return [
                            'left: 0px',
                            'top: 0px',
                            `width: ${Math.max(1, this.width * this.scale)}px`,
                            `height: ${Math.max(1, this.height * this.scale)}px`,
                            `transform: translate3d(${this.x * this.scale}px, ${this.y * this.scale}px, 0) rotate(${this.rotation}deg)`,
                            'will-change: transform',
                        ].join('; ');
                    },

                    startDrag(event) {
                        if (event.button !== 0) {
                            return;
                        }

                        event.preventDefault();
                        this.dragging = true;
                        this.moved = false;
                        this.startClientX = event.clientX;
                        this.startClientY = event.clientY;
                        this.startX = this.x;
                        this.startY = this.y;
                        this.bindPointerEvents('drag');
                    },

                    startResize(event) {
                        if (event.button !== 0) {
                            return;
                        }

                        event.preventDefault();
                        event.stopPropagation();
                        this.resizing = true;
                        this.moved = false;
                        this.startClientX = event.clientX;
                        this.startClientY = event.clientY;
                        this.startWidth = this.width;
                        this.startHeight = this.height;
                        this.bindPointerEvents('resize');
                    },

                    bindPointerEvents(mode) {
                        this.removePointerEvents();

                        this.moveListener = (event) =>
                            this.schedulePointerUpdate(
                                mode,
                                event
                            );

                        this.upListener = () => {
                            this.finishInteraction(mode);
                        };

                        window.addEventListener(
                            'pointermove',
                            this.moveListener
                        );

                        window.addEventListener(
                            'pointerup',
                            this.upListener,
                            { once: true }
                        );

                        window.addEventListener(
                            'pointercancel',
                            this.upListener,
                            { once: true }
                        );
                    },

                    schedulePointerUpdate(mode, event) {
                        this.pendingPointer = {
                            clientX: event.clientX,
                            clientY: event.clientY,
                        };

                        if (this.animationFrame !== null) {
                            return;
                        }

                        this.animationFrame =
                            window.requestAnimationFrame(() => {
                                this.animationFrame = null;
                                this.applyPointerUpdate(mode);
                            });
                    },

                    applyPointerUpdate(mode) {
                        if (! this.pendingPointer) {
                            return;
                        }

                        const pointer =
                            this.pendingPointer;

                        this.pendingPointer = null;

                        if (mode === 'drag') {
                            this.dragTo(pointer);
                        } else {
                            this.resizeTo(pointer);
                        }
                    },

                    dragTo(event) {
                        const deltaX =
                            (event.clientX - this.startClientX)
                                / this.scale;

                        const deltaY =
                            (event.clientY - this.startClientY)
                                / this.scale;

                        this.x = this.clamp(
                            this.startX + deltaX,
                            0,
                            Math.max(0, this.canvasWidth - this.width)
                        );

                        this.y = this.clamp(
                            this.startY + deltaY,
                            0,
                            Math.max(0, this.canvasHeight - this.height)
                        );

                        this.moved = true;
                    },

                    resizeTo(event) {
                        const minimumSize = 20;

                        const deltaX =
                            (event.clientX - this.startClientX)
                                / this.scale;

                        const deltaY =
                            (event.clientY - this.startClientY)
                                / this.scale;

                        const maximumWidth =
                            Math.max(minimumSize, this.canvasWidth - this.x);

                        const maximumHeight =
                            Math.max(minimumSize, this.canvasHeight - this.y);

                        if (this.type === 'qr') {
                            const requestedSize = Math.max(
                                this.startWidth + deltaX,
                                this.startHeight + deltaY
                            );

                            const size = this.clamp(
                                requestedSize,
                                minimumSize,
                                Math.min(maximumWidth, maximumHeight)
                            );

                            this.width = size;
                            this.height = size;
                        } else {
                            this.width = this.clamp(
                                this.startWidth + deltaX,
                                minimumSize,
                                maximumWidth
                            );

                            this.height = this.clamp(
                                this.startHeight + deltaY,
                                minimumSize,
                                maximumHeight
                            );
                        }

                        this.moved = true;
                    },

                    nudgeBy(deltaX, deltaY) {
                        this.x = this.clamp(
                            this.x + deltaX,
                            0,
                            Math.max(0, this.canvasWidth - this.width)
                        );

                        this.y = this.clamp(
                            this.y + deltaY,
                            0,
                            Math.max(0, this.canvasHeight - this.height)
                        );
                    },

                    finishInteraction(mode) {
                        if (this.animationFrame !== null) {
                            window.cancelAnimationFrame(
                                this.animationFrame
                            );

                            this.animationFrame = null;
                        }

                        this.applyPointerUpdate(mode);
                        this.removePointerEvents();
                        this.dragging = false;
                        this.resizing = false;

                        if (! this.moved) {
                            this.$wire.selectElement(
                                this.id
                            );

                            return;
                        }

                        if (mode === 'drag') {
                            this.$wire.moveElement(
                                this.id,
                                this.round(this.x),
                                this.round(this.y)
                            );

                            return;
                        }

                        this.$wire.resizeElement(
                            this.id,
                            this.round(this.width),
                            this.round(this.height)
                        );
                    },

                    removePointerEvents() {
                        if (this.animationFrame !== null) {
                            window.cancelAnimationFrame(
                                this.animationFrame
                            );

                            this.animationFrame = null;
                        }

                        this.pendingPointer = null;

                        if (this.moveListener) {
                            window.removeEventListener(
                                'pointermove',
                                this.moveListener
                            );
                        }

                        if (this.upListener) {
                            window.removeEventListener(
                                'pointerup',
                                this.upListener
                            );

                            window.removeEventListener(
                                'pointercancel',
                                this.upListener
                            );
                        }

                        this.moveListener = null;
                        this.upListener = null;
                    },

                    clamp(value, minimum, maximum) {
                        return Math.min(
                            maximum,
                            Math.max(minimum, value)
                        );
                    },

                    round(value) {
                        return Math.round(value * 100) / 100;
                    },
                };
            };

        window.ticketDesignerKeyboard = window.ticketDesignerKeyboard
            || function () {
                return {
                    pendingX: 0,
                    pendingY: 0,
                    nudgeTimer: null,

                    handleKeydown(event) {
                        const target = event.target;

                        if (
                            target instanceof HTMLInputElement
                            || target instanceof HTMLSelectElement
                            || target instanceof HTMLTextAreaElement
                            || target?.isContentEditable
                        ) {
                            return;
                        }

                        if (! this.$root.dataset.selectedElement) {
                            return;
                        }

                        const directions = {
                            ArrowLeft: [-1, 0],
                            ArrowRight: [1, 0],
                            ArrowUp: [0, -1],
                            ArrowDown: [0, 1],
                        };

                        const direction =
                            directions[event.key];

                        if (! direction) {
                            return;
                        }

                        event.preventDefault();

                        const distance =
                            event.shiftKey ? 10 : 1;

                        this.pendingX +=
                            direction[0] * distance;

                        this.pendingY +=
                            direction[1] * distance;

                        if (this.nudgeTimer !== null) {
                            return;
                        }

                        this.nudgeTimer = window.setTimeout(
                            () => this.flushNudge(),
                            50
                        );
                    },

                    flushNudge() {
                        const deltaX = this.pendingX;
                        const deltaY = this.pendingY;

                        this.pendingX = 0;
                        this.pendingY = 0;
                        this.nudgeTimer = null;

                        if (deltaX === 0 && deltaY === 0) {
                            return;
                        }

                        const elementState =
                            this.findElementState(
                                this.$root.dataset.selectedElement
                            );

                        elementState?.nudgeBy(
                            deltaX,
                            deltaY
                        );

                        this.$wire.nudgeSelectedElement(
                            deltaX,
                            deltaY
                        );
                    },

                    syncElementSize(detail) {
                        const elementState =
                            this.findElementState(
                                detail?.elementId
                            );

                        if (! elementState) {
                            return;
                        }

                        elementState.width =
                            Number(detail.width);

                        elementState.height =
                            Number(detail.height);
                    },

                    findElementState(elementId) {
                        if (! elementId) {
                            return null;
                        }

                        const element = Array.from(
                            this.$root.querySelectorAll(
                                '[data-element-id]'
                            )
                        ).find(
                            (candidate) =>
                                candidate.dataset.elementId
                                    === elementId
                        );

                        if (! element || ! window.Alpine) {
                            return null;
                        }

                        return window.Alpine.$data(
                            element
                        );
                    },
                };
            };
    </script>

    @php
        $selectedElement = collect($elements)->first(
            fn (array $element): bool =>
                ($element['id'] ?? null) === $selectedElementId
        );

        $scale = min(
            1,
            720 / max($canvasWidth, 1),
            760 / max($canvasHeight, 1)
        );

        $displayWidth = max(
            240,
            (int) round($canvasWidth * $scale)
        );

        $displayHeight = max(
            300,
            (int) round($canvasHeight * $scale)
        );

        $backgroundUrl =
            $backgroundImagePath
                ? \Illuminate\Support\Facades\Storage::disk('public')
                    ->url($backgroundImagePath)
                : null;

        $selectedBackgroundName =
            $this->backgroundUploadName();

        $selectedBackgroundPreviewUrl =
            $this->backgroundUploadPreviewUrl();

        $backgroundUploadReady =
            $backgroundUpload !== null;

        $selectedElementSupportsImage =
            $selectedElement
            && in_array(
                $selectedElement['type'] ?? null,
                ['image', 'logo', 'sponsor_logo'],
                true
            );

        $selectedElementAssetPath =
            $selectedElementSupportsImage
                ? ($selectedElement['asset_path'] ?? null)
                : null;

        $selectedElementAssetUrl =
            $selectedElementAssetPath
                ? \Illuminate\Support\Facades\Storage::disk('public')
                    ->url($selectedElementAssetPath)
                : null;

        $selectedElementUploadName =
            $this->elementImageUploadName();

        $selectedElementUploadPreviewUrl =
            $this->elementImageUploadPreviewUrl();

        $elementImageUploadReady =
            $elementImageUpload !== null;
    @endphp

    <div
        class="elive-ticket-designer"
        data-selected-element="{{ $selectedElementId ?? '' }}"
        x-data="ticketDesignerKeyboard()"
        x-on:keydown.window="handleKeydown($event)"
        x-on:ticket-element-sized.window="syncElementSize($event.detail)"
    >
        <div
            data-testid="ticket-designer-toolbar"
            class="designer-toolbar"
        >
            <div class="designer-title">
                <h2>{{ $templateName }}</h2>

                <p>
                    {{ $canvasWidth }} Ã— {{ $canvasHeight }} px
                </p>
            </div>

            <div class="toolbar-actions">
                <span
                    class="save-state {{ $isDirty ? 'dirty' : '' }}"
                >
                    @if ($isDirty)
                        Unsaved changes
                    @else
                        Saved
                    @endif
                </span>

                <button
                    type="button"
                    wire:click="save"
                    class="designer-button primary"
                >
                    Save
                </button>
            </div>
        </div>

        @error('definition')
            <div class="error-box">
                {{ $message }}
            </div>
        @enderror

        @error('activePageId')
            <div class="error-box">
                {{ $message }}
            </div>
        @enderror

        <div class="designer-shell">
            <aside
                data-testid="ticket-designer-tools"
                class="designer-panel tools-panel"
            >
                <div
                    data-testid="ticket-designer-background-controls"
                    class="background-section"
                >
                    <h3 class="panel-heading">
                        Background
                    </h3>

                    @if ($selectedBackgroundPreviewUrl)
                        <div class="background-selected-label">
                            Selected image
                        </div>

                        <img
                            data-testid="ticket-background-temporary-preview"
                            src="{{ $selectedBackgroundPreviewUrl }}"
                            alt="Selected ticket background preview"
                            class="background-preview"
                        >

                        <div class="background-selected-file">
                            {{ $selectedBackgroundName }}
                        </div>
                    @elseif ($backgroundUrl)
                        <div class="background-current-label">
                            Current background
                        </div>

                        <img
                            src="{{ $backgroundUrl }}"
                            alt="Current ticket background"
                            class="background-preview"
                        >
                    @else
                        <div class="background-empty">
                            No background uploaded for this page.
                        </div>
                    @endif

                    <input
                        type="file"
                        wire:model="backgroundUpload"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        class="background-upload"
                    >

                    @if (! $selectedBackgroundName)
                        <div
                            style="
                                margin-bottom: .65rem;
                                font-size: .7rem;
                                color: #64748b;
                            "
                        >
                            Select an image before uploading.
                        </div>
                    @endif

                    @error('backgroundUpload')
                        <div
                            class="error-box"
                            style="margin-bottom: .55rem;"
                        >
                            {{ $message }}
                        </div>
                    @enderror

                    <div class="background-actions">
                        <button
                            type="button"
                            data-testid="ticket-background-upload-button"
                            data-upload-ready="{{ $backgroundUploadReady ? 'true' : 'false' }}"
                            wire:click="uploadBackground"
                            wire:loading.attr="disabled"
                            wire:target="backgroundUpload,uploadBackground"
                            @disabled(! $backgroundUploadReady)
                            class="designer-button primary"
                            style="width: 100%;"
                        >
                            <span
                                wire:loading.remove
                                wire:target="uploadBackground"
                            >
                                @if ($backgroundImagePath)
                                    Replace Background
                                @else
                                    Upload Background
                                @endif
                            </span>

                            <span
                                wire:loading
                                wire:target="uploadBackground"
                            >
                                Uploading...
                            </span>
                        </button>

                        @if ($backgroundImagePath)
                            <button
                                type="button"
                                wire:click="removeBackground"
                                wire:loading.attr="disabled"
                                wire:target="removeBackground"
                                class="designer-button danger"
                                style="width: 100%;"
                            >
                                <span
                                    wire:loading.remove
                                    wire:target="removeBackground"
                                >
                                    Remove Background
                                </span>

                                <span
                                    wire:loading
                                    wire:target="removeBackground"
                                >
                                    Removing...
                                </span>
                            </button>
                        @endif
                    </div>

                    <div
                        wire:loading
                        wire:target="backgroundUpload"
                        class="upload-progress"
                    >
                        Preparing image preview...
                    </div>

                    <p class="background-help">
                        JPG, PNG or WEBP, up to 10 MB. Images of
                        any dimensions are fitted inside the ticket
                        canvas without cropping or distortion. Each
                        page can use a different background.
                    </p>
                </div>

                <h3 class="panel-heading">
                    Elements
                </h3>

                <div class="tool-grid">
                    <button
                        type="button"
                        wire:click="addText"
                        class="designer-button tool-button"
                    >
                        Text
                    </button>

                    <button
                        type="button"
                        wire:click="addQr"
                        class="designer-button tool-button"
                    >
                        QR Code
                    </button>

                    <button
                        type="button"
                        wire:click="addImage"
                        class="designer-button tool-button"
                    >
                        Image
                    </button>

                    <button
                        type="button"
                        wire:click="addLogo"
                        class="designer-button tool-button"
                    >
                        Logo
                    </button>

                    <button
                        type="button"
                        wire:click="addSponsorLogo"
                        class="designer-button tool-button"
                    >
                        Sponsor Logo
                    </button>

                    <button
                        type="button"
                        wire:click="addShape"
                        class="designer-button tool-button"
                    >
                        Shape
                    </button>

                    <button
                        type="button"
                        wire:click="addLine"
                        class="designer-button tool-button"
                    >
                        Line
                    </button>
                </div>
            </aside>

            <main class="canvas-area">
                <div class="canvas-scroll">
                    <div
                        data-testid="ticket-designer-canvas"
                        class="canvas-frame"
                        style="
                            width: {{ $displayWidth }}px;
                            height: {{ $displayHeight }}px;
                        "
                    >
                        @if ($backgroundUrl)
                            <img
                                data-testid="ticket-designer-background-image"
                                src="{{ $backgroundUrl }}"
                                alt="Ticket template background"
                                class="designer-background"
                            >
                        @endif

                        @foreach ($elements as $element)
                            @php
                                $elementId =
                                    $element['id'] ?? '';

                                $elementType =
                                    $element['type'] ?? 'unknown';

                                $x =
                                    (float) ($element['x'] ?? 0);

                                $y =
                                    (float) ($element['y'] ?? 0);

                                $width =
                                    (float) ($element['width'] ?? 100);

                                $height =
                                    (float) ($element['height'] ?? 100);

                                $rotation =
                                    (float) ($element['rotation'] ?? 0);

                                $style =
                                    is_array($element['style'] ?? null)
                                        ? $element['style']
                                        : [];

                                $fontFamily =
                                    $style['fontFamily']
                                        ?? 'Arial';

                                $fontSize =
                                    (float) ($style['fontSize'] ?? 24);

                                $fontWeight =
                                    $style['fontWeight']
                                        ?? 400;

                                $textAlign =
                                    $style['textAlign']
                                        ?? 'center';

                                $color =
                                    $style['color']
                                        ?? '#161943';

                                $isSelected =
                                    $selectedElementId === $elementId;

                                $assetPath =
                                    $element['asset_path'] ?? null;

                                $assetUrl =
                                    filled($assetPath)
                                        ? \Illuminate\Support\Facades\Storage::disk('public')
                                            ->url($assetPath)
                                        : null;

                                $isImageElement =
                                    in_array(
                                        $elementType,
                                        ['image', 'logo', 'sponsor_logo'],
                                        true
                                    );
                            @endphp

                            <div
                                wire:key="designer-element-{{ $elementId }}"
                                x-data="ticketDesignerElement({
                                    id: @js($elementId),
                                    type: @js($elementType),
                                    x: @js($x),
                                    y: @js($y),
                                    width: @js($width),
                                    height: @js($height),
                                    rotation: @js($rotation),
                                    scale: @js($scale),
                                    canvasWidth: @js($canvasWidth),
                                    canvasHeight: @js($canvasHeight)
                                })"
                                x-bind:style="elementStyle"
                                x-bind:class="{
                                    dragging: dragging,
                                    resizing: resizing
                                }"
                                x-on:pointerdown="startDrag($event)"
                                data-element-id="{{ $elementId }}"
                                data-element-type="{{ $elementType }}"
                                class="
                                    designer-element
                                    {{ $elementType }}
                                    {{ $isSelected ? 'selected' : '' }}
                                    {{ (
                                        ($isImageElement && ! $assetUrl)
                                        || in_array(
                                            $elementType,
                                            ['shape', 'line'],
                                            true
                                        )
                                    ) ? 'placeholder' : '' }}
                                "
                                style="
                                    left: {{ $x * $scale }}px;
                                    top: {{ $y * $scale }}px;
                                    width: {{ max(1, $width * $scale) }}px;
                                    height: {{ max(1, $height * $scale) }}px;
                                    transform: rotate({{ $rotation }}deg);
                                    font-family: {{ $fontFamily }};
                                    font-size: {{ max(8, $fontSize * $scale) }}px;
                                    font-weight: {{ $fontWeight }};
                                    text-align: {{ $textAlign }};
                                    color: {{ $color }};
                                "
                            >
                                @switch($elementType)
                                    @case('text')
                                        <span>
                                            {{ $element['binding'] ?? 'holder_name' }}
                                        </span>
                                        @break

                                    @case('qr')
                                        {!! $ticketDesignerQrPreview !!}

                                        <span class="sr-only">
                                            Ticket QR preview
                                        </span>
                                        @break

                                    @case('image')
                                        @if ($assetUrl)
                                            <img
                                                src="{{ $assetUrl }}"
                                                alt="Ticket image"
                                                class="element-asset"
                                            >
                                        @else
                                            <span>Image</span>
                                        @endif
                                        @break

                                    @case('logo')
                                        @if ($assetUrl)
                                            <img
                                                src="{{ $assetUrl }}"
                                                alt="Event logo"
                                                class="element-asset"
                                            >
                                        @else
                                            <span>Logo</span>
                                        @endif
                                        @break

                                    @case('sponsor_logo')
                                        @if ($assetUrl)
                                            <img
                                                src="{{ $assetUrl }}"
                                                alt="Sponsor logo"
                                                class="element-asset"
                                            >
                                        @else
                                            <span>Sponsor Logo</span>
                                        @endif
                                        @break

                                    @case('shape')
                                        <span>Shape</span>
                                        @break

                                    @case('line')
                                        <span>Line</span>
                                        @break

                                    @default
                                        <span>{{ $elementType }}</span>
                                @endswitch

                                @if ($isSelected)
                                    <button
                                        type="button"
                                        class="element-resize-handle"
                                        aria-label="Resize {{ $elementType }} element"
                                        title="Drag to resize"
                                        x-on:pointerdown="startResize($event)"
                                    ></button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div
                    data-testid="ticket-designer-pages"
                    class="pages-bar"
                >
                    @foreach ($pages as $page)
                        <button
                            type="button"
                            wire:key="designer-page-{{ $page['id'] }}"
                            wire:click="switchPage({{ $page['id'] }})"
                            class="
                                page-tab
                                {{ $activePageId === (int) $page['id']
                                    ? 'active'
                                    : '' }}
                            "
                        >
                            {{ $page['name'] }}
                        </button>
                    @endforeach

                    <button
                        type="button"
                        wire:click="addPage"
                        class="page-tab"
                    >
                        + Add Page
                    </button>
                </div>
            </main>

            <aside
                data-testid="ticket-designer-properties"
                class="designer-panel properties-panel"
            >
                <h3 class="panel-heading">
                    Properties
                </h3>

                @if ($selectedElement)
                    <div
                        data-testid="selected-element-properties"
                    >
                        <div class="property-section">
                            <h4 class="property-section-title">
                                Position
                            </h4>

                            <div class="property-grid">
                                <div class="property-field">
                                    <label>X</label>

                                    <input
                                        type="number"
                                        value="{{ $selectedElement['x'] ?? 0 }}"
                                        wire:change="updateSelectedElement({
                                            x: $event.target.value
                                        })"
                                    >
                                </div>

                                <div class="property-field">
                                    <label>Y</label>

                                    <input
                                        type="number"
                                        value="{{ $selectedElement['y'] ?? 0 }}"
                                        wire:change="updateSelectedElement({
                                            y: $event.target.value
                                        })"
                                    >
                                </div>
                            </div>
                        </div>

                        @if ($selectedElementSupportsImage)
                            <div class="property-section">
                                <h4 class="property-section-title">
                                    Element Image
                                </h4>

                                <div class="element-image-controls">
                                    @if ($selectedElementUploadPreviewUrl)
                                        <img
                                            src="{{ $selectedElementUploadPreviewUrl }}"
                                            alt="Selected element image preview"
                                            class="background-preview"
                                        >

                                        <div class="background-selected-file">
                                            {{ $selectedElementUploadName }}
                                        </div>
                                    @elseif ($selectedElementAssetUrl)
                                        <img
                                            src="{{ $selectedElementAssetUrl }}"
                                            alt="Current element image"
                                            class="background-preview"
                                        >
                                    @else
                                        <div class="background-empty">
                                            No image uploaded for this element.
                                        </div>
                                    @endif

                                    <input
                                        type="file"
                                        wire:model="elementImageUpload"
                                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                        class="background-upload"
                                    >

                                    @error('elementImageUpload')
                                        <div class="error-box">
                                            {{ $message }}
                                        </div>
                                    @enderror

                                    <button
                                        type="button"
                                        wire:click="uploadSelectedElementImage"
                                        wire:loading.attr="disabled"
                                        wire:target="elementImageUpload,uploadSelectedElementImage"
                                        @disabled(! $elementImageUploadReady)
                                        class="designer-button primary"
                                    >
                                        <span
                                            wire:loading.remove
                                            wire:target="uploadSelectedElementImage"
                                        >
                                            {{ $selectedElementAssetUrl
                                                ? 'Replace Image'
                                                : 'Upload Image' }}
                                        </span>

                                        <span
                                            wire:loading
                                            wire:target="uploadSelectedElementImage"
                                        >
                                            Uploading...
                                        </span>
                                    </button>

                                    @if ($selectedElementAssetUrl)
                                        <button
                                            type="button"
                                            wire:click="removeSelectedElementImage"
                                            wire:loading.attr="disabled"
                                            wire:target="removeSelectedElementImage"
                                            class="designer-button danger"
                                        >
                                            Remove Image
                                        </button>
                                    @endif

                                    <div
                                        wire:loading
                                        wire:target="elementImageUpload"
                                        class="upload-progress"
                                    >
                                        Preparing image preview...
                                    </div>

                                    <p class="background-help">
                                        JPG, PNG or WEBP. Maximum 10 MB and
                                        4000 Ã— 4000 px. Transparent PNG is
                                        recommended for logos.
                                    </p>
                                </div>
                            </div>
                        @endif

                        <div class="property-section">
                            <h4 class="property-section-title">
                                Manual Size
                            </h4>

                            <div class="property-grid">
                                <div class="property-field">
                                    <label>
                                        {{ ($selectedElement['type'] ?? null) === 'qr'
                                            ? 'QR Size'
                                            : 'Width' }}
                                    </label>

                                    <input
                                        type="number"
                                        min="1"
                                        step="1"
                                        wire:model="manualWidth"
                                        wire:keydown.enter="applyManualSize"
                                    >
                                </div>

                                <div class="property-field">
                                    <label>Height</label>

                                    <input
                                        type="number"
                                        min="1"
                                        step="1"
                                        wire:model="manualHeight"
                                        wire:keydown.enter="applyManualSize"
                                        @disabled(
                                            ($selectedElement['type'] ?? null)
                                                === 'qr'
                                        )
                                    >
                                </div>

                                @error('manualWidth')
                                    <div class="property-field full">
                                        <div class="error-box">
                                            {{ $message }}
                                        </div>
                                    </div>
                                @enderror

                                @error('manualHeight')
                                    <div class="property-field full">
                                        <div class="error-box">
                                            {{ $message }}
                                        </div>
                                    </div>
                                @enderror

                                <div class="property-field full">
                                    <button
                                        type="button"
                                        wire:click="applyManualSize"
                                        wire:loading.attr="disabled"
                                        wire:target="applyManualSize"
                                        class="designer-button primary"
                                    >
                                        <span
                                            wire:loading.remove
                                            wire:target="applyManualSize"
                                        >
                                            Apply Size
                                        </span>

                                        <span
                                            wire:loading
                                            wire:target="applyManualSize"
                                        >
                                            Applying...
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <p class="background-help">
                                Use the arrow keys to move this element by
                                1 px. Hold Shift while pressing an arrow key
                                to move it by 10 px.
                            </p>
                        </div>

                        <div class="property-section">
                            <h4 class="property-section-title">
                                Rotation
                            </h4>

                            <div class="property-field">
                                <label>Degrees</label>

                                <input
                                    type="number"
                                    value="{{ $selectedElement['rotation'] ?? 0 }}"
                                    wire:change="updateSelectedElement({
                                        rotation: $event.target.value
                                    })"
                                >
                            </div>
                        </div>

                        @if (
                            ($selectedElement['type'] ?? null)
                                === 'text'
                        )
                            <div class="property-section">
                                <h4 class="property-section-title">
                                    Binding
                                </h4>

                                <div class="property-field">
                                    <label>Dynamic value</label>

                                    <select
                                        wire:change="updateSelectedElement({
                                            binding: $event.target.value
                                        })"
                                    >
                                        @foreach ([
                                            'holder_name' => 'Holder Name',
                                            'ticket_number' => 'Ticket Number',
                                            'ticket_type' => 'Ticket Type',
                                            'event_name' => 'Event Name',
                                            'event_date' => 'Event Date',
                                            'event_time' => 'Event Time',
                                            'venue' => 'Venue',
                                            'order_number' => 'Order Number',
                                        ] as $value => $label)
                                            <option
                                                value="{{ $value }}"
                                                @selected(
                                                    ($selectedElement['binding'] ?? null)
                                                        === $value
                                                )
                                            >
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif

                        <div class="property-section">
                            <h4 class="property-section-title">
                                Layer
                            </h4>

                            <div class="layer-actions">
                                <button
                                    type="button"
                                    wire:click="moveLayerBackward"
                                    class="designer-button"
                                >
                                    Backward
                                </button>

                                <button
                                    type="button"
                                    wire:click="moveLayerForward"
                                    class="designer-button"
                                >
                                    Forward
                                </button>
                            </div>
                        </div>

                        <div class="property-section">
                            <button
                                type="button"
                                wire:click="deleteSelectedElement"
                                class="designer-button danger"
                                style="width: 100%;"
                            >
                                Delete Element
                            </button>
                        </div>
                    </div>
                @else
                    <div class="empty-properties">
                        Select an element on the canvas to edit its properties.
                    </div>
                @endif
            </aside>
        </div>
    </div>
</div>