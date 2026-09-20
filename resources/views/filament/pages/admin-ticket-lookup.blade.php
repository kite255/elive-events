<x-filament-panels::page>
    @php
        $results = $this->results;

        $statusStyle = fn ($status) => match ($status) {
            'paid', 'issued', 'completed' =>
                'background:#ecfdf3;color:#027a48;border-color:#abefc6;',

            'pending', 'processing' =>
                'background:#fffaeb;color:#b54708;border-color:#fedf89;',

            'expired', 'failed', 'cancelled', 'refunded' =>
                'background:#fff1f3;color:#c01048;border-color:#fecdd6;',

            default =>
                'background:#f8fafc;color:#475569;border-color:#e2e8f0;',
        };

        $formatMoney = fn ($amount, $currency) =>
            ($currency ?: 'TZS') . ' '
            . number_format((float) $amount, 0);
    @endphp

    <div class="elive-lookup-shell">
        <div class="elive-lookup-wrap">
            <div class="elive-lookup-head">
                <div>
                    <div class="elive-lookup-kicker">
                        Ticketing
                    </div>

                    <h1>
                        Admin Manual Lookup
                    </h1>

                    <p>
                        Find ticket buyers and ticket records using an
                        order number, ticket number, buyer name, phone,
                        or email.
                    </p>
                </div>
            </div>

            <section class="elive-lookup-panel">
                <div class="elive-search-block">
                    <label for="ticket-lookup-search">
                        Search tickets
                    </label>

                    <input
                        id="ticket-lookup-search"
                        type="search"
                        wire:model.live.debounce.350ms="search"
                        placeholder="Order number, ticket number, name, phone, or email"
                        autocomplete="off"
                        class="elive-lookup-input"
                    >

                    <div class="elive-search-hint">
                        Search is restricted to events you are
                        authorized to view.
                    </div>
                </div>
            </section>

            @if (trim($search) === '')
                <section class="elive-empty-panel">
                    <h2>Start a lookup</h2>
                    <p>
                        Enter an Order Number, Ticket Number, buyer name,
                        phone number, or email address.
                    </p>
                </section>
            @elseif (count($results) === 0)
                <section class="elive-empty-panel">
                    <h2>No matching records</h2>
                    <p>
                        No accessible ticket or order matched
                        "{{ $search }}".
                    </p>
                </section>
            @else
                <div class="elive-result-summary">
                    {{ count($results) }}
                    {{ count($results) === 1 ? 'record' : 'records' }}
                    found
                </div>

                <div class="elive-result-list">
                    @foreach ($results as $result)
                        <article class="elive-result-card">
                            <div class="elive-result-top">
                                <div>
                                    <div class="elive-event-name">
                                        {{ $result['event_name'] }}
                                    </div>

                                    <div class="elive-primary-id">
                                        {{ $result['ticket_number'] }}
                                    </div>
                                </div>

                                <div class="elive-ticket-type">
                                    {{ $result['ticket_type'] }}
                                </div>
                            </div>

                            <div class="elive-detail-grid">
                                <div class="elive-detail">
                                    <span>Order Number</span>
                                    <strong>
                                        {{ $result['order_number'] }}
                                    </strong>
                                </div>

                                <div class="elive-detail">
                                    <span>Ticket Number</span>
                                    <strong>
                                        {{ $result['ticket_number'] }}
                                    </strong>
                                </div>

                                <div class="elive-detail">
                                    <span>Buyer</span>
                                    <strong>
                                        {{ $result['buyer_name'] }}
                                    </strong>
                                </div>

                                <div class="elive-detail">
                                    <span>Phone</span>
                                    <strong>
                                        {{ $result['buyer_phone'] ?: '-' }}
                                    </strong>
                                </div>

                                <div class="elive-detail">
                                    <span>Email</span>
                                    <strong>
                                        {{ $result['buyer_email'] ?: '-' }}
                                    </strong>
                                </div>

                                <div class="elive-detail">
                                    <span>Amount</span>
                                    <strong>
                                        {{ $formatMoney(
                                            $result['amount'],
                                            $result['currency']
                                        ) }}
                                    </strong>
                                </div>

                                <div class="elive-detail">
                                    <span>Payment / Order Status</span>

                                    <strong>
                                        <span
                                            class="elive-status-chip"
                                            style="{{ $statusStyle(
                                                $result['order_status']
                                            ) }}"
                                        >
                                            {{ str_replace(
                                                '_',
                                                ' ',
                                                $result['order_status']
                                            ) }}
                                        </span>
                                    </strong>
                                </div>

                                <div class="elive-detail">
                                    <span>Ticket Status</span>

                                    <strong>
                                        <span
                                            class="elive-status-chip"
                                            style="{{ $statusStyle(
                                                $result['ticket_status']
                                            ) }}"
                                        >
                                            {{ str_replace(
                                                '_',
                                                ' ',
                                                $result['ticket_status']
                                            ) }}
                                        </span>
                                    </strong>
                                </div>

                                <div class="elive-detail">
                                    <span>Check-in Status</span>

                                    <strong>
                                        @if ($result['is_checked_in'])
                                            Checked in
                                        @else
                                            Not checked in
                                        @endif
                                    </strong>

                                    @if ($result['used_at'])
                                        <small>
                                            {{ $result['used_at']->format(
                                                'd M Y, H:i'
                                            ) }}
                                        </small>
                                    @endif
                                </div>
                            </div>

                            <div class="elive-actions">
                                @if ($result['order_url'])
                                    <a
                                        href="{{ $result['order_url'] }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="elive-action-secondary"
                                    >
                                        View Order
                                    </a>
                                @endif

                                @if ($result['ticket_url'])
                                    <a
                                        href="{{ $result['ticket_url'] }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="elive-action-primary"
                                    >
                                        View Ticket
                                    </a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <style>
        .elive-lookup-shell {
            min-height: calc(100vh - 4rem);
            margin: -1rem;
            padding: 1.5rem;
            background:
                radial-gradient(
                    circle at 15% 0%,
                    rgba(0, 122, 178, 0.08),
                    transparent 28%
                ),
                linear-gradient(
                    180deg,
                    #eef4fb 0%,
                    #f7f9fc 48%,
                    #f8fafc 100%
                );
        }

        .elive-lookup-wrap {
            width: min(100%, 1280px);
            margin: 0 auto;
        }

        .elive-lookup-head {
            margin-bottom: 1.25rem;
        }

        .elive-lookup-kicker {
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .elive-lookup-head h1 {
            margin: 0.25rem 0 0;
            color: #161943;
            font-size: clamp(1.75rem, 3vw, 2.25rem);
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .elive-lookup-head p {
            max-width: 760px;
            margin: 0.65rem 0 0;
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .elive-lookup-panel,
        .elive-empty-panel,
        .elive-result-card {
            border: 1px solid #e7edf4;
            border-radius: 1.2rem;
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.055);
        }

        .elive-lookup-panel {
            padding: 1.1rem;
            margin-bottom: 1rem;
        }

        .elive-search-block label {
            display: block;
            margin-bottom: 0.45rem;
            color: #334155;
            font-size: 0.75rem;
            font-weight: 800;
        }

        .elive-lookup-input {
            width: 100%;
            min-height: 3rem;
            padding: 0 0.95rem;
            border: 1px solid #dfe7ef;
            border-radius: 0.8rem;
            background: #f8fafc;
            color: #0f172a;
            font-size: 0.88rem;
            outline: none;
        }

        .elive-lookup-input:focus {
            border-color: #007AB2;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 122, 178, 0.10);
        }

        .elive-search-hint {
            margin-top: 0.45rem;
            color: #94a3b8;
            font-size: 0.68rem;
        }

        .elive-empty-panel {
            padding: 2.5rem 1.25rem;
            text-align: center;
        }

        .elive-empty-panel h2 {
            margin: 0;
            color: #334155;
            font-size: 1rem;
            font-weight: 800;
        }

        .elive-empty-panel p {
            margin: 0.4rem 0 0;
            color: #94a3b8;
            font-size: 0.78rem;
        }

        .elive-result-summary {
            margin: 0 0 0.65rem;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .elive-result-list {
            display: grid;
            gap: 0.9rem;
        }

        .elive-result-card {
            padding: 1.1rem;
        }

        .elive-result-top,
        .elive-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .elive-event-name {
            color: #64748b;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .elive-primary-id {
            margin-top: 0.2rem;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 800;
        }

        .elive-ticket-type {
            padding: 0.42rem 0.65rem;
            border-radius: 999px;
            background: #eef4fb;
            color: #161943;
            font-size: 0.68rem;
            font-weight: 800;
        }

        .elive-detail-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .elive-detail {
            min-width: 0;
            padding: 0.75rem;
            border: 1px solid #edf2f7;
            border-radius: 0.85rem;
            background: #fbfcfe;
        }

        .elive-detail > span {
            display: block;
            margin-bottom: 0.3rem;
            color: #94a3b8;
            font-size: 0.6rem;
            font-weight: 800;
            letter-spacing: 0.07em;
            text-transform: uppercase;
        }

        .elive-detail strong {
            display: block;
            overflow: hidden;
            color: #334155;
            font-size: 0.76rem;
            text-overflow: ellipsis;
        }

        .elive-detail small {
            display: block;
            margin-top: 0.25rem;
            color: #94a3b8;
            font-size: 0.62rem;
        }

        .elive-status-chip {
            display: inline-flex;
            max-width: 100%;
            padding: 0.3rem 0.5rem;
            border: 1px solid;
            border-radius: 999px;
            font-size: 0.62rem;
            font-weight: 800;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .elive-actions {
            justify-content: flex-end;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eef2f6;
        }

        .elive-action-primary,
        .elive-action-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.4rem;
            padding: 0 0.85rem;
            border-radius: 0.7rem;
            font-size: 0.72rem;
            font-weight: 800;
            text-decoration: none;
        }

        .elive-action-primary {
            background: #161943;
            color: #ffffff;
        }

        .elive-action-secondary {
            border: 1px solid #dfe7ef;
            background: #ffffff;
            color: #334155;
        }

        @media (max-width: 900px) {
            .elive-detail-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 640px) {
            .elive-lookup-shell {
                margin: -0.75rem;
                padding: 1rem 0.75rem;
            }

            .elive-result-top,
            .elive-actions {
                align-items: flex-start;
                flex-direction: column;
            }

            .elive-detail-grid {
                grid-template-columns: 1fr;
            }

            .elive-actions {
                align-items: stretch;
            }

            .elive-action-primary,
            .elive-action-secondary {
                width: 100%;
            }
        }
    </style>
</x-filament-panels::page>
