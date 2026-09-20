<x-filament-panels::page>
    <div data-testid="organization-ticket-template-designer">
        <livewire:tickets.ticket-designer
            :template-type="\App\Services\Tickets\TicketDesignerService::TYPE_ORGANIZATION"
            :template-id="$this->getRecord()->getKey()"
            :key="'organization-ticket-designer-'.$this->getRecord()->getKey()"
        />
    </div>
</x-filament-panels::page>