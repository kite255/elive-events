<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\EventTicketTemplate;
use App\Models\Organization;
use App\Models\OrganizationTicketTemplate;
use App\Models\TicketTemplateAsset;
use App\Models\TicketTemplatePage;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class TicketTemplateRelationshipsTest extends TestCase
{
    public function test_organization_exposes_ticket_templates_relationship(): void
    {
        $organization = new Organization();

        $this->assertInstanceOf(
            HasMany::class,
            $organization->ticketTemplates()
        );

        $this->assertSame(
            OrganizationTicketTemplate::class,
            $organization->ticketTemplates()->getRelated()::class
        );
    }

    public function test_event_exposes_ticket_templates_relationship(): void
    {
        $event = new Event();

        $this->assertInstanceOf(
            HasMany::class,
            $event->ticketTemplates()
        );

        $this->assertSame(
            EventTicketTemplate::class,
            $event->ticketTemplates()->getRelated()::class
        );
    }

    public function test_ticket_type_exposes_ticket_templates_relationship(): void
    {
        $ticketType = new TicketType();

        $this->assertInstanceOf(
            HasMany::class,
            $ticketType->ticketTemplates()
        );

        $this->assertSame(
            EventTicketTemplate::class,
            $ticketType->ticketTemplates()->getRelated()::class
        );
    }

    public function test_organization_ticket_template_relationships_and_casts(): void
    {
        $template = new OrganizationTicketTemplate();

        $this->assertInstanceOf(BelongsTo::class, $template->organization());
        $this->assertInstanceOf(HasMany::class, $template->pages());
        $this->assertInstanceOf(HasMany::class, $template->assets());
        $this->assertInstanceOf(HasMany::class, $template->eventTemplates());

        $template->forceFill([
            'width' => '1080',
            'height' => '1350',
            'is_active' => 1,
            'is_default' => 0,
        ]);

        $this->assertSame(1080, $template->width);
        $this->assertSame(1350, $template->height);
        $this->assertTrue($template->is_active);
        $this->assertFalse($template->is_default);
    }

    public function test_event_ticket_template_relationships_and_casts(): void
    {
        $template = new EventTicketTemplate();

        $this->assertInstanceOf(BelongsTo::class, $template->event());
        $this->assertInstanceOf(BelongsTo::class, $template->ticketType());
        $this->assertInstanceOf(
            BelongsTo::class,
            $template->sourceOrganizationTemplate()
        );
        $this->assertInstanceOf(HasMany::class, $template->pages());
        $this->assertInstanceOf(HasMany::class, $template->assets());

        $template->forceFill([
            'width' => '1080',
            'height' => '1350',
            'is_active' => 1,
            'is_default' => 0,
        ]);

        $this->assertSame(1080, $template->width);
        $this->assertSame(1350, $template->height);
        $this->assertTrue($template->is_active);
        $this->assertFalse($template->is_default);
    }

    public function test_ticket_template_page_relationships_and_casts(): void
    {
        $page = new TicketTemplatePage();

        $this->assertInstanceOf(
            BelongsTo::class,
            $page->eventTemplate()
        );

        $this->assertInstanceOf(
            BelongsTo::class,
            $page->organizationTemplate()
        );

        $page->forceFill([
            'page_number' => '2',
            'definition' => [
                'version' => 1,
                'elements' => [],
            ],
        ]);

        $this->assertSame(2, $page->page_number);
        $this->assertSame(1, $page->definition['version']);
        $this->assertSame([], $page->definition['elements']);
    }

    public function test_ticket_template_asset_relationships_and_casts(): void
    {
        $asset = new TicketTemplateAsset();

        $this->assertInstanceOf(
            BelongsTo::class,
            $asset->organization()
        );

        $this->assertInstanceOf(
            BelongsTo::class,
            $asset->eventTemplate()
        );

        $this->assertInstanceOf(
            BelongsTo::class,
            $asset->organizationTemplate()
        );

        $asset->forceFill([
            'width' => '800',
            'height' => '600',
            'metadata' => [
                'purpose' => 'sponsor',
            ],
        ]);

        $this->assertSame(800, $asset->width);
        $this->assertSame(600, $asset->height);
        $this->assertSame(
            'sponsor',
            $asset->metadata['purpose']
        );
    }
}
