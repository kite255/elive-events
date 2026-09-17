<section class="designed-ticket" aria-label="Designed ticket">
    <div class="designed-ticket-toolbar">
        <div>
            <strong>{{ $ticket->ticket_number }}</strong>
            <span>{{ ucfirst($ticket->status) }}</span>
        </div>

        <a
            class="ticket-download-button"
            href="{{ route('public.tickets.download.pdf', ['token' => $ticket->public_token]) }}"
        >
            Download PDF
        </a>
    </div>

    @foreach ($renderedPages as $renderedPage)
        <article class="designed-ticket-page">
            <div class="designed-ticket-page-heading">
                <span>{{ $renderedPage->pageName }}</span>
                <a href="{{ route('public.tickets.pages.png', [
                    'token' => $ticket->public_token,
                    'pageNumber' => $renderedPage->pageNumber,
                ]) }}">
                    Download PNG
                </a>
            </div>

            <div class="designed-ticket-canvas">
                {!! $renderedPage->svg !!}
            </div>
        </article>
    @endforeach

    <p class="scan-note">
        Present the QR code at the event entrance. Do not share it publicly.
    </p>
</section>
