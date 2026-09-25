<x-filament-panels::page>
    @php
        $results = $this->results;

        $statusStyle = fn ($status) => match ($status) {
            'paid', 'issued', 'completed' => 'background:#ecfdf3;color:#027a48;border-color:#abefc6;',
            'pending', 'processing' => 'background:#fffaeb;color:#b54708;border-color:#fedf89;',
            'expired', 'failed', 'cancelled', 'refunded' => 'background:#fff1f3;color:#c01048;border-color:#fecdd6;',
            default => 'background:#f8fafc;color:#475569;border-color:#e2e8f0;',
        };

        $formatMoney = fn ($amount, $currency) => ($currency ?: 'TZS') . ' ' . number_format((float) $amount, 0);
    @endphp

    <div class="elive-lookup-shell">
        <div class="elive-lookup-wrap">
            <div class="elive-lookup-head">
                <div class="elive-lookup-kicker">Ticketing</div>
                <h1>Admin Manual Lookup</h1>
                <p>Find ticket buyers and ticket records using an order number, ticket number, buyer name, phone, or email. Eligible unused tickets can be upgraded securely or resent through available delivery channels.</p>
            </div>

            <section class="elive-lookup-panel">
                <div class="elive-search-block">
                    <label for="ticket-lookup-search">Search tickets</label>
                    <input
                        id="ticket-lookup-search"
                        type="search"
                        wire:model.live.debounce.350ms="search"
                        placeholder="Order number, ticket number, name, phone, or email"
                        autocomplete="off"
                        class="elive-lookup-input"
                    >
                    <div class="elive-search-hint">Search is restricted to events you are authorized to view.</div>
                </div>
            </section>

            @if (trim($search) === '')
                <section class="elive-empty-panel">
                    <h2>Start a lookup</h2>
                    <p>Enter an Order Number, Ticket Number, buyer name, phone number, or email address.</p>
                </section>
            @elseif (count($results) === 0)
                <section class="elive-empty-panel">
                    <h2>No matching records</h2>
                    <p>No accessible ticket or order matched "{{ $search }}".</p>
                </section>
            @else
                <div class="elive-result-summary">
                    {{ count($results) }} {{ count($results) === 1 ? 'record' : 'records' }} found
                </div>

                <div class="elive-result-list">
                    @foreach ($results as $result)
                        <article class="elive-result-card" wire:key="ticket-lookup-{{ $result['ticket_id'] }}">
                            <div class="elive-result-top">
                                <div>
                                    <div class="elive-event-name">{{ $result['event_name'] }}</div>
                                    <div class="elive-primary-id">{{ $result['ticket_number'] }}</div>
                                </div>
                                <div class="elive-ticket-type">{{ $result['ticket_type'] }}</div>
                            </div>

                            <div class="elive-detail-grid">
                                <div class="elive-detail"><span>Order Number</span><strong>{{ $result['order_number'] }}</strong></div>
                                <div class="elive-detail"><span>Ticket Number</span><strong>{{ $result['ticket_number'] }}</strong></div>
                                <div class="elive-detail"><span>Buyer</span><strong>{{ $result['buyer_name'] }}</strong></div>
                                <div class="elive-detail"><span>Phone</span><strong>{{ $result['buyer_phone'] ?: '-' }}</strong></div>
                                <div class="elive-detail"><span>Email</span><strong>{{ $result['buyer_email'] ?: '-' }}</strong></div>
                                <div class="elive-detail"><span>Current Ticket Price</span><strong>{{ $formatMoney($result['ticket_price'], $result['currency']) }}</strong></div>
                                <div class="elive-detail">
                                    <span>Payment / Order Status</span>
                                    <strong><span class="elive-status-chip" style="{{ $statusStyle($result['order_status']) }}">{{ str_replace('_', ' ', $result['order_status']) }}</span></strong>
                                </div>
                                <div class="elive-detail">
                                    <span>Ticket Status</span>
                                    <strong><span class="elive-status-chip" style="{{ $statusStyle($result['ticket_status']) }}">{{ str_replace('_', ' ', $result['ticket_status']) }}</span></strong>
                                </div>
                                <div class="elive-detail">
                                    <span>Check-in Status</span>
                                    <strong>{{ $result['is_checked_in'] ? 'Checked in' : 'Not checked in' }}</strong>
                                    @if ($result['used_at'])
                                        <small>{{ $result['used_at']->format('d M Y, H:i') }}</small>
                                    @endif
                                </div>
                            </div>

                            @if ($result['can_upgrade'])
                                <div class="elive-upgrade-box">
                                    <div>
                                        <strong>Upgrade Ticket</strong>
                                        <p>Select a higher ticket type. The system calculates the balance automatically and creates a secure customer payment link.</p>
                                    </div>
                                    <div class="elive-upgrade-controls">
                                        <select wire:model="upgradeTargets.{{ $result['ticket_id'] }}" class="elive-upgrade-select">
                                            <option value="">Select upgrade</option>
                                            @foreach ($result['upgrade_targets'] as $target)
                                                <option value="{{ $target['id'] }}">
                                                    {{ $target['name'] }} — {{ $formatMoney($target['balance'], $target['currency']) }} balance
                                                </option>
                                            @endforeach
                                        </select>
                                        <button
                                            type="button"
                                            wire:click="createUpgrade({{ $result['ticket_id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="createUpgrade({{ $result['ticket_id'] }})"
                                            class="elive-upgrade-button"
                                        >
                                            Create Upgrade Link
                                        </button>
                                    </div>

                                    @if (! empty($upgradeLinks[$result['ticket_id']]))
                                        <div class="elive-upgrade-link">
                                            <span>Secure customer link</span>
                                            <a href="{{ $upgradeLinks[$result['ticket_id']] }}" target="_blank" rel="noopener noreferrer">
                                                {{ $upgradeLinks[$result['ticket_id']] }}
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @elseif ($result['is_checked_in'])
                                <div class="elive-upgrade-blocked">This ticket has already been checked in and can no longer be upgraded.</div>
                            @endif

                            @if ($result['can_resend'])
                                <div class="elive-resend-box">
                                    <div>
                                        <strong>Resend Ticket Access</strong>
                                        <p>Select one or more available channels. This resends the existing secure ticket access and does not issue a new ticket or QR code.</p>
                                    </div>

                                    <div class="elive-resend-channels">
                                        @foreach ($result['resend_channels'] as $channel => $label)
                                            <label class="elive-resend-option">
                                                <input
                                                    type="checkbox"
                                                    value="{{ $channel }}"
                                                    wire:model="resendChannels.{{ $result['ticket_id'] }}"
                                                >
                                                <span>{{ $label }}</span>
                                            </label>
                                        @endforeach
                                    </div>

                                    <button
                                        type="button"
                                        wire:click="resendTicket({{ $result['ticket_id'] }})"
                                        wire:loading.attr="disabled"
                                        wire:target="resendTicket({{ $result['ticket_id'] }})"
                                        class="elive-resend-button"
                                    >
                                        Resend Ticket
                                    </button>
                                </div>
                            @endif

                            <div class="elive-actions">
                                @if ($result['order_url'])
                                    <a href="{{ $result['order_url'] }}" target="_blank" rel="noopener noreferrer" class="elive-action-secondary">View Order</a>
                                @endif
                                @if ($result['ticket_url'])
                                    <a href="{{ $result['ticket_url'] }}" target="_blank" rel="noopener noreferrer" class="elive-action-primary">View Ticket</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <style>
        .elive-lookup-shell{min-height:calc(100vh - 4rem);margin:-1rem;padding:1.5rem;background:#f5f8fc}.elive-lookup-wrap{width:min(100%,1280px);margin:0 auto}.elive-lookup-head{margin-bottom:1.25rem}.elive-lookup-kicker{color:#64748b;font-size:.68rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}.elive-lookup-head h1{margin:.25rem 0 0;color:#161943;font-size:clamp(1.75rem,3vw,2.25rem);font-weight:800;letter-spacing:-.03em}.elive-lookup-head p{max-width:800px;margin:.65rem 0 0;color:#64748b;font-size:.9rem;line-height:1.6}.elive-lookup-panel,.elive-empty-panel,.elive-result-card{border:1px solid #e7edf4;border-radius:1.2rem;background:#fff;box-shadow:0 10px 30px rgba(15,23,42,.055)}.elive-lookup-panel{padding:1.1rem;margin-bottom:1rem}.elive-search-block label{display:block;margin-bottom:.45rem;color:#334155;font-size:.75rem;font-weight:800}.elive-lookup-input{width:100%;min-height:3rem;padding:0 .95rem;border:1px solid #dfe7ef;border-radius:.8rem;background:#f8fafc;color:#0f172a;font-size:.88rem;outline:none}.elive-lookup-input:focus{border-color:#007ab2;background:#fff;box-shadow:0 0 0 3px rgba(0,122,178,.1)}.elive-search-hint{margin-top:.45rem;color:#94a3b8;font-size:.68rem}.elive-empty-panel{padding:2.5rem 1.25rem;text-align:center}.elive-empty-panel h2{margin:0;color:#334155;font-size:1rem;font-weight:800}.elive-empty-panel p{margin:.4rem 0 0;color:#94a3b8;font-size:.78rem}.elive-result-summary{margin:0 0 .65rem;color:#64748b;font-size:.72rem;font-weight:700}.elive-result-list{display:grid;gap:.9rem}.elive-result-card{padding:1.1rem}.elive-result-top,.elive-actions{display:flex;align-items:center;justify-content:space-between;gap:1rem}.elive-event-name{color:#64748b;font-size:.7rem;font-weight:700}.elive-primary-id{margin-top:.2rem;color:#0f172a;font-size:1rem;font-weight:800}.elive-ticket-type{padding:.42rem .65rem;border-radius:999px;background:#eef4fb;color:#161943;font-size:.68rem;font-weight:800}.elive-detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;margin-top:1rem}.elive-detail{min-width:0;padding:.75rem;border:1px solid #edf2f7;border-radius:.85rem;background:#fbfcfe}.elive-detail>span,.elive-upgrade-link>span{display:block;margin-bottom:.3rem;color:#94a3b8;font-size:.6rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase}.elive-detail strong{display:block;overflow:hidden;color:#334155;font-size:.76rem;text-overflow:ellipsis}.elive-detail small{display:block;margin-top:.25rem;color:#94a3b8;font-size:.62rem}.elive-status-chip{display:inline-flex;max-width:100%;padding:.3rem .5rem;border:1px solid;border-radius:999px;font-size:.62rem;font-weight:800;text-transform:capitalize;white-space:nowrap}.elive-upgrade-box{margin-top:1rem;padding:1rem;border:1px solid #fed7aa;border-radius:1rem;background:#fff7ed}.elive-upgrade-box strong{color:#9a3412;font-size:.82rem}.elive-upgrade-box p{margin:.25rem 0 0;color:#9a3412;font-size:.72rem;line-height:1.5}.elive-upgrade-controls{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.65rem;margin-top:.8rem}.elive-upgrade-select{min-height:2.6rem;padding:0 .7rem;border:1px solid #fdba74;border-radius:.7rem;background:#fff;color:#7c2d12;font-size:.75rem}.elive-upgrade-button{min-height:2.6rem;padding:0 .9rem;border:0;border-radius:.7rem;background:#ea580c;color:#fff;font-size:.72rem;font-weight:800;cursor:pointer}.elive-upgrade-button:disabled{opacity:.55;cursor:not-allowed}.elive-upgrade-link{margin-top:.8rem;padding:.75rem;border-radius:.7rem;background:#fff}.elive-upgrade-link a{display:block;overflow-wrap:anywhere;color:#0369a1;font-size:.72rem;font-weight:700}.elive-upgrade-blocked{margin-top:1rem;padding:.8rem;border:1px solid #fecaca;border-radius:.8rem;background:#fef2f2;color:#991b1b;font-size:.72rem;font-weight:700}.elive-resend-box{margin-top:1rem;padding:1rem;border:1px solid #bae6fd;border-radius:1rem;background:#f0f9ff}.elive-resend-box strong{color:#075985;font-size:.82rem}.elive-resend-box p{margin:.25rem 0 0;color:#0369a1;font-size:.72rem;line-height:1.5}.elive-resend-channels{display:flex;flex-wrap:wrap;gap:.55rem;margin-top:.75rem}.elive-resend-option{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .65rem;border:1px solid #bae6fd;border-radius:.65rem;background:#fff;color:#075985;font-size:.72rem;font-weight:700}.elive-resend-button{margin-top:.75rem;min-height:2.5rem;padding:0 .9rem;border:0;border-radius:.7rem;background:#0369a1;color:#fff;font-size:.72rem;font-weight:800;cursor:pointer}.elive-resend-button:disabled{opacity:.55;cursor:not-allowed}.elive-actions{justify-content:flex-end;margin-top:1rem;padding-top:1rem;border-top:1px solid #eef2f6}.elive-action-primary,.elive-action-secondary{display:inline-flex;align-items:center;justify-content:center;min-height:2.4rem;padding:0 .85rem;border-radius:.7rem;font-size:.72rem;font-weight:800;text-decoration:none}.elive-action-primary{background:#161943;color:#fff}.elive-action-secondary{border:1px solid #dfe7ef;background:#fff;color:#334155}@media(max-width:900px){.elive-detail-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:640px){.elive-lookup-shell{margin:-.75rem;padding:1rem .75rem}.elive-result-top,.elive-actions{align-items:flex-start;flex-direction:column}.elive-detail-grid{grid-template-columns:1fr}.elive-upgrade-controls{grid-template-columns:1fr}.elive-actions{align-items:stretch}.elive-action-primary,.elive-action-secondary{width:100%}}
    </style>
</x-filament-panels::page>
