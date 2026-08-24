<?php

namespace App\Filament\Resources\Events\Pages;

use App\Models\Event;
use App\Models\EventCommunication;

class EditEventCommunication extends EventCommunicationEditor
{
    public function mount(
        Event $event,
        EventCommunication $communication
    ): void {
        $this->mountEditor(
            $event,
            $communication
        );
    }
}
