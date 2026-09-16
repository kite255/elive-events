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

        .elive-ticket-designer .designer-button:hover {
            background: #f8fafc;
        }

        .elive-ticket-designer .designer-button.primary {
            border-color: var(--elive-blue);
            background: var(--elive-blue);
            color: #fff;
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
            background: #fff;
            border: 1px solid #cbd5e1;
            box-shadow: 0 14px 36px rgba(15, 23, 42, .10);
            transform-origin: top left;
        }

        .elive-ticket-designer .designer-element {
            position: absolute;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            cursor: pointer;
            border: 1px dashed transparent;
        }

        .elive-ticket-designer .designer-element.selected {
            border-color: var(--elive-blue);
            box-shadow: 0 0 0 2px rgba(0, 122, 178, .10);
        }

        .elive-ticket-designer .designer-element.text {
            padding: .35rem;
        }

        .elive-ticket-designer .designer-element.qr {
            background:
                linear-gradient(45deg, #111 25%, transparent 25%) 0 0/14px 14px,
                linear-gradient(-45deg, #111 25%, transparent 25%) 0 7px/14px 14px,
                linear-gradient(45deg, transparent 75%, #111 75%) 7px -7px/14px 14px,
                linear-gradient(-45deg, transparent 75%, #111 75%) -7px 0/14px 14px;
            background-color: #fff;
        }

        .elive-ticket-designer .designer-element.placeholder {
            border: 1px dashed #94a3b8;
            background: rgba(248, 250, 252, .92);
            color: #475569;
            font-size: .72rem;
            text-align: center;
            padding: .4rem;
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
    @endphp

    <div class="elive-ticket-designer">
        <div
            data-testid="ticket-designer-toolbar"
            class="designer-toolbar"
        >
            <div class="designer-title">
                <h2>{{ $templateName }}</h2>

                <p>
                    {{ $canvasWidth }} × {{ $canvasHeight }} px
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
                            @endphp

                            <div
                                wire:key="designer-element-{{ $elementId }}"
                                wire:click="selectElement('{{ $elementId }}')"
                                data-element-id="{{ $elementId }}"
                                data-element-type="{{ $elementType }}"
                                class="
                                    designer-element
                                    {{ $elementType }}
                                    {{ $isSelected ? 'selected' : '' }}
                                    {{ in_array(
                                        $elementType,
                                        [
                                            'image',
                                            'logo',
                                            'sponsor_logo',
                                            'shape',
                                            'line',
                                        ],
                                        true
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
                                        <span class="sr-only">
                                            Ticket QR
                                        </span>
                                        @break

                                    @case('image')
                                        <span>Image</span>
                                        @break

                                    @case('logo')
                                        <span>Logo</span>
                                        @break

                                    @case('sponsor_logo')
                                        <span>Sponsor Logo</span>
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
                                            x: Number($event.target.value)
                                        })"
                                    >
                                </div>

                                <div class="property-field">
                                    <label>Y</label>

                                    <input
                                        type="number"
                                        value="{{ $selectedElement['y'] ?? 0 }}"
                                        wire:change="updateSelectedElement({
                                            y: Number($event.target.value)
                                        })"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="property-section">
                            <h4 class="property-section-title">
                                Size
                            </h4>

                            <div class="property-grid">
                                <div class="property-field">
                                    <label>Width</label>

                                    <input
                                        type="number"
                                        min="1"
                                        value="{{ $selectedElement['width'] ?? 1 }}"
                                        wire:change="updateSelectedElement({
                                            width: Number($event.target.value)
                                        })"
                                    >
                                </div>

                                <div class="property-field">
                                    <label>Height</label>

                                    <input
                                        type="number"
                                        min="1"
                                        value="{{ $selectedElement['height'] ?? 1 }}"
                                        wire:change="updateSelectedElement({
                                            height: Number($event.target.value)
                                        })"
                                    >
                                </div>
                            </div>
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
                                        rotation: Number($event.target.value)
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