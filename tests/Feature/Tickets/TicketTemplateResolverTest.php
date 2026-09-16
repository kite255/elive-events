<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\EventTicketTemplate;
use App\Models\Organization;
use App\Models\OrganizationTicketTemplate;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\Tickets\TicketTemplateResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTemplateResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_type_specific_event_template_has_highest_priority(): void
    {
        $organization = $this->createOrganization(
            'Priority Organization'
        );

        $event = $this->createEvent(
            $organization,
            'Priority Event'
        );

        $ticketType = $this->createTicketType(
            $event,
            'VIP',
            'VIP'
        );

        $eventDefault = EventTicketTemplate::query()->create([
            'event_id' => $event->id,
            'ticket_type_id' => null,
            'name' => 'Event Default',
            'is_active' => true,
            'is_default' => true,
        ]);

        $ticketTypeTemplate = EventTicketTemplate::query()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'name' => 'VIP Template',
            'is_active' => true,
            'is_default' => false,
        ]);

        $ticket = new Ticket([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
        ]);

        $result = app(TicketTemplateResolver::class)
            ->resolve($ticket);

        $this->assertSame(
            'event_ticket_type',
            $result->source
        );

        $this->assertTrue(
            $result->template->is($ticketTypeTemplate)
        );

        $this->assertFalse(
            $result->template->is($eventDefault)
        );
    }

    public function test_inactive_ticket_type_template_is_ignored(): void
    {
        $organization = $this->createOrganization(
            'Inactive Organization'
        );

        $event = $this->createEvent(
            $organization,
            'Inactive Template Event'
        );

        $ticketType = $this->createTicketType(
            $event,
            'VIP',
            'VIP-INACTIVE'
        );

        EventTicketTemplate::query()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'name' => 'Inactive VIP',
            'is_active' => false,
            'is_default' => false,
        ]);

        $eventDefault = EventTicketTemplate::query()->create([
            'event_id' => $event->id,
            'ticket_type_id' => null,
            'name' => 'Event Default',
            'is_active' => true,
            'is_default' => true,
        ]);

        $ticket = new Ticket([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
        ]);

        $result = app(TicketTemplateResolver::class)
            ->resolve($ticket);

        $this->assertSame(
            'event_default',
            $result->source
        );

        $this->assertTrue(
            $result->template->is($eventDefault)
        );
    }

    public function test_event_default_is_used_when_no_ticket_type_template_exists(): void
    {
        $organization = $this->createOrganization(
            'Event Default Organization'
        );

        $event = $this->createEvent(
            $organization,
            'Event Default Event'
        );

        $ticketType = $this->createTicketType(
            $event,
            'Regular',
            'REGULAR'
        );

        $eventDefault = EventTicketTemplate::query()->create([
            'event_id' => $event->id,
            'ticket_type_id' => null,
            'name' => 'Event Default',
            'is_active' => true,
            'is_default' => true,
        ]);

        $ticket = new Ticket([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
        ]);

        $result = app(TicketTemplateResolver::class)
            ->resolve($ticket);

        $this->assertSame(
            'event_default',
            $result->source
        );

        $this->assertTrue(
            $result->template->is($eventDefault)
        );
    }

    public function test_organization_default_is_used_when_event_has_no_template(): void
    {
        $organization = $this->createOrganization(
            'Organization Default Organization'
        );

        $event = $this->createEvent(
            $organization,
            'Organization Default Event'
        );

        $ticketType = $this->createTicketType(
            $event,
            'Delegate',
            'DELEGATE'
        );

        $organizationDefault =
            OrganizationTicketTemplate::query()->create([
                'organization_id' => $organization->id,
                'name' => 'Organization Default',
                'is_active' => true,
                'is_default' => true,
            ]);

        $ticket = new Ticket([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
        ]);

        $result = app(TicketTemplateResolver::class)
            ->resolve($ticket);

        $this->assertSame(
            'organization_default',
            $result->source
        );

        $this->assertTrue(
            $result->template->is($organizationDefault)
        );
    }

    public function test_standard_fallback_is_returned_when_no_template_exists(): void
    {
        $organization = $this->createOrganization(
            'Fallback Organization'
        );

        $event = $this->createEvent(
            $organization,
            'Fallback Event'
        );

        $ticketType = $this->createTicketType(
            $event,
            'Standard',
            'STANDARD'
        );

        $ticket = new Ticket([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
        ]);

        $result = app(TicketTemplateResolver::class)
            ->resolve($ticket);

        $this->assertSame(
            'standard',
            $result->source
        );

        $this->assertNull(
            $result->template
        );
    }

    public function test_template_from_another_event_or_organization_is_never_returned(): void
    {
        $organizationA = $this->createOrganization(
            'Organization A'
        );

        $organizationB = $this->createOrganization(
            'Organization B'
        );

        $eventA = $this->createEvent(
            $organizationA,
            'Event A'
        );

        $eventB = $this->createEvent(
            $organizationB,
            'Event B'
        );

        $ticketTypeA = $this->createTicketType(
            $eventA,
            'VIP A',
            'VIP-A'
        );

        EventTicketTemplate::query()->create([
            'event_id' => $eventB->id,
            'ticket_type_id' => null,
            'name' => 'Wrong Event',
            'is_active' => true,
            'is_default' => true,
        ]);

        OrganizationTicketTemplate::query()->create([
            'organization_id' => $organizationB->id,
            'name' => 'Wrong Organization',
            'is_active' => true,
            'is_default' => true,
        ]);

        $ticket = new Ticket([
            'event_id' => $eventA->id,
            'ticket_type_id' => $ticketTypeA->id,
        ]);

        $result = app(TicketTemplateResolver::class)
            ->resolve($ticket);

        $this->assertSame(
            'standard',
            $result->source
        );

        $this->assertNull(
            $result->template
        );
    }

    private function createOrganization(
        string $name
    ): Organization {
        return Organization::query()->create([
            'name' => $name,
        ]);
    }

    private function createEvent(
        Organization $organization,
        string $name
    ): Event {
        return Event::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
        ]);
    }

    private function createTicketType(
        Event $event,
        string $name,
        string $code
    ): TicketType {
        return TicketType::query()->create([
            'event_id' => $event->id,
            'name' => $name,
            'code' => $code,
            'price' => 10000,
            'currency' => 'TZS',
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
            'requires_holder_details' => true,
            'sort_order' => 1,
        ]);
    }
}
