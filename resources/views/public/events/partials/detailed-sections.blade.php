@php
    use Illuminate\Support\Str;

    /*
     * Keep this partial self-contained. These values may be supplied by the
     * parent view or controller, but they are always initialized here so the
     * partial can never fail with an undefined-variable exception.
     */
    $publicTicketTypes = $publicTicketTypes
        ?? $event->publicTicketTypes()->get();

    $ticketSettings = $ticketSettings
        ?? $event->ticketSetting;

    $ticketSalesEnabled = $ticketSalesEnabled
        ?? (bool) ($ticketSettings?->ticket_sales_enabled);

    $ticketSalesOpen = $ticketSalesOpen
        ?? (
            $ticketSettings
            && $ticketSettings->salesAreOpen()
            && $publicTicketTypes->contains(
                fn ($ticketType): bool =>
                    (! $ticketType->sales_start_at
                        || $ticketType->sales_start_at->lte(now()))
                    && (! $ticketType->sales_end_at
                        || $ticketType->sales_end_at->gte(now()))
                    && ! $ticketType->isSoldOut()
            )
        );

    $ticketUrl = $ticketUrl
        ?? route('public.tickets.buy', ['event' => $event->slug]);

    $registerUrl = $registerUrl
        ?? route('public.events.register', ['event' => $event->slug]);

    $gallery = collect($event->public_gallery ?? [])->filter(fn ($item) => filled($item['image_path'] ?? null));
    $speakers = collect($event->public_speakers ?? [])->filter(fn ($item) => filled($item['name'] ?? null));
    $faqs = collect($event->public_faqs ?? [])->filter(fn ($item) => filled($item['question'] ?? null) && filled($item['answer'] ?? null));
    $sessions = $event->activeSessions()->orderBy('starts_at')->get();
    $policies = collect([
        'Dress Code' => $event->dress_code,
        'Seating Information' => $event->seating_policy,
        'Age Restrictions' => $event->age_restriction,
        'Ticket and Entry Policy' => $event->ticket_policy,
        'Cancellation / Refund Policy' => $event->refund_policy,
    ])->filter();
    $organization = $event->organization;
    $contactEmail = $event->organizer_contact_email ?: $organization?->email ?: $organization?->support_email;
    $contactPhone = $event->organizer_contact_phone ?: $organization?->phone ?: $organization?->support_phone;
    $relatedEvents = $organization
        ? $organization->events()->whereKeyNot($event->getKey())->where('status', \App\Models\Event::STATUS_ACTIVE)->where('starts_at', '>=', now())->orderBy('starts_at')->limit(3)->get()
        : collect();
    $shareUrl = route('public.events.show', ['event' => $event->slug]);
    $shareText = $event->name . ($event->public_theme ? ' — ' . $event->public_theme : '');
@endphp

@if ($gallery->isNotEmpty())
<section class="public-detail-section alt" aria-labelledby="gallery-heading"><div class="container"><p class="public-detail-kicker">Moments</p><h2 id="gallery-heading" class="public-detail-heading">Photo Gallery</h2><div class="gallery-grid">
    @foreach ($gallery as $photo)
        @php $galleryUrl = Str::startsWith($photo['image_path'], ['http://', 'https://']) ? $photo['image_path'] : asset('storage/' . ltrim($photo['image_path'], '/')); @endphp
        <a class="gallery-item" href="{{ $galleryUrl }}" target="_blank" rel="noopener"><img src="{{ $galleryUrl }}" alt="{{ $photo['caption'] ?? $event->name }}" loading="lazy">@if (filled($photo['caption'] ?? null))<span class="gallery-caption">{{ $photo['caption'] }}</span>@endif</a>
    @endforeach
</div></div></section>
@endif

