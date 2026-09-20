<x-filament-panels::page>
    @php
        $config = $template->getDesignConfigWithDefaults();

        $enabledElements = $config['enabled_elements'] ?? [
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

        $savedElements = $config['elements'] ?? [];

        if (! is_array($savedElements)) {
            $savedElements = [];
        }

        $elementLabels = [
            'name' => 'Attendee Name',
            'category' => 'Category',
            'organization' => 'Organization',
            'position' => 'Position / Title',
            'badge_number' => 'Badge Number',
            'qr_code' => 'QR Code',
        ];

        $visibleElementLabels = collect($enabledElements)
            ->map(fn ($key) => $elementLabels[$key] ?? null)
            ->filter()
            ->values();

        $backgroundUrl = $template->backgroundImageUrl();

        $templateSize = ((int) ($template->width ?? data_get($config, 'canvas.width', 420)))
            . 'px × '
            . ((int) ($template->height ?? data_get($config, 'canvas.height', 620)))
            . 'px';

        $eventName = $template->event?->name
            ?? $template->event?->title
            ?? 'Global Template';

        $categoryName = $template->category?->name ?? 'All Categories';
        $badgeTypeName = $template->badgeType?->name ?? 'All Badge Types';

        $statusLabel = $template->is_active ? 'Active Template' : 'Inactive Template';
        $statusClass = $template->is_active ? 'is-active' : 'is-inactive';
    @endphp

    <style>
        .elive-preview-wrapper {
            max-width: 1180px;
            margin: 0 auto;
            display: grid;
            gap: 24px;
        }

        .elive-preview-card {
            position: relative;
            overflow: hidden;
            border-radius: 28px;
            padding: 28px;
            background:
                radial-gradient(circle at top left, rgba(35, 63, 126, 0.13), transparent 35%),
                radial-gradient(circle at top right, rgba(249, 154, 18, 0.14), transparent 35%),
                #ffffff;
            border: 1px solid rgba(148, 163, 184, 0.28);
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.10);
        }

        .elive-preview-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(15, 23, 42, 0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, 0.035) 1px, transparent 1px);
            background-size: 24px 24px;
            mask-image: linear-gradient(to bottom, black, transparent 70%);
            pointer-events: none;
        }

        .elive-preview-content {
            position: relative;
            z-index: 2;
        }

        .elive-preview-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
            padding-bottom: 22px;
            border-bottom: 1px solid #e2e8f0;
        }

        .elive-kicker {
            display: inline-flex;
            width: fit-content;
            margin-bottom: 8px;
            padding: 5px 10px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .elive-title {
            font-size: 30px;
            line-height: 38px;
            font-weight: 900;
            color: #0f172a;
            margin: 0;
        }

        .elive-subtitle {
            margin-top: 6px;
            color: #64748b;
            font-size: 15px;
            font-weight: 600;
        }

        .elive-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 9px 14px;
            font-size: 13px;
            font-weight: 900;
            border: 1px solid transparent;
        }

        .elive-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
        }

        .elive-status.is-active {
            background: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }

        .elive-status.is-active .elive-status-dot {
            background: #16a34a;
        }

        .elive-status.is-inactive {
            background: #f1f5f9;
            color: #475569;
            border-color: #e2e8f0;
        }

        .elive-status.is-inactive .elive-status-dot {
            background: #94a3b8;
        }

        .elive-preview-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 380px;
            gap: 26px;
            align-items: start;
            margin-top: 26px;
        }

        .elive-section-title {
            margin: 0 0 14px;
            font-size: 15px;
            font-weight: 900;
            color: #0f172a;
        }

        .elive-info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .elive-info-box {
            background: rgba(248, 250, 252, 0.88);
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 16px;
        }

        .elive-label {
            font-size: 12px;
            font-weight: 900;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.035em;
        }

        .elive-value {
            margin-top: 7px;
            font-size: 16px;
            font-weight: 900;
            color: #0f172a;
            word-break: break-word;
        }

        .elive-muted-value {
            color: #64748b;
        }

        .elive-element-list {
            margin-top: 22px;
            padding: 18px;
            border-radius: 22px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .elive-element-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .elive-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 11px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: #0f172a;
            font-size: 12px;
            font-weight: 800;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .elive-chip-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #f99a12;
        }

        .elive-badge-preview-box {
            position: sticky;
            top: 24px;
            background:
                linear-gradient(135deg, #f8fafc, #eef2ff),
                #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 26px;
            padding: 20px;
            text-align: center;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75);
        }

        .elive-badge-preview-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .elive-badge-preview-title strong {
            font-size: 14px;
            color: #0f172a;
            font-weight: 900;
        }

        .elive-size-pill {
            padding: 5px 9px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 11px;
            font-weight: 900;
        }

        .elive-badge-preview-frame {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 420px;
            border-radius: 22px;
            padding: 18px;
            background:
                linear-gradient(45deg, rgba(148, 163, 184, 0.16) 25%, transparent 25%),
                linear-gradient(-45deg, rgba(148, 163, 184, 0.16) 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, rgba(148, 163, 184, 0.16) 75%),
                linear-gradient(-45deg, transparent 75%, rgba(148, 163, 184, 0.16) 75%);
            background-size: 22px 22px;
            background-position: 0 0, 0 11px, 11px -11px, -11px 0;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
        }

        .elive-badge-preview-frame img {
            width: 320px;
            max-width: 100%;
            height: auto;
            border-radius: 18px;
            background: #ffffff;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
        }

        .elive-actions {
            margin-top: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .elive-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            padding: 12px 18px;
            font-size: 14px;
            font-weight: 900;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
        }

        .elive-btn-primary {
            background: #161943;
            color: #ffffff;
        }

        .elive-btn-dark {
            background: #0B1F3A;
            color: #ffffff;
        }

        .elive-btn-light {
            background: #ffffff;
            color: #0f172a;
            border-color: #e2e8f0;
        }

        .elive-empty {
            padding: 24px;
            border-radius: 18px;
            border: 1px dashed #cbd5e1;
            color: #64748b;
            background: rgba(248, 250, 252, 0.9);
            text-align: center;
            font-weight: 800;
        }

        @media print {
            .fi-sidebar,
            .fi-topbar,
            .elive-actions,
            .elive-info-panel,
            .elive-preview-header,
            .elive-element-list {
                display: none !important;
            }

            .elive-preview-card {
                box-shadow: none;
                border: none;
                padding: 0;
            }

            .elive-preview-grid {
                display: block;
            }

            .elive-badge-preview-box {
                position: static;
                border: none;
                background: white;
                box-shadow: none;
            }

            .elive-badge-preview-frame {
                border: none;
                background: white;
                min-height: auto;
            }
        }

        @media (max-width: 980px) {
            .elive-preview-grid {
                grid-template-columns: 1fr;
            }

            .elive-badge-preview-box {
                position: static;
            }

            .elive-info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="elive-preview-wrapper">
        <div class="elive-preview-card">
            <div class="elive-preview-content">
                <div class="elive-preview-header">
                    <div>
                        <div class="elive-kicker">
                            Badge Template Preview
                        </div>

                        <h2 class="elive-title">
                            {{ $template->name }}
                        </h2>

                        <div class="elive-subtitle">
                            {{ $eventName }}
                        </div>
                    </div>

                    <div class="elive-status {{ $statusClass }}">
                        <span class="elive-status-dot"></span>
                        {{ $statusLabel }}
                    </div>
                </div>

                <div class="elive-preview-grid">
                    <div class="elive-info-panel">
                        <h3 class="elive-section-title">
                            Template Information
                        </h3>

                        <div class="elive-info-grid">
                            <div class="elive-info-box">
                                <div class="elive-label">Template Size</div>
                                <div class="elive-value">
                                    {{ $templateSize }}
                                </div>
                            </div>

                            <div class="elive-info-box">
                                <div class="elive-label">Default Template</div>
                                <div class="elive-value">
                                    {{ $template->is_default ? 'Yes' : 'No' }}
                                </div>
                            </div>

                            <div class="elive-info-box">
                                <div class="elive-label">Category</div>
                                <div class="elive-value">
                                    {{ $categoryName }}
                                </div>
                            </div>

                            <div class="elive-info-box">
                                <div class="elive-label">Badge Type</div>
                                <div class="elive-value">
                                    {{ $badgeTypeName }}
                                </div>
                            </div>

                            <div class="elive-info-box">
                                <div class="elive-label">Sample Attendee</div>
                                <div class="elive-value {{ $sampleAttendee ? '' : 'elive-muted-value' }}">
                                    {{ $sampleAttendee?->full_name ?? 'No attendee found' }}
                                </div>
                            </div>

                            <div class="elive-info-box">
                                <div class="elive-label">Background</div>
                                <div class="elive-value">
                                    {{ $backgroundUrl ? 'Uploaded Image' : 'System Background' }}
                                </div>
                            </div>
                        </div>

                        <div class="elive-element-list">
                            <h3 class="elive-section-title">
                                Visible Elements
                            </h3>

                            @if ($visibleElementLabels->isNotEmpty())
                                <div class="elive-element-chips">
                                    @foreach ($visibleElementLabels as $label)
                                        <div class="elive-chip">
                                            <span class="elive-chip-dot"></span>
                                            {{ $label }}
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="elive-empty">
                                    No visible elements selected.
                                </div>
                            @endif
                        </div>

                        <div class="elive-actions">
                            @if ($badgePreviewUrl)
                                <a href="{{ $badgePreviewUrl }}" target="_blank" class="elive-btn elive-btn-primary">
                                    Open Preview Badge
                                </a>
                            @endif

                            <button type="button" onclick="window.print()" class="elive-btn elive-btn-dark">
                                Print Preview
                            </button>

                            <a href="{{ \App\Filament\Resources\BadgeTemplates\BadgeTemplateResource::getUrl('design', ['record' => $template]) }}" class="elive-btn elive-btn-light">
                                Open Designer
                            </a>

                            <a href="{{ \App\Filament\Resources\BadgeTemplates\BadgeTemplateResource::getUrl('edit', ['record' => $template]) }}" class="elive-btn elive-btn-light">
                                Edit Template
                            </a>
                        </div>
                    </div>

                    <div class="elive-badge-preview-box">
                        <div class="elive-badge-preview-title">
                            <strong>Generated Badge</strong>
                            <span class="elive-size-pill">{{ $templateSize }}</span>
                        </div>

                        <div class="elive-badge-preview-frame">
                            @if ($badgePreviewUrl)
                                <img src="{{ $badgePreviewUrl }}" alt="Badge Template Preview">
                            @else
                                <div class="elive-empty">
                                    No attendee found for preview.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>