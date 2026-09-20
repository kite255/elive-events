<?php

namespace App\Http\Controllers;

use App\Models\EventCommunication;
use Illuminate\View\View;

class EventCommunicationPreviewController extends Controller
{
    public function __invoke(
        EventCommunication $communication
    ): View {
        $communication->loadMissing([
            'event.organization',
            'sections',
            'links',
            'images',
            'attachments',
        ]);

        $event = $communication->event;

        abort_unless(
            $event
            && $event->isAccessibleBy(auth()->user()),
            403
        );

        return view(
            'public.event-communications.show',
            [
                'event' => $event,
                'communication' => $communication,
                'isPreview' => true,
            ]
        );
    }
}