@if ($publicTicketTypes->isNotEmpty())
<section id="tickets" class="public-detail-section" aria-labelledby="tickets-heading"><div class="container"><p class="public-detail-kicker">Choose your experience</p><h2 id="tickets-heading" class="public-detail-heading">Ticket Options</h2><div class="ticket-grid">
    @foreach ($publicTicketTypes as $ticketType)
        @php
            $remaining = $ticketType->remainingCapacity();
            $soldOut = $remaining !== null && $remaining <= 0;
            $lowStock = $remaining !== null && $remaining > 0 && $remaining <= max(5, (int) ceil($ticketType->capacity * .1));
            $tierSalesOpen = $ticketSalesOpen
                && (! $ticketType->sales_start_at || $ticketType->sales_start_at->lte(now()))
                && (! $ticketType->sales_end_at || $ticketType->sales_end_at->gte(now()));
        @endphp
        <article class="ticket-tier"><h3>{{ $ticketType->name }}</h3><div class="ticket-price">{{ $ticketType->price > 0 ? $ticketType->currency . ' ' . number_format((float) $ticketType->price) : 'Free' }}</div>@if ($ticketType->description)<p class="ticket-copy">{{ $ticketType->description }}</p>@endif
            <p class="ticket-stock {{ $soldOut ? 'stock-out' : ($lowStock ? 'stock-low' : 'stock-ok') }}">{{ $soldOut ? 'Sold Out' : ($remaining === null ? 'Available' : number_format($remaining) . ' remaining') }}</p>
            @if ($ticketType->sales_end_at && ! $soldOut)<p class="ticket-copy">Sales end {{ $ticketType->sales_end_at->format('d M Y, g:i A') }}</p>@endif
            @if ($tierSalesOpen && ! $soldOut)<a class="public-action" href="{{ route('public.tickets.buy', ['event' => $event->slug]) }}">Buy Tickets</a>@elseif (! $soldOut)<span class="ticket-stock stock-out">Sales Closed</span>@endif
        </article>
    @endforeach
</div></div></section>
@endif

@if ($sessions->isNotEmpty())
<section class="public-detail-section alt" aria-labelledby="programme-heading"><div class="container"><p class="public-detail-kicker">What to expect</p><h2 id="programme-heading" class="public-detail-heading">Event Programme</h2><div class="programme-list">
    @foreach ($sessions as $session)<article class="programme-row"><div class="programme-time">{{ $session->starts_at?->format('D, g:i A') ?: 'Time TBA' }}</div><div><h3>{{ $session->name }}</h3>@if ($session->description)<p>{{ $session->description }}</p>@endif @if ($session->venue_name)<p>{{ $session->venue_name }}</p>@endif</div></article>@endforeach
</div></div></section>
@endif

@if ($speakers->isNotEmpty())
<section class="public-detail-section" aria-labelledby="speakers-heading"><div class="container"><p class="public-detail-kicker">Meet the people</p><h2 id="speakers-heading" class="public-detail-heading">Speakers & Performers</h2><div class="speaker-grid">
    @foreach ($speakers as $speaker)<article class="speaker-card">@if (filled($speaker['image_path'] ?? null))@php $speakerUrl = Str::startsWith($speaker['image_path'], ['http://', 'https://']) ? $speaker['image_path'] : asset('storage/' . ltrim($speaker['image_path'], '/')); @endphp<img class="speaker-photo" src="{{ $speakerUrl }}" alt="{{ $speaker['name'] }}" loading="lazy">@endif<div class="speaker-copy"><h3>{{ $speaker['name'] }}</h3>@if (filled($speaker['role'] ?? null))<p>{{ $speaker['role'] }}</p>@endif</div></article>@endforeach
</div></div></section>
@endif

@if ($policies->isNotEmpty() || $event->venue_address || $event->map_url)
<section class="public-detail-section alt" aria-labelledby="policies-heading"><div class="container"><p class="public-detail-kicker">Plan your visit</p><h2 id="policies-heading" class="public-detail-heading">Event Information & Policies</h2><div class="detail-card-grid">
    @foreach ($policies as $title => $content)<article class="detail-card"><h3>{{ $title }}</h3><p>{!! nl2br(e($content)) !!}</p></article>@endforeach
    @if ($event->venue_address || $event->map_url)<article class="detail-card"><h3>Location</h3>@if ($event->venue_address)<p>{{ $event->venue_address }}</p>@endif @if ($event->map_url)<p style="margin-top:12px"><a class="share-link" href="{{ $event->map_url }}" target="_blank" rel="noopener">Open in Google Maps</a></p>@endif</article>@endif
</div></div></section>
@endif

@if ($faqs->isNotEmpty())
<section class="public-detail-section" aria-labelledby="faq-heading"><div class="container"><p class="public-detail-kicker">Helpful answers</p><h2 id="faq-heading" class="public-detail-heading">Common Questions</h2><div class="faq-list">
    @foreach ($faqs as $faq)<details class="faq-item"><summary>{{ $faq['question'] }}</summary><div class="faq-answer">{!! nl2br(e($faq['answer'])) !!}</div></details>@endforeach
