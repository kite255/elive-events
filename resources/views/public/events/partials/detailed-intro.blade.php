@php
    $countdownTarget = $scheduleStart?->copy()->timezone('UTC')->toIso8601String();
    $publicTicketTypes = $event->publicTicketTypes()->get();
    $totalTicketCapacity = $publicTicketTypes->sum(fn ($type) => max(0, (int) ($type->capacity ?? 0)));
    $totalTicketsRemaining = $publicTicketTypes->sum(fn ($type) => max(0, (int) ($type->remainingCapacity() ?? 0)));
    $hasLimitedTicketCapacity = $publicTicketTypes->contains(fn ($type) => filled($type->capacity) && $type->capacity > 0);
    $overallRemaining = filled($event->capacity)
        ? max(0, (int) $event->capacity - $event->ticketsSoldCount())
        : null;
    $seatCount = $hasLimitedTicketCapacity ? $totalTicketsRemaining : $overallRemaining;
@endphp

<style>
    .public-detail-section { padding: 34px 0; }
    .public-detail-section.alt { background: #fff; }
    .public-detail-heading { margin: 0 0 20px; color: #0B1F3A; font-size: clamp(25px, 3vw, 38px); line-height: 1.15; }
    .public-detail-kicker { margin: 0 0 8px; color: #233F7E; font-size: 12px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
    .countdown-card { margin-top: -18px; position: relative; z-index: 3; padding: 26px; border: 1px solid #dfe6f1; border-radius: 22px; background: #fff; box-shadow: 0 16px 44px rgba(11,31,58,.09); text-align: center; }
    .countdown-label { margin: 0 0 18px; color: #0B1F3A; font-size: 18px; font-weight: 800; }
    .countdown-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; max-width: 680px; margin: auto; }
    .countdown-unit { padding: 15px 8px; border-radius: 16px; background: #F8FAFC; border: 1px solid #e2e8f0; }
    .countdown-number { display: block; color: #233F7E; font-size: clamp(24px, 4vw, 38px); font-weight: 900; line-height: 1; }
    .countdown-name { display: block; margin-top: 8px; color: #667085; font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .event-theme { margin: 18px auto 0; color: #E48600; font-size: clamp(18px, 2.5vw, 26px); font-style: italic; font-weight: 800; }
    .highlight-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
    .highlight-card { min-height: 132px; padding: 24px 18px; border: 1px solid #e2e8f0; border-radius: 18px; background: #fff; text-align: center; box-shadow: 0 8px 24px rgba(11,31,58,.05); }
    .highlight-value { display: block; color: #233F7E; font-size: clamp(24px, 3vw, 34px); font-weight: 900; line-height: 1.05; }
    .highlight-label { display: block; margin-top: 9px; color: #667085; font-size: 13px; font-weight: 700; }
    .detail-card-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
    .detail-card { padding: 22px; border: 1px solid #e2e8f0; border-radius: 18px; background: #fff; }
    .detail-card h3 { margin: 0 0 8px; color: #0B1F3A; font-size: 18px; }
    .detail-card p { margin: 0; color: #667085; line-height: 1.7; }
    .gallery-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
    .gallery-item { overflow: hidden; border-radius: 18px; background: #e2e8f0; aspect-ratio: 4/3; position: relative; }
    .gallery-item img { width: 100%; height: 100%; object-fit: cover; transition: transform .25s ease; }
    .gallery-item:hover img { transform: scale(1.035); }
    .gallery-caption { position: absolute; inset: auto 0 0; padding: 28px 14px 12px; color: #fff; background: linear-gradient(transparent, rgba(11,31,58,.88)); font-size: 13px; font-weight: 700; }
    .ticket-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
    .ticket-tier { display: flex; flex-direction: column; padding: 24px; border: 1px solid #dfe6f1; border-radius: 20px; background: #fff; }
    .ticket-tier h3 { margin: 0; color: #0B1F3A; font-size: 22px; }
    .ticket-price { margin: 12px 0; color: #233F7E; font-size: 28px; font-weight: 900; }
    .ticket-copy { color: #667085; line-height: 1.65; flex: 1; }
    .ticket-stock { margin: 18px 0 14px; font-size: 13px; font-weight: 800; }
    .stock-ok { color: #16A34A; } .stock-low { color: #F59E0B; } .stock-out { color: #DC2626; }
    .public-action { display: inline-flex; min-height: 46px; align-items: center; justify-content: center; padding: 0 20px; border-radius: 12px; background: #F99A12; color: #0B1F3A; font-weight: 900; }
    .public-action:hover { background: #E48600; }
    .public-action.secondary { background: #233F7E; color: #fff; }
    .public-action.secondary:hover { background: #1D356A; }
    .programme-list { display: grid; gap: 12px; }
    .programme-row { display: grid; grid-template-columns: 150px 1fr; gap: 18px; padding: 18px; border-radius: 16px; background: #fff; border: 1px solid #e2e8f0; }
    .programme-time { color: #233F7E; font-weight: 900; }
    .programme-row h3 { margin: 0 0 5px; color: #0B1F3A; font-size: 18px; }
    .programme-row p { margin: 0; color: #667085; }
    .speaker-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
    .speaker-card { overflow: hidden; border: 1px solid #e2e8f0; border-radius: 18px; background: #fff; text-align: center; }
    .speaker-photo { width: 100%; aspect-ratio: 1; object-fit: cover; background: #eef2f7; }
    .speaker-copy { padding: 16px; } .speaker-copy h3 { margin: 0; color: #0B1F3A; } .speaker-copy p { margin: 6px 0 0; color: #667085; }
    .faq-list { display: grid; gap: 12px; }
    .faq-item { border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; overflow: hidden; }
    .faq-item summary { padding: 18px 20px; color: #0B1F3A; font-weight: 800; cursor: pointer; }
    .faq-answer { padding: 0 20px 20px; color: #667085; line-height: 1.7; }
    .share-row { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 22px; }
    .share-link { padding: 10px 14px; border: 1px solid #cfd8e6; border-radius: 10px; color: #233F7E; background: #fff; font-weight: 800; cursor: pointer; }
    .final-cta { padding: 46px; border-radius: 24px; background: linear-gradient(135deg, #0B1F3A, #233F7E); color: #fff; text-align: center; }
    .final-cta h2 { margin: 0 0 12px; font-size: clamp(28px, 4vw, 44px); } .final-cta p { max-width: 760px; margin: 0 auto 24px; color: #dbe7ff; line-height: 1.7; }
    .related-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
    .related-card { padding: 20px; border: 1px solid #e2e8f0; border-radius: 18px; background: #fff; }
    .related-card h3 { margin: 0 0 8px; color: #0B1F3A; } .related-card p { margin: 0 0 15px; color: #667085; }
    @media (max-width: 900px) { .highlight-grid, .speaker-grid { grid-template-columns: repeat(2, 1fr); } .ticket-grid, .detail-card-grid, .related-grid { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 640px) { .countdown-card { padding: 20px 12px; } .countdown-grid { gap: 7px; } .countdown-unit { padding: 12px 4px; } .highlight-grid, .ticket-grid, .detail-card-grid, .gallery-grid, .speaker-grid, .related-grid { grid-template-columns: 1fr; } .programme-row { grid-template-columns: 1fr; gap: 6px; } .final-cta { padding: 32px 20px; } }
</style>

@if ($countdownTarget && ! $isPast)
    <section class="public-detail-section" aria-labelledby="countdown-heading">
        <div class="container">
            <div class="countdown-card" data-event-countdown data-starts-at="{{ $countdownTarget }}" data-ends-at="{{ $scheduleEnd?->copy()->timezone('UTC')->toIso8601String() }}">
                <p id="countdown-heading" class="countdown-label">{{ $isLive ? 'The event is happening now' : 'Revival begins in' }}</p>
                @unless ($isLive)
                    <div class="countdown-grid" aria-live="polite">
                        @foreach (['days' => 'Days', 'hours' => 'Hours', 'minutes' => 'Minutes', 'seconds' => 'Seconds'] as $unit => $label)
                            <div class="countdown-unit"><span class="countdown-number" data-countdown-{{ $unit }}>00</span><span class="countdown-name">{{ $label }}</span></div>
                        @endforeach
                    </div>
                @endunless
                @if ($event->public_theme)<p class="event-theme">“{{ $event->public_theme }}”</p>@endif
            </div>
        </div>
    </section>
@endif

@if ($event->ministry_years || $event->group_members_count || $seatCount !== null || $publicTicketTypes->isNotEmpty() || filled($event->public_highlights))
    <section class="public-detail-section" aria-labelledby="highlights-heading">
        <div class="container">
            <p class="public-detail-kicker">At a glance</p>
            <h2 id="highlights-heading" class="public-detail-heading">Event Highlights</h2>
            <div class="highlight-grid">
                @if ($event->ministry_years)<div class="highlight-card"><span class="highlight-value">{{ $event->ministry_years }}+ Years</span><span class="highlight-label">Ministry Experience</span></div>@endif
                @if ($event->group_members_count)<div class="highlight-card"><span class="highlight-value">{{ number_format($event->group_members_count) }}</span><span class="highlight-label">Group Members</span></div>@endif
                @if ($seatCount !== null)<div class="highlight-card"><span class="highlight-value">{{ number_format($seatCount) }}</span><span class="highlight-label">Seats Available</span></div>@endif
                @if ($publicTicketTypes->isNotEmpty())<div class="highlight-card"><span class="highlight-value">{{ $publicTicketTypes->count() }}</span><span class="highlight-label">Ticket {{ Str::plural('Tier', $publicTicketTypes->count()) }}</span></div>@endif
                @foreach (($event->public_highlights ?? []) as $highlight)
                    @if (filled($highlight['value'] ?? null) || filled($highlight['label'] ?? null))<div class="highlight-card"><span class="highlight-value">{{ $highlight['value'] ?? '' }}</span><span class="highlight-label">{{ $highlight['label'] ?? '' }}</span></div>@endif
                @endforeach
            </div>
        </div>
    </section>
@endif
