<?php

namespace Tests\Feature\Tickets;

use App\Filament\Resources\EventTicketTemplates\EventTicketTemplateResource;
use App\Filament\Resources\OrganizationTicketTemplates\OrganizationTicketTemplateResource;
use App\Models\Event;
use App\Models\EventTicketTemplate;
use App\Models\Organization;
use App\Models\OrganizationTicketTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTemplateFilamentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_organization_template_library(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $this->actingAs($user);

        $this->assertTrue(
            OrganizationTicketTemplateResource::canViewAny()
        );
    }

    public function test_event_manager_cannot_access_organization_template_library(): void
    {
        [$organization, $event] =
            $this->createOrganizationAndEvent(
                'Organization A',
                'Event A'
            );

        $user = User::factory()->create();

        $organization->attachUser(
            $user,
            User::ORGANIZATION_ROLE_EVENT_MANAGER
        );

        $user->assignToEvent(
            $event,
            User::ORGANIZATION_ROLE_EVENT_MANAGER
        );

        $this->actingAs($user);

        $this->assertFalse(
            OrganizationTicketTemplateResource::canViewAny()
        );
    }

    public function test_organization_owner_cannot_access_organization_template_library(): void
    {
        $organization = $this->createOrganization(
            'Organization A'
        );

        $user = User::factory()->create();

        $organization->attachUser(
            $user,
            User::ORGANIZATION_ROLE_OWNER,
            true
        );

        $this->actingAs($user);

        $this->assertFalse(
            OrganizationTicketTemplateResource::canViewAny()
        );
    }

    public function test_super_admin_sees_all_event_ticket_templates(): void
    {
        [$organizationA, $eventA] =
            $this->createOrganizationAndEvent(
                'Organization A',
                'Event A'
            );

        [$organizationB, $eventB] =
            $this->createOrganizationAndEvent(
                'Organization B',
                'Event B'
            );

        $templateA = $this->createEventTemplate(
            $eventA,
            'Template A'
        );

        $templateB = $this->createEventTemplate(
            $eventB,
            'Template B'
        );

        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $this->actingAs($user);

        $ids = EventTicketTemplateResource::getEloquentQuery()
            ->pluck('id');

        $this->assertTrue(
            $ids->contains($templateA->id)
        );

        $this->assertTrue(
            $ids->contains($templateB->id)
        );
    }

    public function test_assigned_event_manager_sees_only_assigned_event_templates(): void
    {
        $organization = $this->createOrganization(
            'Organization A'
        );

        $eventA = $this->createEvent(
            $organization,
            'Event A'
        );

        $eventB = $this->createEvent(
            $organization,
            'Event B'
        );

        $templateA = $this->createEventTemplate(
            $eventA,
            'Template A'
        );

        $templateB = $this->createEventTemplate(
            $eventB,
            'Template B'
        );

        $user = User::factory()->create();

        $organization->attachUser(
            $user,
            User::ORGANIZATION_ROLE_EVENT_MANAGER
        );

        $user->assignToEvent(
            $eventA,
            User::ORGANIZATION_ROLE_EVENT_MANAGER
        );

        $this->actingAs($user);

        $this->assertTrue(
            EventTicketTemplateResource::canViewAny()
        );

        $ids = EventTicketTemplateResource::getEloquentQuery()
            ->pluck('id');

        $this->assertTrue(
            $ids->contains($templateA->id)
        );

        $this->assertFalse(
            $ids->contains($templateB->id)
        );
    }

    public function test_unassigned_event_manager_cannot_see_other_event_templates(): void
    {
        $organization = $this->createOrganization(
            'Organization A'
        );

        $eventA = $this->createEvent(
            $organization,
            'Event A'
        );

        $eventB = $this->createEvent(
            $organization,
            'Event B'
        );

        $templateB = $this->createEventTemplate(
            $eventB,
            'Template B'
        );

        $user = User::factory()->create();

        $organization->attachUser(
            $user,
            User::ORGANIZATION_ROLE_EVENT_MANAGER
        );

        $user->assignToEvent(
            $eventA,
            User::ORGANIZATION_ROLE_EVENT_MANAGER
        );

        $this->actingAs($user);

        $ids = EventTicketTemplateResource::getEloquentQuery()
            ->pluck('id');

        $this->assertFalse(
            $ids->contains($templateB->id)
        );
    }

    public function test_organization_owner_cannot_access_event_ticket_templates(): void
    {
        $organization = $this->createOrganization(
            'Organization A'
        );

        $user = User::factory()->create();

        $organization->attachUser(
            $user,
            User::ORGANIZATION_ROLE_OWNER,
            true
        );

        $this->actingAs($user);

        $this->assertFalse(
            EventTicketTemplateResource::canViewAny()
        );
    }

    public function test_ticketing_manager_cannot_access_event_ticket_templates(): void
    {
        [$organization, $event] =
            $this->createOrganizationAndEvent(
                'Organization A',
                'Event A'
            );

        $user = User::factory()->create();

        $organization->attachUser(
            $user,
            User::ORGANIZATION_ROLE_TICKET_ORGANIZER
        );

        $user->assignToEvent(
            $event,
            User::ORGANIZATION_ROLE_TICKET_ORGANIZER
        );

        $this->actingAs($user);

        $this->assertFalse(
            EventTicketTemplateResource::canViewAny()
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

    private function createOrganizationAndEvent(
        string $organizationName,
        string $eventName
    ): array {
        $organization = $this->createOrganization(
            $organizationName
        );

        $event = $this->createEvent(
            $organization,
            $eventName
        );

        return [
            $organization,
            $event,
        ];
    }

    private function createEventTemplate(
        Event $event,
        string $name
    ): EventTicketTemplate {
        return EventTicketTemplate::query()->create([
            'event_id' => $event->id,
            'name' => $name,
            'is_active' => true,
            'is_default' => true,
        ]);
    }
}
