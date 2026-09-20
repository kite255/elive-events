<?php

namespace Tests\Feature\Tickets;

use App\Filament\Resources\EventTicketTemplates\Pages\DesignEventTicketTemplate;
use App\Filament\Resources\OrganizationTicketTemplates\Pages\DesignOrganizationTicketTemplate;
use App\Models\Event;
use App\Models\EventTicketTemplate;
use App\Models\Organization;
use App\Models\OrganizationTicketTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketDesignerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_organization_designer(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $template = OrganizationTicketTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Organization Template',
            'width' => 1080,
            'height' => 1350,
            'is_active' => true,
            'is_default' => false,
        ]);

        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(
            DesignOrganizationTicketTemplate::class,
            [
                'record' => $template->getRouteKey(),
            ]
        )->assertOk();
    }

    public function test_event_manager_cannot_open_organization_designer(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Event A',
        ]);

        $template = OrganizationTicketTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Organization Template',
            'width' => 1080,
            'height' => 1350,
            'is_active' => true,
            'is_default' => false,
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

        $this->expectException(
            ModelNotFoundException::class
        );

        Livewire::test(
            DesignOrganizationTicketTemplate::class,
            [
                'record' => $template->getRouteKey(),
            ]
        );
    }

    public function test_organization_owner_cannot_open_organization_designer(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $template = OrganizationTicketTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Organization Template',
            'width' => 1080,
            'height' => 1350,
            'is_active' => true,
            'is_default' => false,
        ]);

        $user = User::factory()->create();

        $organization->attachUser(
            $user,
            User::ORGANIZATION_ROLE_OWNER
        );

        $this->actingAs($user);

        $this->expectException(
            ModelNotFoundException::class
        );

        Livewire::test(
            DesignOrganizationTicketTemplate::class,
            [
                'record' => $template->getRouteKey(),
            ]
        );
    }

    public function test_ticketing_manager_cannot_open_organization_designer(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $template = OrganizationTicketTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Organization Template',
            'width' => 1080,
            'height' => 1350,
            'is_active' => true,
            'is_default' => false,
        ]);

        $user = User::factory()->create();

        $organization->attachUser(
            $user,
            User::ORGANIZATION_ROLE_TICKET_ORGANIZER
        );

        $this->actingAs($user);

        $this->expectException(
            ModelNotFoundException::class
        );

        Livewire::test(
            DesignOrganizationTicketTemplate::class,
            [
                'record' => $template->getRouteKey(),
            ]
        );
    }

    public function test_super_admin_can_open_event_designer(): void
    {
        [$event, $template] =
            $this->createEventTemplate();

        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(
            DesignEventTicketTemplate::class,
            [
                'record' => $template->getRouteKey(),
            ]
        )->assertOk();
    }

    public function test_assigned_event_manager_can_open_assigned_event_designer(): void
    {
        [$event, $template, $organization] =
            $this->createEventTemplate();

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

        Livewire::test(
            DesignEventTicketTemplate::class,
            [
                'record' => $template->getRouteKey(),
            ]
        )->assertOk();
    }

    public function test_event_manager_cannot_open_unassigned_event_designer(): void
    {
        [$event, $template, $organization] =
            $this->createEventTemplate();

        $otherEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Other Event',
        ]);

        $user = User::factory()->create();

        $organization->attachUser(
            $user,
            User::ORGANIZATION_ROLE_EVENT_MANAGER
        );

        $user->assignToEvent(
            $otherEvent,
            User::ORGANIZATION_ROLE_EVENT_MANAGER
        );

        $this->actingAs($user);

        $this->expectException(
            ModelNotFoundException::class
        );

        Livewire::test(
            DesignEventTicketTemplate::class,
            [
                'record' => $template->getRouteKey(),
            ]
        );
    }

    public function test_ticketing_manager_cannot_open_event_designer(): void
    {
        [$event, $template, $organization] =
            $this->createEventTemplate();

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

        $this->expectException(
            ModelNotFoundException::class
        );

        Livewire::test(
            DesignEventTicketTemplate::class,
            [
                'record' => $template->getRouteKey(),
            ]
        );
    }

    public function test_organization_owner_cannot_open_event_designer(): void
    {
        [$event, $template, $organization] =
            $this->createEventTemplate();

        $user = User::factory()->create();

        $organization->attachUser(
            $user,
            User::ORGANIZATION_ROLE_OWNER
        );

        $this->actingAs($user);

        $this->expectException(
            ModelNotFoundException::class
        );

        Livewire::test(
            DesignEventTicketTemplate::class,
            [
                'record' => $template->getRouteKey(),
            ]
        );
    }

    private function createEventTemplate(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Organization A',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Event A',
        ]);

        $template = EventTicketTemplate::query()->create([
            'event_id' => $event->id,
            'name' => 'Event Template',
            'width' => 1080,
            'height' => 1350,
            'is_active' => true,
            'is_default' => false,
        ]);

        return [
            $event,
            $template,
            $organization,
        ];
    }
}