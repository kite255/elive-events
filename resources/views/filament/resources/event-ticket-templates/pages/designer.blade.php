<x-filament-panels::page>
    <div data-testid="event-ticket-template-designer">
        <livewire:tickets.ticket-designer
            :template-type="\App\Services\Tickets\TicketDesignerService::TYPE_EVENT"
            :template-id="$this->getRecord()->getKey()"
            :key="'event-ticket-designer-'.$this->getRecord()->getKey()"
        />
    </div>
</x-filament-panels::page>