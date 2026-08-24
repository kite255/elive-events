<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventCommunication;
use Illuminate\View\View;

class PublicEventCommunicationController extends Controller
{
    public function __invoke(
        Event $event,
        EventCommunication $communication
    ): View {
        abort_unless(
            (int) $communication->event_id === (int) $event->id,
            404
        );

        abort_unless(
            $communication->is_public
            && $communication->isPublished(),
            404
        );

        $event->loadMissing(
            'organization'
        );

        $communication->loadMissing([
            'sections',
            'links',
            'images',
            'attachments',
        ]);

        return view(
            'public.event-communications.show',
            compact('event', 'communication')
        );
    }
}
