<x-filament-panels::page>
    @php
        $preview = $this->previewConfig ?? [];

        $width = (int) ($preview['width'] ?? 420);
        $height = (int) ($preview['height'] ?? 620);

        $enabledElements = $preview['enabled_elements'] ?? [
            'name',
            'category',
            'organization',
            'position',
            'badge_number',
            'qr_code',
        ];

        if (! is_array($enabledElements)) {
            $enabledElements = [];
        }

        $showName = in_array('name', $enabledElements, true);
        $showCategory = in_array('category', $enabledElements, true);
        $showOrganization = in_array('organization', $enabledElements, true);
        $showPosition = in_array('position', $enabledElements, true);
        $showBadgeNumber = in_array('badge_number', $enabledElements, true);
        $showQrCode = in_array('qr_code', $enabledElements, true);

        $backgroundPath = $preview['background_image_path'] ?? null;

        if (is_array($backgroundPath)) {
            $backgroundPath = collect($backgroundPath)->filter()->first();
        }

        $backgroundUrl = filled($backgroundPath)
            ? asset('storage/' . $backgroundPath)
            : null;

        $name = $preview['name'] ?? [];
        $category = $preview['category'] ?? [];
        $organization = $preview['organization'] ?? [];
        $position = $preview['position'] ?? [];
        $badgeNumber = $preview['badge_number'] ?? [];
        $qrCode = $preview['qr_code'] ?? [];

        $safeTextWidth = max($width - 40, 120);

        $nameX = (int) ($name['x'] ?? 210);
        $nameY = (int) ($name['y'] ?? 250);

        $categoryX = (int) ($category['x'] ?? 210);
        $categoryY = (int) ($category['y'] ?? 315);

        $organizationX = (int) ($organization['x'] ?? 210);
        $organizationY = (int) ($organization['y'] ?? 360);

        $positionX = (int) ($position['x'] ?? 210);
        $positionY = (int) ($position['y'] ?? 385);

        $badgeNumberX = (int) ($badgeNumber['x'] ?? 210);
        $badgeNumberY = (int) ($badgeNumber['y'] ?? 420);

        $qrX = (int) ($qrCode['x'] ?? 150);
        $qrY = (int) ($qrCode['y'] ?? 465);
        $qrSize = (int) ($qrCode['size'] ?? 120);

        $nameFontSize = (int) ($name['font_size'] ?? 30);
        $categoryFontSize = (int) ($category['font_size'] ?? 18);
        $organizationFontSize = (int) ($organization['font_size'] ?? 14);
        $positionFontSize = (int) ($position['font_size'] ?? 13);
        $badgeNumberFontSize = (int) ($badgeNumber['font_size'] ?? 13);

        $nameColor = $name['color'] ?? '#FFFFFF';
        $categoryColor = $category['color'] ?? '#FFFFFF';
        $categoryBackground = $category['background'] ?? '#F99A12';
        $organizationColor = $organization['color'] ?? '#DBEAFE';
        $positionColor = $position['color'] ?? '#E0F2FE';
        $badgeNumberColor = $badgeNumber['color'] ?? '#FFFFFF';
    @endphp

    <div
        x-data="badgeDesigner({
            width: {{ $width }},
            height: {{ $height }},
            elements: {
                name: { x: {{ $nameX }}, y: {{ $nameY }}, xField: 'name_x', yField: 'name_y', mode: 'center', visible: {{ $showName ? 'true' : 'false' }} },
                category: { x: {{ $categoryX }}, y: {{ $categoryY }}, xField: 'category_x', yField: 'category_y', mode: 'center', visible: {{ $showCategory ? 'true' : 'false' }} },
                organization: { x: {{ $organizationX }}, y: {{ $organizationY }}, xField: 'organization_x', yField: 'organization_y', mode: 'center', visible: {{ $showOrganization ? 'true' : 'false' }} },
                position: { x: {{ $positionX }}, y: {{ $positionY }}, xField: 'position_x', yField: 'position_y', mode: 'center', visible: {{ $showPosition ? 'true' : 'false' }} },
                badge_number: { x: {{ $badgeNumberX }}, y: {{ $badgeNumberY }}, xField: 'badge_number_x', yField: 'badge_number_y', mode: 'center', visible: {{ $showBadgeNumber ? 'true' : 'false' }} },
                qr_code: { x: {{ $qrX }}, y: {{ $qrY }}, xField: 'qr_code_x', yField: 'qr_code_y', mode: 'top-left', visible: {{ $showQrCode ? 'true' : 'false' }} },
            }
        })"
        class="elive-designer-shell"
    >
        <div class="elive-designer-form-panel">
            {{ $this->form }}
        </div>

        <div class="elive-preview-column">
            <div class="elive-preview-card">
                <div class="elive-preview-header">
                    <div>
                        <div class="elive-preview-kicker">
                            Badge Designer
                        </div>

                        <h2 class="elive-preview-title">
                            Live Badge Preview
                        </h2>

                        <p class="elive-preview-description">
                            Select visible elements from the library, then drag them on the badge.
                        </p>
                    </div>

                    <div class="elive-preview-size">
                        {{ $width }} × {{ $height }} px
                    </div>
                </div>

                <div class="elive-selected-bar">
                    <div class="elive-selected-item" x-show="selectedKey" x-cloak>
                        <span class="elive-selected-dot"></span>
                        Selected:
                        <strong x-text="selectedLabel()"></strong>
                    </div>

                    <div class="elive-selected-item elive-selected-muted" x-show="! selectedKey" x-cloak>
                        <span class="elive-selected-dot-muted"></span>
                        No element selected
                    </div>
                </div>

                <div class="elive-preview-stage">
                    <div
                        x-ref="canvas"
                        @click.self="clearSelection()"
                        @mousemove.window="dragMove($event)"
                        @mouseup.window="dragEnd()"
                        @mouseleave.window="dragEnd()"
                        class="elive-badge-canvas"
                        style="
                            width: {{ $width }}px;
                            height: {{ $height }}px;
                            min-width: {{ $width }}px;
                            max-width: {{ $width }}px;
                            min-height: {{ $height }}px;
                            max-height: {{ $height }}px;
                        "
                    >
                        @if ($backgroundUrl)
                            <img
                                src="{{ $backgroundUrl }}"
                                alt="Badge Background"
                                draggable="false"
                                class="elive-badge-background"
                            >
                        @else
                            <div class="elive-badge-background-fallback">
                                <div class="elive-fallback-top"></div>
                                <div class="elive-fallback-side"></div>
                                <div class="elive-fallback-bottom"></div>
                                <div class="elive-fallback-accent"></div>
                                <div class="elive-fallback-logo">eLive Events</div>
                            </div>
                        @endif

                        @if ($showName)
                            <div
                                @click.stop="selectElement('name')"
                                @mousedown.prevent="dragStart($event, 'name')"
                                :style="elementStyle('name')"
                                :class="elementClass('name')"
                                data-element-label="Attendee Name"
                                class="elive-draggable-badge-element"
                                style="
                                    width: {{ $safeTextWidth }}px;
                                    max-width: {{ $safeTextWidth }}px;
                                    overflow: hidden;
                                    white-space: nowrap;
                                    text-overflow: ellipsis;
                                    text-align: center;
                                    font-size: {{ $nameFontSize }}px;
                                    font-weight: 800;
                                    line-height: 1.1;
                                    color: {{ $nameColor }};
                                    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.45);
                                "
                            >
                                Kitenken Lucas
                            </div>
                        @endif

                        @if ($showCategory)
                            <div
                                @click.stop="selectElement('category')"
                                @mousedown.prevent="dragStart($event, 'category')"
                                :style="elementStyle('category')"
                                :class="elementClass('category')"
                                data-element-label="Category"
                                class="elive-draggable-badge-element"
                                style="
                                    max-width: {{ $safeTextWidth }}px;
                                    overflow: hidden;
                                    white-space: nowrap;
                                    text-overflow: ellipsis;
                                    text-align: center;
                                    padding: 8px 20px;
                                    border-radius: 999px;
                                    font-size: {{ $categoryFontSize }}px;
                                    font-weight: 800;
                                    line-height: 1;
                                    text-transform: uppercase;
                                    color: {{ $categoryColor }};
                                    background: {{ $categoryBackground }};
                                    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.22);
                                "
                            >
                                VIP Delegate
                            </div>
                        @endif

                        @if ($showOrganization)
                            <div
                                @click.stop="selectElement('organization')"
                                @mousedown.prevent="dragStart($event, 'organization')"
                                :style="elementStyle('organization')"
                                :class="elementClass('organization')"
                                data-element-label="Organization"
                                class="elive-draggable-badge-element"
                                style="
                                    width: {{ $safeTextWidth }}px;
                                    max-width: {{ $safeTextWidth }}px;
                                    overflow: hidden;
                                    white-space: nowrap;
                                    text-overflow: ellipsis;
                                    text-align: center;
                                    font-size: {{ $organizationFontSize }}px;
                                    font-weight: 600;
                                    line-height: 1.2;
                                    color: {{ $organizationColor }};
                                    text-shadow: 0 1px 5px rgba(0, 0, 0, 0.4);
                                "
                            >
                                Elcone Technical Solutions
                            </div>
                        @endif

                        @if ($showPosition)
                            <div
                                @click.stop="selectElement('position')"
                                @mousedown.prevent="dragStart($event, 'position')"
                                :style="elementStyle('position')"
                                :class="elementClass('position')"
                                data-element-label="Position"
                                class="elive-draggable-badge-element"
                                style="
                                    width: {{ $safeTextWidth }}px;
                                    max-width: {{ $safeTextWidth }}px;
                                    overflow: hidden;
                                    white-space: nowrap;
                                    text-overflow: ellipsis;
                                    text-align: center;
                                    font-size: {{ $positionFontSize }}px;
                                    font-weight: 500;
                                    line-height: 1.2;
                                    color: {{ $positionColor }};
                                    text-shadow: 0 1px 5px rgba(0, 0, 0, 0.4);
                                "
                            >
                                ICT Officer
                            </div>
                        @endif

                        @if ($showBadgeNumber)
                            <div
                                @click.stop="selectElement('badge_number')"
                                @mousedown.prevent="dragStart($event, 'badge_number')"
                                :style="elementStyle('badge_number')"
                                :class="elementClass('badge_number')"
                                data-element-label="Badge Number"
                                class="elive-draggable-badge-element"
                                style="
                                    width: {{ $safeTextWidth }}px;
                                    max-width: {{ $safeTextWidth }}px;
                                    overflow: hidden;
                                    text-align: center;
                                    font-size: {{ $badgeNumberFontSize }}px;
                                    font-weight: 800;
                                    line-height: 1.2;
                                    color: {{ $badgeNumberColor }};
                                    text-shadow: 0 1px 5px rgba(0, 0, 0, 0.45);
                                "
                            >
                                <div style="font-size: 11px; font-weight: 600; line-height: 1.1; opacity: 0.9;">
                                    Badge No.
                                </div>

                                <div style="overflow: hidden; white-space: nowrap; text-overflow: ellipsis;">
                                    ELV-LC26-0001
                                </div>
                            </div>
                        @endif

                        @if ($showQrCode)
                            <div
                                @click.stop="selectElement('qr_code')"
                                @mousedown.prevent="dragStart($event, 'qr_code')"
                                :style="elementStyle('qr_code')"
                                :class="elementClass('qr_code')"
                                data-element-label="QR Code"
                                class="elive-draggable-badge-element"
                                style="
                                    width: {{ $qrSize }}px;
                                    height: {{ $qrSize }}px;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    padding: 8px;
                                    background: #ffffff;
                                    border: 1px solid #e5e7eb;
                                    border-radius: 12px;
                                    box-shadow: 0 10px 22px rgba(0, 0, 0, 0.28);
                                "
                            >
                                <div
                                    style="
                                        display: grid;
                                        grid-template-columns: repeat(5, 1fr);
                                        grid-template-rows: repeat(5, 1fr);
                                        gap: 4px;
                                        width: 100%;
                                        height: 100%;
                                    "
                                >
                                    @for ($i = 0; $i < 25; $i++)
                                        <div
                                            style="
                                                background: {{ in_array($i, [0,1,2,5,7,10,11,12,18,19,20,22,24]) ? '#000000' : '#ffffff' }};
                                            "
                                        ></div>
                                    @endfor
                                </div>
                            </div>
                        @endif

                        @unless ($showName || $showCategory || $showOrganization || $showPosition || $showBadgeNumber || $showQrCode)
                            <div class="elive-empty-canvas-message">
                                <div class="elive-empty-title">No elements added</div>
                                <div class="elive-empty-text">
                                    Use the Element Library on the left or click Add Default Elements.
                                </div>
                            </div>
                        @endunless
                    </div>
                </div>

                <div class="elive-preview-help">
                    <div class="elive-help-icon">i</div>

                    <div>
                        <strong>How to design:</strong>
                        Enable elements from the Element Library, select an element, drag it into position,
                        adjust style on the left, then click <strong>Save Design</strong>.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        [x-cloak] {
            display: none !important;
        }

        .elive-designer-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(460px, 560px);
            gap: 24px;
            align-items: start;
        }

        .elive-designer-form-panel {
            min-width: 0;
        }

        .elive-preview-column {
            min-width: 0;
            position: sticky;
            top: 24px;
        }

        .elive-preview-card {
            position: relative;
            overflow: hidden;
            border-radius: 24px;
            padding: 18px;
            background:
                radial-gradient(circle at top left, rgba(59, 130, 246, 0.16), transparent 34%),
                radial-gradient(circle at top right, rgba(249, 154, 18, 0.16), transparent 34%),
                #ffffff;
            border: 1px solid rgba(148, 163, 184, 0.28);
            box-shadow: 0 22px 50px rgba(15, 23, 42, 0.10);
        }

        .elive-preview-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(15, 23, 42, 0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, 0.035) 1px, transparent 1px);
            background-size: 22px 22px;
            mask-image: linear-gradient(to bottom, black, transparent 70%);
            pointer-events: none;
        }

        .elive-preview-header,
        .elive-selected-bar,
        .elive-preview-stage,
        .elive-preview-help {
            position: relative;
            z-index: 2;
        }

        .elive-preview-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 14px;
        }

        .elive-preview-kicker {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            margin-bottom: 6px;
            padding: 4px 9px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .elive-preview-title {
            margin: 0;
            font-size: 20px;
            line-height: 28px;
            font-weight: 800;
            color: #0f172a;
        }

        .elive-preview-description {
            margin: 3px 0 0;
            font-size: 13px;
            color: #64748b;
        }

        .elive-preview-size {
            flex-shrink: 0;
            padding: 7px 10px;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
        }

        .elive-selected-bar {
            min-height: 34px;
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }

        .elive-selected-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 11px;
            border-radius: 999px;
            background: #fff7ed;
            color: #9a3412;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid #fed7aa;
        }

        .elive-selected-muted {
            background: #f8fafc;
            color: #64748b;
            border-color: #e2e8f0;
        }

        .elive-selected-dot,
        .elive-selected-dot-muted {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #f99a12;
        }

        .elive-selected-dot-muted {
            background: #94a3b8;
        }

        .elive-preview-stage {
            width: 100%;
            overflow: auto;
            border-radius: 22px;
            padding: 26px;
            background:
                linear-gradient(135deg, #f8fafc, #eef2ff),
                #f3f4f6;
            border: 1px solid #e2e8f0;
        }

        .elive-badge-canvas {
            position: relative;
            margin-left: auto;
            margin-right: auto;
            overflow: hidden;
            background: #f8fafc;
            border-radius: 22px;
            box-shadow:
                0 28px 45px rgba(15, 23, 42, 0.20),
                0 0 0 1px rgba(15, 23, 42, 0.08);
            user-select: none;
        }

        .elive-badge-background {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
            pointer-events: none;
        }

        .elive-badge-background-fallback {
            position: absolute;
            inset: 0;
            overflow: hidden;
            z-index: 1;
            pointer-events: none;
        }

        .elive-fallback-top {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 128px;
            background: #0b1f3a;
        }

        .elive-fallback-side {
            position: absolute;
            right: 0;
            top: 0;
            width: 80px;
            height: 100%;
            background: #161943;
        }

        .elive-fallback-bottom {
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 64px;
            background: #0b1f3a;
        }

        .elive-fallback-accent {
            position: absolute;
            left: 0;
            top: 128px;
            width: 100%;
            height: 8px;
            background: #f99a12;
        }

        .elive-fallback-logo {
            position: absolute;
            left: 24px;
            top: 32px;
            color: #ffffff;
            font-size: 18px;
            font-weight: 700;
        }

        .elive-draggable-badge-element {
            position: absolute;
            z-index: 20;
            cursor: grab;
            user-select: none;
            touch-action: none;
            outline: 2px dashed transparent;
            outline-offset: 5px;
            transition: outline-color 120ms ease, box-shadow 120ms ease, filter 120ms ease;
        }

        .elive-draggable-badge-element:hover {
            outline-color: rgba(249, 154, 18, 0.75);
            box-shadow: 0 0 0 4px rgba(249, 154, 18, 0.16);
        }

        .elive-draggable-badge-element.is-selected {
            outline-color: #f99a12;
            box-shadow: 0 0 0 5px rgba(249, 154, 18, 0.28);
            filter: brightness(1.08);
        }

        .elive-draggable-badge-element.is-dragging {
            cursor: grabbing;
            outline-color: #2563eb;
            box-shadow: 0 0 0 5px rgba(37, 99, 235, 0.28);
            filter: brightness(1.12);
        }

        .elive-draggable-badge-element.is-selected::before,
        .elive-draggable-badge-element.is-dragging::before {
            content: attr(data-element-label);
            position: absolute;
            left: 50%;
            top: -36px;
            transform: translateX(-50%);
            z-index: 999;
            padding: 6px 10px;
            border-radius: 999px;
            background: #f99a12;
            color: #ffffff;
            font-size: 11px;
            line-height: 1;
            font-weight: 800;
            white-space: nowrap;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.25);
            pointer-events: none;
        }

        .elive-draggable-badge-element.is-dragging::before {
            background: #2563eb;
        }

        .elive-empty-canvas-message {
            position: absolute;
            left: 50%;
            top: 50%;
            z-index: 30;
            width: calc(100% - 48px);
            max-width: 320px;
            transform: translate(-50%, -50%);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            background: rgba(255, 255, 255, 0.92);
            border: 1px dashed #cbd5e1;
            box-shadow: 0 18px 35px rgba(15, 23, 42, 0.16);
        }

        .elive-empty-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }

        .elive-empty-text {
            margin-top: 6px;
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
        }

        .elive-preview-help {
            display: flex;
            gap: 10px;
            margin-top: 14px;
            padding: 12px;
            border-radius: 16px;
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 13px;
            line-height: 1.5;
            border: 1px solid #bfdbfe;
        }

        .elive-help-icon {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 999px;
            background: #2563eb;
            color: white;
            font-size: 12px;
            font-weight: 800;
        }

        @media (max-width: 1279px) {
            .elive-designer-shell {
                grid-template-columns: 1fr !important;
            }

            .elive-preview-column {
                position: static !important;
            }
        }
    </style>

    <script>
        function badgeDesigner(config) {
            return {
                width: config.width,
                height: config.height,
                elements: config.elements,
                activeKey: null,
                selectedKey: null,
                offsetX: 0,
                offsetY: 0,

                selectElement(key) {
                    if (! this.elements[key] || this.elements[key].visible === false) {
                        return;
                    }

                    this.selectedKey = key;
                },

                clearSelection() {
                    if (this.activeKey) {
                        return;
                    }

                    this.selectedKey = null;
                },

                selectedLabel() {
                    const labels = {
                        name: 'Attendee Name',
                        category: 'Category',
                        organization: 'Organization',
                        position: 'Position',
                        badge_number: 'Badge Number',
                        qr_code: 'QR Code',
                    };

                    return labels[this.selectedKey] || '';
                },

                elementClass(key) {
                    return {
                        'is-selected': this.selectedKey === key,
                        'is-dragging': this.activeKey === key,
                    };
                },

                elementStyle(key) {
                    const element = this.elements[key];

                    let transform = 'none';

                    if (element.mode === 'center') {
                        transform = 'translate(-50%, -50%)';
                    }

                    return `
                        left: ${element.x}px;
                        top: ${element.y}px;
                        transform: ${transform};
                    `;
                },

                dragStart(event, key) {
                    if (! this.elements[key] || this.elements[key].visible === false) {
                        return;
                    }

                    this.activeKey = key;
                    this.selectedKey = key;

                    const element = this.elements[key];
                    const rect = this.$refs.canvas.getBoundingClientRect();

                    const mouseX = event.clientX - rect.left;
                    const mouseY = event.clientY - rect.top;

                    this.offsetX = mouseX - element.x;
                    this.offsetY = mouseY - element.y;
                },

                dragMove(event) {
                    if (! this.activeKey) {
                        return;
                    }

                    const rect = this.$refs.canvas.getBoundingClientRect();
                    const element = this.elements[this.activeKey];

                    let newX = event.clientX - rect.left - this.offsetX;
                    let newY = event.clientY - rect.top - this.offsetY;

                    newX = Math.round(Math.max(0, Math.min(this.width, newX)));
                    newY = Math.round(Math.max(0, Math.min(this.height, newY)));

                    element.x = newX;
                    element.y = newY;

                    this.syncField(element.xField, newX);
                    this.syncField(element.yField, newY);
                },

                dragEnd() {
                    if (! this.activeKey) {
                        return;
                    }

                    const element = this.elements[this.activeKey];

                    this.syncField(element.xField, element.x);
                    this.syncField(element.yField, element.y);

                    this.activeKey = null;
                },

                syncField(field, value) {
                    const path = `data.${field}`;

                    if (this.$wire) {
                        this.$wire.set(path, value, false);
                    }

                    const input = document.querySelector(`[wire\\:model="data.${field}"], [wire\\:model\\.live="data.${field}"], [wire\\:model\\.blur="data.${field}"], input[name="${field}"]`);

                    if (input) {
                        input.value = value;
                        input.dispatchEvent(new Event('input', { bubbles: true }));
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                },
            }
        }
    </script>
</x-filament-panels::page>