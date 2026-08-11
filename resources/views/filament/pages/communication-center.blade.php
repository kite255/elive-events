<x-filament-panels::page>
    <style>
        .elive-communication-center {
            display: grid;
            gap: 24px;
        }

        .elive-grid {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
            gap: 24px;
            align-items: start;
        }

        .elive-stack {
            display: grid;
            gap: 24px;
        }

        .elive-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            overflow: hidden;
            box-shadow:
                0 1px 2px rgba(15, 23, 42, 0.03),
                0 8px 24px rgba(15, 23, 42, 0.04);
        }

        .dark .elive-card {
            background: rgba(24, 24, 27, 0.8);
            border-color: rgba(255, 255, 255, 0.08);
        }

        .elive-card-header {
            padding: 20px 22px;
            border-bottom: 1px solid #e5e7eb;
        }

        .dark .elive-card-header {
            border-color: rgba(255, 255, 255, 0.08);
        }

        .elive-card-title {
            margin: 0;
            font-size: 16px;
            line-height: 1.3;
            font-weight: 800;
            color: #111827;
        }

        .dark .elive-card-title {
            color: #ffffff;
        }

        .elive-card-description {
            margin: 6px 0 0;
            font-size: 13px;
            line-height: 1.6;
            color: #6b7280;
        }

        .elive-card-body {
            padding: 22px;
        }

        .elive-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .elive-field {
            display: grid;
            gap: 8px;
        }

        .elive-field-full {
            grid-column: 1 / -1;
        }

        .elive-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: 13px;
            font-weight: 700;
            color: #374151;
        }

        .dark .elive-label {
            color: #e5e7eb;
        }

        .elive-required {
            font-size: 11px;
            font-weight: 600;
            color: #9ca3af;
        }

        .elive-input,
        .elive-select,
        .elive-textarea {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            background: #ffffff;
            color: #111827;
            font-size: 14px;
            outline: none;
            transition:
                border-color 0.15s ease,
                box-shadow 0.15s ease,
                background 0.15s ease;
        }

        .elive-input,
        .elive-select {
            min-height: 44px;
            padding: 0 13px;
        }

        .elive-textarea {
            min-height: 190px;
            padding: 14px;
            resize: vertical;
            line-height: 1.65;
        }

        .elive-input:focus,
        .elive-select:focus,
        .elive-textarea:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }

        .dark .elive-input,
        .dark .elive-select,
        .dark .elive-textarea {
            background: rgba(255, 255, 255, 0.04);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.12);
        }

        .elive-help {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.5;
        }

        .elive-error {
            font-size: 12px;
            color: #dc2626;
        }

        .elive-placeholder-box {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .elive-placeholder {
            display: inline-flex;
            align-items: center;
            padding: 5px 9px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #4b5563;
            font-size: 11px;
            font-weight: 700;
            border: 1px solid #e5e7eb;
        }

        .dark .elive-placeholder {
            background: rgba(255, 255, 255, 0.06);
            color: #d1d5db;
            border-color: rgba(255, 255, 255, 0.08);
        }

        .elive-message-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .elive-meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid #e5e7eb;
            background: #fafafa;
            color: #4b5563;
            font-size: 11px;
            font-weight: 700;
        }

        .dark .elive-meta-pill {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.08);
            color: #d1d5db;
        }

        .elive-test-box {
            display: grid;
            gap: 14px;
            padding: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #fafafa;
        }

        .dark .elive-test-box {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.08);
        }

        .elive-test-header {
            display: grid;
            gap: 4px;
        }

        .elive-test-title {
            font-size: 13px;
            font-weight: 800;
            color: #111827;
        }

        .dark .elive-test-title {
            color: #ffffff;
        }

        .elive-test-description {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.5;
        }

        .elive-test-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: end;
        }

        .elive-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .dark .elive-actions {
            border-color: rgba(255, 255, 255, 0.08);
        }

        .elive-action-info {
            font-size: 12px;
            color: #6b7280;
        }

        .elive-button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .elive-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 40px;
            padding: 0 15px;
            border-radius: 10px;
            border: 1px solid transparent;
            font-size: 13px;
            font-weight: 750;
            cursor: pointer;
            transition: 0.15s ease;
            white-space: nowrap;
        }

        .elive-btn-secondary {
            background: #ffffff;
            border-color: #d1d5db;
            color: #374151;
        }

        .elive-btn-secondary:hover {
            background: #f9fafb;
        }

        .dark .elive-btn-secondary {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.12);
            color: #e5e7eb;
        }

        .elive-btn-primary {
            background: #4f46e5;
            color: #ffffff;
        }

        .elive-btn-primary:hover {
            background: #4338ca;
        }

        .elive-btn-test {
            background: #111827;
            color: #ffffff;
        }

        .elive-btn-test:hover {
            background: #1f2937;
        }

        .dark .elive-btn-test {
            background: #f9fafb;
            color: #111827;
        }

        .elive-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .elive-stat-grid {
            display: grid;
            gap: 14px;
        }

        .elive-stat-card {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 17px;
            background: #fafafa;
        }

        .dark .elive-stat-card {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.08);
        }

        .elive-stat-label {
            font-size: 12px;
            font-weight: 650;
            color: #6b7280;
        }

        .elive-stat-value {
            margin-top: 6px;
            font-size: 30px;
            line-height: 1;
            font-weight: 900;
            color: #111827;
        }

        .dark .elive-stat-value {
            color: #ffffff;
        }

        .elive-stat-footer {
            margin-top: 8px;
            font-size: 11px;
            color: #9ca3af;
        }

        .elive-progress {
            height: 7px;
            overflow: hidden;
            border-radius: 999px;
            background: #e5e7eb;
            margin-top: 12px;
        }

        .dark .elive-progress {
            background: rgba(255, 255, 255, 0.08);
        }

        .elive-progress-bar {
            height: 100%;
            border-radius: inherit;
            background: #4f46e5;
        }

        .elive-summary {
            padding: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            display: grid;
            gap: 11px;
        }

        .dark .elive-summary {
            border-color: rgba(255, 255, 255, 0.08);
        }

        .elive-summary-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            font-size: 13px;
        }

        .elive-summary-label {
            color: #6b7280;
        }

        .elive-summary-value {
            text-align: right;
            font-weight: 750;
            color: #111827;
        }

        .dark .elive-summary-value {
            color: #ffffff;
        }

        .elive-summary-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 2px 0;
        }

        .dark .elive-summary-divider {
            background: rgba(255, 255, 255, 0.08);
        }

        .elive-table-wrap {
            overflow-x: auto;
        }

        .elive-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .elive-table th {
            padding: 12px 14px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }

        .dark .elive-table th {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(255, 255, 255, 0.08);
        }

        .elive-table td {
            padding: 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .dark .elive-table td {
            border-color: rgba(255, 255, 255, 0.05);
        }

        .elive-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .elive-campaign-name {
            font-weight: 750;
            color: #111827;
        }

        .dark .elive-campaign-name {
            color: #ffffff;
        }

        .elive-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 750;
            background: #f3f4f6;
            color: #4b5563;
        }

        .elive-badge-queued {
            background: #eef2ff;
            color: #4338ca;
        }

        .elive-badge-processing {
            background: #fff7ed;
            color: #c2410c;
        }

        .elive-badge-completed {
            background: #ecfdf5;
            color: #047857;
        }

        .elive-badge-failed {
            background: #fef2f2;
            color: #b91c1c;
        }

        .elive-badge-draft {
            background: #f3f4f6;
            color: #4b5563;
        }

        .elive-empty {
            padding: 40px 20px;
            text-align: center;
            color: #6b7280;
        }

        .elive-empty-title {
            font-weight: 750;
            color: #374151;
            margin-bottom: 5px;
        }

        .dark .elive-empty-title {
            color: #e5e7eb;
        }

        .elive-queue-note {
            display: grid;
            gap: 12px;
            font-size: 13px;
            line-height: 1.6;
            color: #4b5563;
        }

        .dark .elive-queue-note {
            color: #d1d5db;
        }

        .elive-code {
            display: inline-flex;
            border-radius: 6px;
            padding: 2px 6px;
            background: #f3f4f6;
            font-family: monospace;
            font-size: 11px;
        }

        .dark .elive-code {
            background: rgba(255, 255, 255, 0.06);
        }

        .elive-warning {
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid #fde68a;
            background: #fffbeb;
            color: #92400e;
            font-size: 12px;
            line-height: 1.55;
        }

        .dark .elive-warning {
            background: rgba(245, 158, 11, 0.08);
            border-color: rgba(245, 158, 11, 0.22);
            color: #fcd34d;
        }

        @media (max-width: 1100px) {
            .elive-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {
            .elive-form-grid {
                grid-template-columns: 1fr;
            }

            .elive-field-full {
                grid-column: auto;
            }

            .elive-test-row {
                grid-template-columns: 1fr;
            }

            .elive-card-header,
            .elive-card-body {
                padding: 17px;
            }

            .elive-button-group {
                width: 100%;
            }

            .elive-btn {
                flex: 1;
            }
        }
    </style>

    @php
        $events = $this->events();
        $templates = $this->templates();
        $categories = $this->categories();
        $campaigns = $this->recentCampaigns();

        $validRecipients = (int) ($preview['valid'] ?? 0);
        $totalRecipients = (int) ($preview['total'] ?? 0);
        $invalidRecipients = (int) ($preview['invalid'] ?? 0);

        $validPercentage = $totalRecipients > 0
            ? min(
                100,
                round(
                    ($validRecipients / $totalRecipients) * 100
                )
            )
            : 0;

        $characterCount = $this->smsCharacterCount();
        $segmentCount = $this->smsSegmentCount();
        $estimatedSmsUnits = $this->estimatedSmsUnits();

        $canQueue =
            filled($eventId)
            && filled($campaignName)
            && filled($message)
            && $validRecipients > 0;

        $canSendTest =
            filled($eventId)
            && filled($testPhone)
            && filled($message);
    @endphp

    <div class="elive-communication-center">

        <div class="elive-grid">

            {{-- LEFT SIDE --}}
            <div class="elive-stack">

                {{-- Campaign Builder --}}
                <section class="elive-card">

                    <div class="elive-card-header">
                        <h2 class="elive-card-title">
                            New SMS Campaign
                        </h2>

                        <p class="elive-card-description">
                            Select your event and audience, compose the message,
                            send a test SMS, then queue the final campaign.
                        </p>
                    </div>

                    <div class="elive-card-body">

                        <div class="elive-form-grid">

                            {{-- Event --}}
                            <div class="elive-field">

                                <label class="elive-label">
                                    <span>Event</span>

                                    <span class="elive-required">
                                        Required
                                    </span>
                                </label>

                                <select
                                    wire:model.live="eventId"
                                    class="elive-select"
                                >
                                    <option value="">
                                        Select event
                                    </option>

                                    @foreach ($events as $event)
                                        <option value="{{ $event->id }}">
                                            {{ $event->name }}

                                            @if ($event->organization)
                                                — {{ $event->organization->name }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>

                                @error('eventId')
                                    <div class="elive-error">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            {{-- Campaign Name --}}
                            <div class="elive-field">

                                <label class="elive-label">
                                    <span>Campaign Name</span>

                                    <span class="elive-required">
                                        Required
                                    </span>
                                </label>

                                <input
                                    type="text"
                                    wire:model="campaignName"
                                    class="elive-input"
                                    placeholder="Example: Event reminder SMS"
                                >

                                @error('campaignName')
                                    <div class="elive-error">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            {{-- Audience --}}
                            <div class="elive-field">

                                <label class="elive-label">
                                    Audience
                                </label>

                                <select
                                    wire:model.live="audience"
                                    class="elive-select"
                                >
                                    <option value="all">
                                        All eligible attendees
                                    </option>

                                    <option value="approved">
                                        Approved attendees
                                    </option>

                                    <option value="registered">
                                        Registered attendees
                                    </option>

                                    <option value="confirmed">
                                        Confirmed attendees
                                    </option>

                                    <option value="pending_approval">
                                        Pending approval
                                    </option>

                                    <option value="waitlisted">
                                        Waitlisted
                                    </option>

                                    <option value="checked_in">
                                        Checked in
                                    </option>

                                    <option value="not_checked_in">
                                        Not checked in
                                    </option>
                                </select>

                                @error('audience')
                                    <div class="elive-error">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            {{-- Category --}}
                            <div class="elive-field">

                                <label class="elive-label">
                                    Attendee Category
                                </label>

                                <select
                                    wire:model.live="categoryId"
                                    class="elive-select"
                                    @disabled(! $eventId)
                                >
                                    <option value="">
                                        All categories
                                    </option>

                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('categoryId')
                                    <div class="elive-error">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            {{-- Template --}}
                            <div class="elive-field elive-field-full">

                                <label class="elive-label">
                                    SMS Template
                                </label>

                                <select
                                    wire:model.live="templateId"
                                    class="elive-select"
                                    @disabled(! $eventId)
                                >
                                    <option value="">
                                        Write message manually
                                    </option>

                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}">
                                            {{ $template->name }}
                                        </option>
                                    @endforeach
                                </select>

                                @error('templateId')
                                    <div class="elive-error">
                                        {{ $message }}
                                    </div>
                                @enderror

                                <div class="elive-help">
                                    Select an existing SMS template or write
                                    the message manually below.
                                </div>
                            </div>

                            {{-- Message --}}
                            <div class="elive-field elive-field-full">

                                <label class="elive-label">
                                    <span>Message</span>

                                    <span class="elive-required">
                                        Required
                                    </span>
                                </label>

                                <textarea
                                    wire:model.live.debounce.300ms="message"
                                    class="elive-textarea"
                                    placeholder="Hello #NAME#, this is a reminder for #EVENT_NAME#..."
                                ></textarea>

                                @error('message')
                                    <div class="elive-error">
                                        {{ $message }}
                                    </div>
                                @enderror

                                <div class="elive-message-meta">

                                    <span class="elive-meta-pill">
                                        {{ number_format($characterCount) }}
                                        characters
                                    </span>

                                    <span class="elive-meta-pill">
                                        {{ number_format($segmentCount) }}
                                        SMS segment{{ $segmentCount === 1 ? '' : 's' }}
                                    </span>

                                    <span class="elive-meta-pill">
                                        {{ number_format($estimatedSmsUnits) }}
                                        estimated SMS units
                                    </span>

                                </div>

                                <div class="elive-help">
                                    Personalize your message using placeholders.
                                    Actual attendee values are inserted when the
                                    campaign is processed.
                                </div>

                                <div class="elive-placeholder-box">

                                    @foreach ([
                                        '#NAME#',
                                        '#PHONE#',
                                        '#EMAIL#',
                                        '#ORGANIZATION#',
                                        '#POSITION#',
                                        '#CATEGORY#',
                                        '#BADGE_NUMBER#',
                                        '#EVENT_NAME#',
                                        '#EVENT_VENUE#',
                                        '#EVENT_DATE#',
                                        '#EVENT_TIME#',
                                    ] as $placeholder)

                                        <span class="elive-placeholder">
                                            {{ $placeholder }}
                                        </span>

                                    @endforeach

                                </div>

                            </div>

                            {{-- Test SMS --}}
                            <div class="elive-field elive-field-full">

                                <div class="elive-test-box">

                                    <div class="elive-test-header">

                                        <div class="elive-test-title">
                                            Send Test SMS
                                        </div>

                                        <div class="elive-test-description">
                                            Send the current message to one
                                            phone number before launching the
                                            campaign. No campaign recipients
                                            are created.
                                        </div>

                                    </div>

                                    <div class="elive-test-row">

                                        <div class="elive-field">

                                            <label class="elive-label">
                                                Test Phone Number
                                            </label>

                                            <input
                                                type="tel"
                                                wire:model="testPhone"
                                                class="elive-input"
                                                placeholder="0768461644 or 255768461644"
                                            >

                                            @error('testPhone')
                                                <div class="elive-error">
                                                    {{ $message }}
                                                </div>
                                            @enderror

                                        </div>

                                        <button
                                            type="button"
                                            wire:click="sendTestSms"
                                            wire:loading.attr="disabled"
                                            wire:target="sendTestSms"
                                            class="elive-btn elive-btn-test"
                                            @disabled(! $canSendTest)
                                        >

                                            <span
                                                wire:loading.remove
                                                wire:target="sendTestSms"
                                            >
                                                Send Test SMS
                                            </span>

                                            <span
                                                wire:loading
                                                wire:target="sendTestSms"
                                            >
                                                Sending...
                                            </span>

                                        </button>

                                    </div>

                                    <div class="elive-help">
                                        For Tanzania, numbers such as
                                        <strong>0768461644</strong> are
                                        automatically converted to
                                        <strong>255768461644</strong>.
                                    </div>

                                </div>

                            </div>

                        </div>

                        {{-- Main Actions --}}
                        <div class="elive-actions">

                            <div class="elive-action-info">
                                {{ number_format($validRecipients) }}
                                valid recipient(s) currently eligible.
                            </div>

                            <div class="elive-button-group">

                                <button
                                    type="button"
                                    wire:click="refreshPreview"
                                    wire:loading.attr="disabled"
                                    wire:target="refreshPreview"
                                    class="elive-btn elive-btn-secondary"
                                    @disabled(! $eventId)
                                >

                                    <span
                                        wire:loading.remove
                                        wire:target="refreshPreview"
                                    >
                                        Refresh Preview
                                    </span>

                                    <span
                                        wire:loading
                                        wire:target="refreshPreview"
                                    >
                                        Refreshing...
                                    </span>

                                </button>

                                <button
                                    type="button"
                                    wire:click="queueCampaign"
                                    wire:loading.attr="disabled"
                                    wire:target="queueCampaign"
                                    class="elive-btn elive-btn-primary"
                                    @disabled(! $canQueue)
                                >

                                    <span
                                        wire:loading.remove
                                        wire:target="queueCampaign"
                                    >
                                        Queue SMS Campaign
                                    </span>

                                    <span
                                        wire:loading
                                        wire:target="queueCampaign"
                                    >
                                        Queuing...
                                    </span>

                                </button>

                            </div>

                        </div>

                    </div>
                </section>

                {{-- Recent Campaigns --}}
                <section class="elive-card">

                    <div class="elive-card-header">

                        <h2 class="elive-card-title">
                            Recent Campaigns
                        </h2>

                        <p class="elive-card-description">
                            Latest SMS campaigns created for events you manage.
                        </p>

                    </div>

                    @if ($campaigns->isEmpty())

                        <div class="elive-empty">

                            <div class="elive-empty-title">
                                No campaigns yet
                            </div>

                            <div>
                                Your first SMS campaign will appear here.
                            </div>

                        </div>

                    @else

                        <div class="elive-table-wrap">

                            <table class="elive-table">

                                <thead>
                                    <tr>
                                        <th>Campaign</th>
                                        <th>Event</th>
                                        <th>Status</th>
                                        <th>Recipients</th>
                                        <th>Queued</th>
                                        <th>Sent</th>
                                        <th>Failed</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    @foreach ($campaigns as $campaign)

                                        @php
                                            $badgeClass = match ($campaign->status) {
                                                'queued' => 'elive-badge-queued',
                                                'processing' => 'elive-badge-processing',
                                                'completed' => 'elive-badge-completed',
                                                'failed' => 'elive-badge-failed',
                                                default => 'elive-badge-draft',
                                            };
                                        @endphp

                                        <tr>

                                            <td>

                                                <div class="elive-campaign-name">
                                                    {{ $campaign->name }}
                                                </div>

                                                <div class="elive-help">
                                                    {{ strtoupper($campaign->channel) }}
                                                </div>

                                            </td>

                                            <td>
                                                {{ $campaign->event?->name ?? '—' }}
                                            </td>

                                            <td>

                                                <span
                                                    class="elive-badge {{ $badgeClass }}"
                                                >
                                                    {{ str($campaign->status)->headline() }}
                                                </span>

                                            </td>

                                            <td>
                                                {{ number_format($campaign->total_recipients ?? 0) }}
                                            </td>

                                            <td>
                                                {{ number_format($campaign->queued_count ?? 0) }}
                                            </td>

                                            <td>
                                                {{ number_format($campaign->sent_count ?? 0) }}
                                            </td>

                                            <td>
                                                {{ number_format($campaign->failed_count ?? 0) }}
                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    @endif

                </section>

            </div>

            {{-- RIGHT SIDE --}}
            <aside class="elive-stack">

                {{-- Recipient Preview --}}
                <section class="elive-card">

                    <div class="elive-card-header">

                        <h2 class="elive-card-title">
                            Recipient Preview
                        </h2>

                        <p class="elive-card-description">
                            Verify your audience before sending.
                        </p>

                    </div>

                    <div class="elive-card-body">

                        <div class="elive-stat-grid">

                            <div class="elive-stat-card">

                                <div class="elive-stat-label">
                                    Eligible attendees
                                </div>

                                <div class="elive-stat-value">
                                    {{ number_format($totalRecipients) }}
                                </div>

                                <div class="elive-stat-footer">
                                    Matches event and selected filters
                                </div>

                            </div>

                            <div class="elive-stat-card">

                                <div class="elive-stat-label">
                                    Valid SMS recipients
                                </div>

                                <div class="elive-stat-value">
                                    {{ number_format($validRecipients) }}
                                </div>

                                <div class="elive-progress">

                                    <div
                                        class="elive-progress-bar"
                                        style="width: {{ $validPercentage }}%"
                                    ></div>

                                </div>

                                <div class="elive-stat-footer">
                                    {{ $validPercentage }}% ready to send
                                </div>

                            </div>

                            <div class="elive-stat-card">

                                <div class="elive-stat-label">
                                    Missing / invalid phones
                                </div>

                                <div class="elive-stat-value">
                                    {{ number_format($invalidRecipients) }}
                                </div>

                                <div class="elive-stat-footer">
                                    These attendees will be skipped
                                </div>

                            </div>

                            <div class="elive-stat-card">

                                <div class="elive-stat-label">
                                    Estimated SMS units
                                </div>

                                <div class="elive-stat-value">
                                    {{ number_format($estimatedSmsUnits) }}
                                </div>

                                <div class="elive-stat-footer">

                                    {{ number_format($validRecipients) }}
                                    recipients ×
                                    {{ number_format($segmentCount) }}
                                    segment{{ $segmentCount === 1 ? '' : 's' }}

                                </div>

                            </div>

                        </div>

                    </div>

                </section>

                {{-- Campaign Summary --}}
                <section class="elive-card">

                    <div class="elive-card-header">
                        <h2 class="elive-card-title">
                            Campaign Summary
                        </h2>
                    </div>

                    <div class="elive-card-body">

                        <div class="elive-summary">

                            <div class="elive-summary-row">

                                <span class="elive-summary-label">
                                    Channel
                                </span>

                                <span class="elive-summary-value">
                                    SMS
                                </span>

                            </div>

                            <div class="elive-summary-row">

                                <span class="elive-summary-label">
                                    Audience
                                </span>

                                <span class="elive-summary-value">
                                    {{ str($audience)->replace('_', ' ')->headline() }}
                                </span>

                            </div>

                            <div class="elive-summary-row">

                                <span class="elive-summary-label">
                                    Valid recipients
                                </span>

                                <span class="elive-summary-value">
                                    {{ number_format($validRecipients) }}
                                </span>

                            </div>

                            <div class="elive-summary-row">

                                <span class="elive-summary-label">
                                    Invalid / missing
                                </span>

                                <span class="elive-summary-value">
                                    {{ number_format($invalidRecipients) }}
                                </span>

                            </div>

                            <div class="elive-summary-divider"></div>

                            <div class="elive-summary-row">

                                <span class="elive-summary-label">
                                    Characters
                                </span>

                                <span class="elive-summary-value">
                                    {{ number_format($characterCount) }}
                                </span>

                            </div>

                            <div class="elive-summary-row">

                                <span class="elive-summary-label">
                                    SMS segments
                                </span>

                                <span class="elive-summary-value">
                                    {{ number_format($segmentCount) }}
                                </span>

                            </div>

                            <div class="elive-summary-row">

                                <span class="elive-summary-label">
                                    Estimated SMS units
                                </span>

                                <span class="elive-summary-value">
                                    {{ number_format($estimatedSmsUnits) }}
                                </span>

                            </div>

                        </div>

                        @if ($segmentCount > 1)

                            <div
                                class="elive-warning"
                                style="margin-top: 14px;"
                            >
                                This message is estimated to use
                                <strong>
                                    {{ number_format($segmentCount) }}
                                    SMS segments
                                </strong>
                                per recipient. Shortening the message can
                                reduce SMS usage and cost.
                            </div>

                        @endif

                    </div>

                </section>

                {{-- Queue --}}
                <section class="elive-card">

                    <div class="elive-card-header">
                        <h2 class="elive-card-title">
                            SMS Queue
                        </h2>
                    </div>

                    <div class="elive-card-body">

                        <div class="elive-queue-note">

                            <div>
                                Bulk campaign messages are recorded in
                                <span class="elive-code">
                                    CommunicationLog
                                </span>
                                and processed asynchronously.
                            </div>

                            <div>
                                Redis queue:
                                <span class="elive-code">
                                    communications
                                </span>
                            </div>

                            <div>
                                Test SMS messages are sent directly to the
                                selected test number and do not create
                                campaign recipients.
                            </div>

                            <div>
                                Invalid phone numbers are skipped before
                                they reach the SMS provider.
                            </div>

                        </div>

                    </div>

                </section>

            </aside>

        </div>

    </div>
</x-filament-panels::page>