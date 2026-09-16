<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\EventTicketTemplate;
use App\Models\Organization;
use App\Models\OrganizationTicketTemplate;
use App\Models\User;
use App\Policies\EventTicketTemplatePolicy;
use App\Policies\OrganizationTicketTemplatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTemplateAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manage_organization_template(): void
    {
        $organization = $this->createOrganization('Organization A');

        $template = $this->createOrganizationTemplate(
            $organization
        );

        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $policy = new OrganizationTicketTemplatePolicy();

        $this->assertTrue(
            $policy->update($user, $template)
        );
    }

    public function test_organization_owner_can_manage_own_organization_template(): void
    {
        $organization = $this->createOrganization('Organization A');

        $template = $this->createOrganizationTemplate(
            $organization
        );

        $user = User::factory()->create();

        $organization->attachUser(
            $user,
            User::ORGANIZATION_ROLE_OWNER,
            true
        );

        $policy = new OrganizationTicketTemplatePolicy();

        $this->assertTrue(
            $policy->update($user, $template)
        );
    }

    public function test_organization_admin_can_manage_own_organization_template(): void
    {
        $organization = $this->createOrganization('Organization A');

        $template = $this->createOrganizationTemplate(
            $organization
        );

        $user = User::factory()->create();

        $organization->attachUser(
            $user,
            User::ORGANIZATION_ROLE_ADMIN
        );

        $policy = new OrganizationTicketTemplatePolicy();

        $this->assertTrue(
            $policy->update($user, $template)
        );
    }

    public function test_user_cannot_manage_another_organizations_template(): void
    {
        $organizationA = $this->createOrganization('Organization A');
        $organizationB = $this->createOrganization('Organization B');

        $templateB = $this->createOrganizationTemplate(
            $organizationB
        );

        $user = User::factory()->create();

        $organizationA->attachUser(
            $user,
            User::ORGANIZATION_ROLE_OWNER,
            true
        );

        $policy = new OrganizationTicketTemplatePolicy();

        $this->assertFalse(
            $policy->update($user, $templateB)
        );
    }

    public function test_ticketing_manager_cannot_manage_organization_template(): void
    {
        $organization = $this->createOrganization('Organization A');

        $template = $this->createOrganizationTemplate(
            $organization
        );

        $user = User::factory()->create();

        $organization->attachUser(
            $user,
            User::ORGANIZATION_ROLE_TICKET_ORGANIZER
        );

        $policy = new OrganizationTicketTemplatePolicy();

        $this->assertFalse(
            $policy->update($user, $template)
        );
    }

    public function test_owner_can_manage_event_template_for_own_organization(): void
    {
        $organization = $this->createOrganization('Organization A');
        $event = $this->createEvent($organization, 'Event A');
        $template = $this->createEventTemplate($event);

        $user = User::factory()->create();

        $organization->attachUser(
            $user,
            User::ORGANIZATION_ROLE_OWNER,
            true
        );

        $policy = new EventTicketTemplatePolicy();

        $this->assertTrue(
            $policy->update($user, $template)
        );
    }

    public function test_user_cannot_manage_event_template_from_another_organization(): void
    {
        $organizationA = $this->createOrganization('Organization A');
        $organizationB = $this->createOrganization('Organization B');

        $eventB = $this->createEvent(
            $organizationB,
            'Event B'
        );

        $templateB = $this->createEventTemplate(
            $eventB
        );

        $user = User::factory()->create();

        $organizationA->attachUser(
            $user,
            User::ORGANIZATION_ROLE_OWNER,
            true
        );

        $policy = new EventTicketTemplatePolicy();

        $this->assertFalse(
            $policy->update($user, $templateB)
        );
    }

    public function test_ticketing_manager_cannot_manage_event_template(): void
    {
        $organization = $this->createOrganization('Organization A');
        $event = $this->createEvent($organization, 'Event A');
        $template = $this->createEventTemplate($event);

        $user = User::factory()->create();

        $organization->attachUser(
            $user,
            User::ORGANIZATION_ROLE_TICKET_ORGANIZER
        );

        $policy = new EventTicketTemplatePolicy();

        $this->assertFalse(
            $policy->update($user, $template)
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

    private function createOrganizationTemplate(
        Organization $organization
    ): OrganizationTicketTemplate {
        return OrganizationTicketTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Reusable Ticket Template',
            'is_active' => true,
            'is_default' => false,
        ]);
    }

    private function createEventTemplate(
        Event $event
    ): EventTicketTemplate {
        return EventTicketTemplate::query()->create([
            'event_id' => $event->id,
            'name' => 'Event Ticket Template',
            'is_active' => true,
            'is_default' => true,
        ]);
    }
}
