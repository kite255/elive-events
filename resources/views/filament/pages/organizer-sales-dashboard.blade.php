<x-filament-panels::page>
    @php
        $events = $this->eventOptions();
        $metrics = $this->salesMetrics();

        $currency = $metrics['currency'] ?? 'TZS';
        $salesByTicketType = $metrics['sales_by_ticket_type'] ?? [];
        $recentOrders = $metrics['recent_orders'] ?? [];

        $formatMoney = fn ($amount) =>
            $currency . ' ' . number_format((float) $amount, 0);

        $statusClasses = fn ($status) => match ($status) {
            'paid', 'completed' =>
                'background:#ecfdf3;color:#027a48;border-color:#abefc6;',

            'processing', 'pending' =>
                'background:#fffaeb;color:#b54708;border-color:#fedf89;',

            'expired', 'failed', 'cancelled' =>
                'background:#fff1f3;color:#c01048;border-color:#fecdd6;',

            'refunded', 'partially_refunded' =>
                'background:#f4f3ff;color:#5925dc;border-color:#d9d6fe;',

            default =>
                'background:#f8fafc;color:#475569;border-color:#e2e8f0;',
        };

        $statusDot = fn ($status) => match ($status) {
            'paid', 'completed' => '#12b76a',
            'processing', 'pending' => '#f79009',
            'expired', 'failed', 'cancelled' => '#f04438',
            'refunded', 'partially_refunded' => '#7a5af8',
            default => '#94a3b8',
        };
    @endphp

    <div class="elive-sales-shell">
        <div class="elive-sales-wrap">

            {{-- Header --}}
            <div class="elive-page-head">
                <div>
                    <div class="elive-kicker">Ticketing</div>
                    <h1 class="elive-page-title">Organizer Sales Dashboard</h1>
                    <p class="elive-page-subtitle">
                        Review ticket revenue, order activity, capacity usage, and recent buyer transactions.
                    </p>
                </div>

                <div class="elive-live-pill">
                    <span class="elive-live-dot"></span>
                    Sales overview
                </div>
            </div>

            {{-- Filters --}}
            <section class="elive-panel elive-filter-panel">
                <div class="elive-filter-copy">
                    <h2>Ticket Sales Overview</h2>
                    <p>Choose an event to review its current sales performance.</p>
                </div>

                <div class="elive-filter-grid">
                    <div class="elive-field">
                        <label for="sales-event-selector">Event</label>

                        <select
                            id="sales-event-selector"
                            wire:model.live="selectedEventId"
                            class="elive-select"
                        >
                            <option value="">Select event</option>

                            @foreach ($events as $eventId => $eventName)
                                <option value="{{ $eventId }}">
                                    {{ $eventName }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="elive-field">
                        <label>Selected Event</label>
                        <div class="elive-readonly">
                            {{ $events[$this->selectedEventId] ?? 'No event selected' }}
                        </div>
                    </div>

                    <div class="elive-field">
                        <label>Currency</label>
                        <div class="elive-readonly elive-readonly-short">
                            {{ $currency }}
                        </div>
                    </div>
                </div>
            </section>

            {{-- KPI cards --}}
            <div class="elive-kpi-grid">
                <article class="elive-kpi-card">
                    <div class="elive-kpi-accent elive-kpi-accent-navy"></div>

                    <div class="elive-kpi-top">
                        <div>
                            <p class="elive-kpi-label">Gross Sales</p>
                            <p class="elive-kpi-value">
                                {{ $formatMoney($metrics['gross_sales'] ?? 0) }}
                            </p>
                        </div>

                        <div class="elive-icon-box elive-icon-indigo">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 3v18M16 6.5C15.2 5.7 13.9 5 12 5 9.8 5 8 6.1 8 8s1.8 2.6 4 3 4 1.1 4 3-1.8 3-4 3-3.4-.7-4.3-1.7"/>
                            </svg>
                        </div>
                    </div>

                    <p class="elive-kpi-help">Revenue from paid orders</p>
                </article>

                <article class="elive-kpi-card">
                    <div class="elive-kpi-accent elive-kpi-accent-green"></div>

                    <div class="elive-kpi-top">
                        <div>
                            <p class="elive-kpi-label">Paid Orders</p>
                            <p class="elive-kpi-value">
                                {{ number_format($metrics['paid_orders'] ?? 0) }}
                            </p>
                        </div>

                        <div class="elive-icon-box elive-icon-green">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m5 12 4 4L19 6"/>
                            </svg>
                        </div>
                    </div>

                    <p class="elive-kpi-help">Successfully completed</p>
                </article>

                <article class="elive-kpi-card">
                    <div class="elive-kpi-accent elive-kpi-accent-amber"></div>

                    <div class="elive-kpi-top">
                        <div>
                            <p class="elive-kpi-label">Pending Orders</p>
                            <p class="elive-kpi-value">
                                {{ number_format($metrics['pending_orders'] ?? 0) }}
                            </p>
                        </div>

                        <div class="elive-icon-box elive-icon-amber">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="8"/>
                                <path d="M12 8v4l3 2"/>
                            </svg>
                        </div>
                    </div>

                    <p class="elive-kpi-help">Awaiting payment</p>
                </article>

                <article class="elive-kpi-card">
                    <div class="elive-kpi-accent elive-kpi-accent-red"></div>

                    <div class="elive-kpi-top">
                        <div>
                            <p class="elive-kpi-label">Expired Orders</p>
                            <p class="elive-kpi-value">
                                {{ number_format($metrics['expired_orders'] ?? 0) }}
                            </p>
                        </div>

                        <div class="elive-icon-box elive-icon-red">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="8"/>
                                <path d="m9 9 6 6m0-6-6 6"/>
                            </svg>
                        </div>
                    </div>

                    <p class="elive-kpi-help">Reservation timeout</p>
                </article>

                <article class="elive-kpi-card">
                    <div class="elive-kpi-accent elive-kpi-accent-blue"></div>

                    <div class="elive-kpi-top">
                        <div>
                            <p class="elive-kpi-label">Tickets Sold</p>
                            <p class="elive-kpi-value">
                                {{ number_format($metrics['tickets_sold'] ?? 0) }}
                            </p>
                        </div>

                        <div class="elive-icon-box elive-icon-blue">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 7h16v10H4zM8 7v10m8-10v10"/>
                            </svg>
                        </div>
                    </div>

                    <p class="elive-kpi-help">Paid ticket quantity</p>
                </article>
            </div>

            {{-- Main content --}}
            <div class="elive-main-grid">

                {{-- Sales by Ticket Type --}}
                <section class="elive-panel elive-ticket-panel">
                    <div class="elive-section-head">
                        <div>
                            <div class="elive-section-kicker">Performance</div>
                            <h2>Sales by Ticket Type</h2>
                            <p>Revenue and remaining capacity for each ticket category.</p>
                        </div>

                        <div class="elive-sold-chip">
                            {{ number_format($metrics['tickets_sold'] ?? 0) }} sold
                        </div>
                    </div>

                    @if (count($metrics['sales_by_ticket_type']) > 0)
                        <div class="elive-ticket-list">
                            @foreach ($metrics['sales_by_ticket_type'] as $row)
                                @php
                                    $sold = (int) ($row['quantity'] ?? 0);
                                    $capacity = $row['capacity'] ?? null;
                                    $remaining = $row['remaining'] ?? null;

                                    $percentage =
                                        $capacity !== null && (int) $capacity > 0
                                            ? min(
                                                100,
                                                (int) round(
                                                    ($sold / (int) $capacity) * 100
                                                )
                                            )
                                            : 0;
                                @endphp

                                <article class="elive-ticket-row">
                                    <div class="elive-ticket-row-top">
                                        <div class="elive-ticket-name-wrap">
                                            <div class="elive-ticket-name-line">
                                                <h3>{{ $row['name'] ?? '-' }}</h3>

                                                @if (! empty($row['code']))
                                                    <span class="elive-code-chip">
                                                        {{ $row['code'] }}
                                                    </span>
                                                @endif
                                            </div>

                                            <p>
                                                {{ number_format($sold) }} sold
                                                @if ($capacity !== null)
                                                    of {{ number_format((int) $capacity) }}
                                                @endif
                                            </p>
                                        </div>

                                        <div class="elive-ticket-money">
                                            <strong>{{ $formatMoney($row['revenue'] ?? 0) }}</strong>

                                            <span>
                                                @if ($remaining === null)
                                                    Unlimited
                                                @else
                                                    {{ number_format((int) $remaining) }} remaining
                                                @endif
                                            </span>
                                        </div>
                                    </div>

                                    @if ($capacity !== null)
                                        <div class="elive-capacity-block">
                                            <div class="elive-capacity-meta">
                                                <span>Capacity used</span>
                                                <span>{{ $percentage }}%</span>
                                            </div>

                                            <div class="elive-progress-track">
                                                <div
                                                    class="elive-progress-bar"
                                                    style="width: {{ $percentage }}%;"
                                                ></div>
                                            </div>
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="elive-empty-state">
                            <div class="elive-empty-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M4 7h16v10H4z"/>
                                </svg>
                            </div>

                            <h3>No paid ticket sales yet</h3>
                            <p>Ticket type performance will appear after the first paid order.</p>
                        </div>
                    @endif
                </section>

                {{-- Recent Orders --}}
                <section class="elive-panel elive-orders-panel">
                    <div class="elive-section-head">
                        <div>
                            <div class="elive-section-kicker">Activity</div>
                            <h2>Recent Orders</h2>
                            <p>Latest buyer activity and current order status.</p>
                        </div>
                    </div>

                    @if (count($metrics['recent_orders']) > 0)
                        <div class="elive-table-wrap">
                            <table class="elive-orders-table">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Buyer</th>
                                        <th class="elive-center">Qty</th>
                                        <th class="elive-right">Amount</th>
                                        <th class="elive-right">Status</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @foreach ($metrics['recent_orders'] as $order)
                                        <tr>
                                            <td>
                                                <div class="elive-order-number">
                                                    {{ $order['order_number'] ?? '-' }}
                                                </div>

                                                @if (! empty($order['created_at']))
                                                    <div class="elive-order-meta">
                                                        {{ $order['created_at']->format('d M Y, H:i') }}
                                                    </div>
                                                @endif
                                            </td>

                                            <td>
                                                <div class="elive-buyer-name">
                                                    {{ $order['buyer_name'] ?? '-' }}
                                                </div>

                                                @if (! empty($order['buyer_email']))
                                                    <div class="elive-order-meta">
                                                        {{ $order['buyer_email'] }}
                                                    </div>
                                                @elseif (! empty($order['buyer_phone']))
                                                    <div class="elive-order-meta">
                                                        {{ $order['buyer_phone'] }}
                                                    </div>
                                                @endif
                                            </td>

                                            <td class="elive-center">
                                                <span class="elive-qty-chip">
                                                    {{ number_format($order['quantity'] ?? 0) }}
                                                </span>
                                            </td>

                                            <td class="elive-right elive-amount">
                                                {{ $formatMoney($order['total'] ?? 0) }}
                                            </td>

                                            <td class="elive-right">
                                                <span
                                                    class="elive-status-chip"
                                                    style="{{ $statusClasses($order['status'] ?? '') }}"
                                                >
                                                    <span
                                                        class="elive-status-dot"
                                                        style="background: {{ $statusDot($order['status'] ?? '') }};"
                                                    ></span>

                                                    {{ str_replace('_', ' ', $order['status'] ?? 'unknown') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="elive-empty-state">
                            <div class="elive-empty-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M6 7h12M6 12h12M6 17h8"/>
                                </svg>
                            </div>

                            <h3>No recent orders</h3>
                            <p>New ticket orders will appear here automatically.</p>
                        </div>
                    @endif
                </section>
            </div>

        </div>
    </div>

    <style>
        .elive-sales-shell {
            min-height: calc(100vh - 4rem);
            margin: -1rem;
            padding: 1.5rem;
            background:
                radial-gradient(circle at 15% 0%, rgba(0, 122, 178, 0.08), transparent 28%),
                linear-gradient(180deg, #eef4fb 0%, #f7f9fc 48%, #f8fafc 100%);
        }

        .elive-sales-wrap {
            width: min(100%, 1500px);
            margin: 0 auto;
        }

        .elive-page-head,
        .elive-section-head,
        .elive-kpi-top,
        .elive-ticket-row-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .elive-page-head {
            margin-bottom: 1.25rem;
            align-items: flex-end;
        }

        .elive-kicker,
        .elive-section-kicker,
        .elive-kpi-label,
        .elive-field label {
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-weight: 800;
        }

        .elive-kicker,
        .elive-section-kicker {
            color: #64748b;
            font-size: 0.68rem;
        }

        .elive-page-title {
            margin: 0.25rem 0 0;
            color: #161943;
            font-size: clamp(1.75rem, 3vw, 2.25rem);
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .elive-page-subtitle {
            max-width: 700px;
            margin: 0.65rem 0 0;
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .elive-live-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 0.8rem;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.9);
            color: #475569;
            font-size: 0.75rem;
            font-weight: 700;
            white-space: nowrap;
            box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04);
        }

        .elive-live-dot {
            width: 0.48rem;
            height: 0.48rem;
            border-radius: 999px;
            background: #12b76a;
            box-shadow: 0 0 0 4px rgba(18, 183, 106, 0.10);
        }

        .elive-panel {
            border: 1px solid #e7edf4;
            border-radius: 1.25rem;
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.055);
        }

        .elive-filter-panel {
            padding: 1.15rem;
            margin-bottom: 1rem;
        }

        .elive-filter-copy h2,
        .elive-section-head h2 {
            margin: 0;
            color: #0f172a;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .elive-filter-copy h2 {
            font-size: 0.95rem;
        }

        .elive-filter-copy p,
        .elive-section-head p {
            margin: 0.3rem 0 0;
            color: #64748b;
            line-height: 1.5;
        }

        .elive-filter-copy p {
            font-size: 0.78rem;
        }

        .elive-filter-grid {
            display: grid;
            grid-template-columns: minmax(280px, 1.6fr) minmax(180px, 0.9fr) minmax(120px, 0.55fr);
            gap: 0.9rem;
            margin-top: 1rem;
        }

        .elive-field label {
            display: block;
            margin-bottom: 0.4rem;
            color: #94a3b8;
            font-size: 0.65rem;
        }

        .elive-select,
        .elive-readonly {
            width: 100%;
            min-height: 2.75rem;
            border: 1px solid #dfe7ef;
            border-radius: 0.75rem;
            background: #f8fafc;
            color: #1e293b;
            font-size: 0.82rem;
            font-weight: 650;
        }

        .elive-select {
            padding: 0 0.9rem;
            outline: none;
            transition: border-color 150ms ease, box-shadow 150ms ease, background 150ms ease;
        }

        .elive-select:focus {
            border-color: #007AB2;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 122, 178, 0.10);
        }

        .elive-readonly {
            display: flex;
            align-items: center;
            padding: 0 0.9rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .elive-kpi-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 0.9rem;
            margin-bottom: 1rem;
        }

        .elive-kpi-card {
            position: relative;
            overflow: hidden;
            min-height: 142px;
            padding: 1rem;
            border: 1px solid #e7edf4;
            border-radius: 1.15rem;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
            transition: transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease;
        }

        .elive-kpi-card:hover {
            transform: translateY(-2px);
            border-color: #d9e3ec;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08);
        }

        .elive-kpi-accent {
            position: absolute;
            inset: 0 0 auto 0;
            height: 3px;
        }

        .elive-kpi-accent-navy {
            background: linear-gradient(90deg, #161943, #007AB2);
        }

        .elive-kpi-accent-green {
            background: #12b76a;
        }

        .elive-kpi-accent-amber {
            background: #f79009;
        }

        .elive-kpi-accent-red {
            background: #f04438;
        }

        .elive-kpi-accent-blue {
            background: #0ba5ec;
        }

        .elive-kpi-top {
            align-items: flex-start;
        }

        .elive-kpi-label {
            margin: 0;
            color: #94a3b8;
            font-size: 0.65rem;
        }

        .elive-kpi-value {
            margin: 0.7rem 0 0;
            color: #0f172a;
            font-size: 1.55rem;
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.03em;
            white-space: nowrap;
        }

        .elive-kpi-help {
            margin: 1rem 0 0;
            color: #94a3b8;
            font-size: 0.72rem;
        }

        .elive-icon-box,
        .elive-empty-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }

        .elive-icon-box {
            width: 2.4rem;
            height: 2.4rem;
            border-radius: 0.75rem;
        }

        .elive-icon-box svg,
        .elive-empty-icon svg {
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .elive-icon-box svg {
            width: 1.15rem;
            height: 1.15rem;
        }

        .elive-icon-indigo {
            background: #eef2ff;
            color: #4f46e5;
        }

        .elive-icon-green {
            background: #ecfdf3;
            color: #039855;
        }

        .elive-icon-amber {
            background: #fffaeb;
            color: #dc6803;
        }

        .elive-icon-red {
            background: #fff1f3;
            color: #e31b54;
        }

        .elive-icon-blue {
            background: #f0f9ff;
            color: #0284c7;
        }

        .elive-main-grid {
            display: grid;
            grid-template-columns: minmax(320px, 0.9fr) minmax(0, 1.55fr);
            gap: 1rem;
        }

        .elive-ticket-panel,
        .elive-orders-panel {
            padding: 1.1rem;
        }

        .elive-section-head {
            align-items: flex-start;
        }

        .elive-section-head h2 {
            margin-top: 0.2rem;
            font-size: 1rem;
        }

        .elive-section-head p {
            font-size: 0.75rem;
        }

        .elive-sold-chip {
            flex: 0 0 auto;
            padding: 0.45rem 0.7rem;
            border-radius: 0.7rem;
            background: #fff4e8;
            color: #c95f00;
            font-size: 0.7rem;
            font-weight: 800;
        }

        .elive-ticket-list {
            display: grid;
            gap: 0.7rem;
            margin-top: 1rem;
        }

        .elive-ticket-row {
            padding: 0.9rem;
            border: 1px solid #edf2f7;
            border-radius: 0.95rem;
            background: #fbfcfe;
            transition: background 150ms ease, border-color 150ms ease, transform 150ms ease;
        }

        .elive-ticket-row:hover {
            transform: translateY(-1px);
            border-color: #dde6ef;
            background: #ffffff;
        }

        .elive-ticket-row-top {
            align-items: flex-start;
        }

        .elive-ticket-name-wrap {
            min-width: 0;
        }

        .elive-ticket-name-line {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .elive-ticket-name-line h3 {
            margin: 0;
            color: #0f172a;
            font-size: 0.85rem;
            font-weight: 750;
        }

        .elive-code-chip {
            padding: 0.18rem 0.45rem;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            background: #ffffff;
            color: #64748b;
            font-size: 0.58rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .elive-ticket-name-wrap p,
        .elive-ticket-money span {
            margin: 0.28rem 0 0;
            color: #94a3b8;
            font-size: 0.68rem;
        }

        .elive-ticket-money {
            flex: 0 0 auto;
            text-align: right;
        }

        .elive-ticket-money strong {
            display: block;
            color: #0f172a;
            font-size: 0.78rem;
            font-weight: 800;
        }

        .elive-ticket-money span {
            display: block;
        }

        .elive-capacity-block {
            margin-top: 0.85rem;
        }

        .elive-capacity-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.35rem;
            color: #94a3b8;
            font-size: 0.6rem;
            font-weight: 700;
        }

        .elive-progress-track {
            height: 0.42rem;
            overflow: hidden;
            border-radius: 999px;
            background: #e8edf3;
        }

        .elive-progress-bar {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #007AB2 0%, #161943 100%);
        }

        .elive-table-wrap {
            width: 100%;
            margin-top: 0.85rem;
            overflow-x: hidden;
        }

        .elive-orders-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0 0.4rem;
            font-size: 0.76rem;
        }

        .elive-orders-table th:nth-child(1),
        .elive-orders-table td:nth-child(1) {
            width: 30%;
        }

        .elive-orders-table th:nth-child(2),
        .elive-orders-table td:nth-child(2) {
            width: 25%;
        }

        .elive-orders-table th:nth-child(3),
        .elive-orders-table td:nth-child(3) {
            width: 8%;
        }

        .elive-orders-table th:nth-child(4),
        .elive-orders-table td:nth-child(4) {
            width: 20%;
        }

        .elive-orders-table th:nth-child(5),
        .elive-orders-table td:nth-child(5) {
            width: 17%;
        }

        .elive-orders-table thead th {
            padding: 0.45rem 0.65rem;
            color: #94a3b8;
            font-size: 0.6rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .elive-orders-table tbody td {
            min-width: 0;
            padding: 0.72rem 0.65rem;
            background: #f8fafc;
            color: #475569;
            vertical-align: middle;
            border-top: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
            overflow: hidden;
        }

        .elive-orders-table tbody td:first-child {
            border-left: 1px solid #f1f5f9;
            border-radius: 0.8rem 0 0 0.8rem;
        }

        .elive-orders-table tbody td:last-child {
            border-right: 1px solid #f1f5f9;
            border-radius: 0 0.8rem 0.8rem 0;
        }

        .elive-orders-table tbody tr:hover td {
            background: #ffffff;
            border-color: #e6edf4;
        }

        .elive-center {
            text-align: center;
        }

        .elive-right {
            text-align: right;
        }

        .elive-order-number,
        .elive-buyer-name,
        .elive-amount {
            color: #0f172a;
            font-weight: 750;
        }

        .elive-order-number,
        .elive-buyer-name,
        .elive-order-meta {
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .elive-order-meta {
            margin-top: 0.25rem;
            color: #94a3b8;
            font-size: 0.62rem;
        }

        .elive-qty-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.8rem;
            height: 1.8rem;
            padding: 0 0.45rem;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            background: #ffffff;
            color: #475569;
            font-size: 0.68rem;
            font-weight: 800;
        }

        .elive-status-chip {
            display: inline-flex;
            max-width: 100%;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding: 0.32rem 0.55rem;
            border: 1px solid;
            border-radius: 999px;
            font-size: 0.62rem;
            font-weight: 800;
            line-height: 1;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .elive-amount {
            white-space: nowrap;
        }

        .elive-status-dot {
            width: 0.38rem;
            height: 0.38rem;
            border-radius: 999px;
        }

        .elive-empty-state {
            margin-top: 1rem;
            padding: 2.4rem 1rem;
            border: 1px dashed #dce5ee;
            border-radius: 1rem;
            background: #fafcff;
            text-align: center;
        }

        .elive-empty-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.8rem;
            background: #ffffff;
            color: #94a3b8;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.05);
        }

        .elive-empty-icon svg {
            width: 1.1rem;
            height: 1.1rem;
        }

        .elive-empty-state h3 {
            margin: 0.75rem 0 0;
            color: #334155;
            font-size: 0.82rem;
            font-weight: 800;
        }

        .elive-empty-state p {
            margin: 0.35rem 0 0;
            color: #94a3b8;
            font-size: 0.7rem;
        }


        @media (max-width: 1280px) {
            .elive-kpi-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .elive-main-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            .elive-filter-grid,
            .elive-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .elive-readonly-short {
                width: 100%;
            }

            .elive-orders-table th:nth-child(2),
            .elive-orders-table td:nth-child(2) {
                display: none;
            }

            .elive-orders-table th:nth-child(1),
            .elive-orders-table td:nth-child(1) {
                width: 38%;
            }

            .elive-orders-table th:nth-child(3),
            .elive-orders-table td:nth-child(3) {
                width: 10%;
            }

            .elive-orders-table th:nth-child(4),
            .elive-orders-table td:nth-child(4) {
                width: 27%;
            }

            .elive-orders-table th:nth-child(5),
            .elive-orders-table td:nth-child(5) {
                width: 25%;
            }
        }

        @media (max-width: 640px) {
            .elive-sales-shell {
                margin: -0.75rem;
                padding: 1rem 0.75rem;
            }

            .elive-page-head,
            .elive-section-head,
            .elive-ticket-row-top {
                align-items: flex-start;
                flex-direction: column;
            }

            .elive-filter-grid,
            .elive-kpi-grid {
                grid-template-columns: 1fr;
            }

            .elive-kpi-card {
                min-height: auto;
            }

            .elive-ticket-money {
                text-align: left;
            }
        }
    </style>
</x-filament-panels::page>
