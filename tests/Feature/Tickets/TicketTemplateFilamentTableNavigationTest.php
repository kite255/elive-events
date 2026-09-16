<?php

namespace Tests\Feature\Tickets;

use App\Filament\Resources\EventTicketTemplates\EventTicketTemplateResource;
use App\Filament\Resources\EventTicketTemplates\Pages\ListEventTicketTemplates;
use App\Filament\Resources\OrganizationTicketTemplates\OrganizationTicketTemplateResource;
use App\Filament\Resources\OrganizationTicketTemplates\Pages\ListOrganizationTicketTemplates;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketTemplateFilamentTableNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_template_table_has_expected_columns(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(
            ListOrganizationTicketTemplates::class
        )
            ->assertTableColumnExists('name')
            ->assertTableColumnExists('organization.name')
            ->assertTableColumnExists('width')
            ->assertTableColumnExists('height')
            ->assertTableColumnExists('is_active')
            ->assertTableColumnExists('is_default');
    }

    public function test_event_template_table_has_expected_columns(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(
            ListEventTicketTemplates::class
        )
            ->assertTableColumnExists('name')
            ->assertTableColumnExists('event.name')
            ->assertTableColumnExists('ticketType.name')
            ->assertTableColumnExists('width')
            ->assertTableColumnExists('height')
            ->assertTableColumnExists('is_active')
            ->assertTableColumnExists('is_default');
    }

    public function test_resources_have_expected_navigation_labels_and_group(): void
    {
        $this->assertSame(
            'Ticket Template Library',
            OrganizationTicketTemplateResource::getNavigationLabel()
        );

        $this->assertSame(
            'Event Ticket Templates',
            EventTicketTemplateResource::getNavigationLabel()
        );

        $this->assertSame(
            'Ticketing',
            OrganizationTicketTemplateResource::getNavigationGroup()
        );

        $this->assertSame(
            'Ticketing',
            EventTicketTemplateResource::getNavigationGroup()
        );
    }

    public function test_super_admin_can_register_both_navigation_items(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $this->actingAs($user);

        $this->assertTrue(
            OrganizationTicketTemplateResource::shouldRegisterNavigation()
        );

        $this->assertTrue(
            EventTicketTemplateResource::shouldRegisterNavigation()
        );
    }

    public function test_assigned_event_manager_only_gets_event_template_navigation(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Event A',
        ]);

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
            OrganizationTicketTemplateResource::shouldRegisterNavigation()
        );

        $this->assertTrue(
            EventTicketTemplateResource::shouldRegisterNavigation()
        );
    }

    public function test_ticketing_manager_gets_neither_template_navigation_item(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Event A',
        ]);

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
            OrganizationTicketTemplateResource::shouldRegisterNavigation()
        );

        $this->assertFalse(
            EventTicketTemplateResource::shouldRegisterNavigation()
        );
    }
}
