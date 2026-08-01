<?php

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Event;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class EventSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.events.widgets.event-summary-widget';

    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        /** @var Event|null $event */
        $event = $this->record;

        return [
            'event' => $event,
        ];
    }
}