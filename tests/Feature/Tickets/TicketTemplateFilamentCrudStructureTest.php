<?php

namespace Tests\Feature\Tickets;

use App\Filament\Resources\EventTicketTemplates\EventTicketTemplateResource;
use App\Filament\Resources\OrganizationTicketTemplates\OrganizationTicketTemplateResource;
use Tests\TestCase;

class TicketTemplateFilamentCrudStructureTest extends TestCase
{
    public function test_organization_ticket_template_resource_has_crud_pages(): void
    {
        $pages = OrganizationTicketTemplateResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }

    public function test_event_ticket_template_resource_has_crud_pages(): void
    {
        $pages = EventTicketTemplateResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }
}
