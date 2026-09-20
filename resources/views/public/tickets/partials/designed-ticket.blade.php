<section class="designed-ticket" aria-label="Designed ticket">
    <div class="designed-ticket-toolbar">
        <div class="ticket-toolbar-copy">
            <span class="ticket-toolbar-label">Ticket number</span>
            <strong class="ticket-toolbar-number">{{ $ticket->ticket_number }}</strong>
        </div>

        <div class="ticket-actions">
            <a
                class="ticket-download-button"
                href="{{ route('public.tickets.pages.png', [
                    'token' => $ticket->public_token,
                    'pageNumber' => $renderedPages[0]->pageNumber,
                ]) }}"
            >
                Download PNG
            </a>
        </div>
    </div>

    @foreach ($renderedPages as $renderedPage)
        <article class="designed-ticket-page">
            <div class="designed-ticket-page-heading">
                <span>{{ $renderedPage->pageName }}</span>
                <a
                    class="page-download-link"
                    href="{{ route('public.tickets.pages.png', [
                        'token' => $ticket->public_token,
                        'pageNumber' => $renderedPage->pageNumber,
                    ]) }}"
                >
                    Download PNG
                </a>
            </div>

            <div class="designed-ticket-canvas">
                {!! $renderedPage->svg !!}
            </div>
        </article>
    @endforeach

    <h2 class="sr-only">Ticket details</h2>

    <div class="ticket-summary" aria-label="Ticket details">
        <div class="summary-item">
            <span class="summary-label">Ticket holder</span>
            <span class="summary-value">{{ $ticket->holder_name ?: $order->buyer_name }}</span>
        </div>

        <div class="summary-item">
            <span class="summary-label">Ticket type</span>
            <span class="summary-value">{{ $ticket->ticketType?->name ?? 'Event Ticket' }}</span>
        </div>

        <div class="summary-item">
            <span class="summary-label">Event date</span>
            <span class="summary-value">{{ $eventStart?->format('d M Y') ?? 'To be announced' }}</span>
        </div>

        <div class="summary-item">
            <span class="summary-label">Status</span>
            <span class="summary-value">{{ ucfirst($ticket->status) }}</span>
        </div>
    </div>

    <div class="security-note">
        <span class="security-icon" aria-hidden="true">!</span>
        <div>
            <strong>Keep this ticket private.</strong>
            The QR code is unique and should only be presented to authorized event staff at the entrance.
        </div>
    </div>
</section>
