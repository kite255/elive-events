<?php

namespace Tests\Feature\Authorization;

use App\Filament\Resources\Organizers\OrganizerResource;
use Tests\TestCase;

class TicketingManagerLabelsTest extends TestCase
{
    public function test_ticket_organizer_resource_uses_ticketing_manager_labels(): void
    {
        $this->assertSame(
            'Ticketing Managers',
            OrganizerResource::getNavigationLabel()
        );

        $this->assertSame(
            'Ticketing Manager',
            OrganizerResource::getModelLabel()
        );

        $this->assertSame(
            'Ticketing Managers',
            OrganizerResource::getPluralModelLabel()
        );
    }
}