</div></div></section>
@endif

<section class="public-detail-section alt" aria-labelledby="organizer-heading"><div class="container"><p class="public-detail-kicker">Stay connected</p><h2 id="organizer-heading" class="public-detail-heading">Organizer & Sharing</h2><div class="detail-card-grid">
    <article class="detail-card"><h3>{{ $organization?->name ?: 'Event Organizer' }}</h3>@if ($organization?->description)<p>{{ $organization->description }}</p>@endif @if ($contactEmail)<p><a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></p>@endif @if ($contactPhone)<p><a href="tel:{{ preg_replace('/\s+/', '', $contactPhone) }}">{{ $contactPhone }}</a></p>@endif</article>
    <article class="detail-card"><h3>Share this event</h3><p>Invite friends, family, and your community.</p><div class="share-row"><a class="share-link" href="https://wa.me/?text={{ rawurlencode($shareText . ' ' . $shareUrl) }}" target="_blank" rel="noopener">WhatsApp</a><a class="share-link" href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($shareUrl) }}" target="_blank" rel="noopener">Facebook</a><button class="share-link" type="button" data-copy-event-link="{{ $shareUrl }}">Copy Link</button></div></article>
    @if ($event->website_url || $event->facebook_url || $event->instagram_url || $event->youtube_url)<article class="detail-card"><h3>Follow the event</h3><div class="share-row">@foreach (['Website' => $event->website_url, 'Facebook' => $event->facebook_url, 'Instagram' => $event->instagram_url, 'YouTube' => $event->youtube_url] as $label => $url)@if ($url)<a class="share-link" href="{{ $url }}" target="_blank" rel="noopener">{{ $label }}</a>@endif @endforeach</div></article>@endif
</div></div></section>

<section class="public-detail-section"><div class="container"><div class="final-cta"><h2>{{ $event->final_cta_title ?: 'Secure your place at ' . $event->name }}</h2><p>{{ $event->final_cta_body ?: 'Join us for a memorable event and reserve your place while availability remains.' }}</p>
    @if ($ticketSalesOpen)<a class="public-action" href="{{ $ticketUrl }}">Secure Your Seat</a>@elseif ($event->registration_is_open)<a class="public-action" href="{{ $registerUrl }}">Register Now</a>@endif
</div></div></section>

@if ($relatedEvents->isNotEmpty())
<section class="public-detail-section alt" aria-labelledby="related-heading"><div class="container"><p class="public-detail-kicker">More to discover</p><h2 id="related-heading" class="public-detail-heading">Related Events</h2><div class="related-grid">@foreach ($relatedEvents as $related)<article class="related-card"><h3>{{ $related->name }}</h3><p>{{ $related->starts_at?->format('d M Y, g:i A') }}@if ($related->venue) · {{ $related->venue }}@endif</p><a class="public-action secondary" href="{{ route('public.events.show', ['event' => $related->slug]) }}">View Event</a></article>@endforeach</div></div></section>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const countdown = document.querySelector('[data-event-countdown]');
    if (countdown && countdown.querySelector('[data-countdown-days]')) {
        const target = new Date(countdown.dataset.startsAt).getTime();
        const tick = function () {
            const distance = target - Date.now();
            if (distance <= 0) { window.location.reload(); return; }
            const values = { days: Math.floor(distance / 86400000), hours: Math.floor((distance % 86400000) / 3600000), minutes: Math.floor((distance % 3600000) / 60000), seconds: Math.floor((distance % 60000) / 1000) };
            Object.entries(values).forEach(([unit, value]) => { const node = countdown.querySelector('[data-countdown-' + unit + ']'); if (node) node.textContent = String(value).padStart(2, '0'); });
        };
        tick(); window.setInterval(tick, 1000);
    }
    document.querySelectorAll('[data-copy-event-link]').forEach(function (button) { button.addEventListener('click', async function () { try { await navigator.clipboard.writeText(button.dataset.copyEventLink); button.textContent = 'Copied'; window.setTimeout(() => button.textContent = 'Copy Link', 1800); } catch (error) { window.prompt('Copy this event link:', button.dataset.copyEventLink); } }); });
});
</script>
