<?php

namespace App\Filament\Resources\Events\Pages;

use App\Models\Event;

class CreateEventCommunication extends EventCommunicationEditor
{
    public function mount(
        Event $event
    ): void {
        $this->mountEditor(
            $event
        );
    }
}
