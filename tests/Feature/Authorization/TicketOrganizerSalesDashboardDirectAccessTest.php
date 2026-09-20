<?php

namespace Tests\Feature\Authorization;

use App\Filament\Pages\OrganizerSalesDashboard;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketOrganizerSalesDashboardDirectAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_organizer_can_directly_open_sales_dashboard(): void
    {
        [
            $organizer,
            $organization,
        ] = $this->createTicketOrganizerContext();

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Organizer Concert',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $organizer->assignToEvent(
            $event,
            User::ORGANIZATION_ROLE_TICKET_ORGANIZER
        );

        $this->actingAs($organizer);

        $this->get(
            OrganizerSalesDashboard::getUrl()
        )->assertOk();
    }

    public function test_sales_dashboard_only_exposes_assigned_events(): void
    {
        [
            $organizer,
            $organization,
        ] = $this->createTicketOrganizerContext();

        $assignedEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Assigned Event',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $unassignedEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Unassigned Event',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $organizer->assignToEvent(
            $assignedEvent,
            User::ORGANIZATION_ROLE_TICKET_ORGANIZER
        );

        $this->actingAs($organizer);

        $component = Livewire::test(
            OrganizerSalesDashboard::class
        );

        $options = $component
            ->instance()
            ->eventOptions();

        $this->assertArrayHasKey(
            $assignedEvent->id,
            $options
        );

        $this->assertArrayNotHasKey(
            $unassignedEvent->id,
            $options
        );
    }

    public function test_sales_dashboard_defaults_to_an_assigned_event(): void
    {
        [
            $organizer,
            $organization,
        ] = $this->createTicketOrganizerContext();

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Organizer Event',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $organizer->assignToEvent(
            $event,
            User::ORGANIZATION_ROLE_TICKET_ORGANIZER
        );

        $this->actingAs($organizer);

        Livewire::test(
            OrganizerSalesDashboard::class
        )->assertSet(
            'selectedEventId',
            $event->id
        );
    }

    public function test_ticket_organizer_cannot_force_sales_metrics_for_unassigned_event(): void
    {
        [
            $organizer,
            $organization,
        ] = $this->createTicketOrganizerContext();

        $unassignedEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Unassigned Event',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $this->actingAs($organizer);

        $component = Livewire::test(
            OrganizerSalesDashboard::class
        )
            ->set(
                'selectedEventId',
                $unassignedEvent->id
            );

        $metrics = $component
            ->instance()
            ->salesMetrics();

        $this->assertSame(
            0.0,
            (float) $metrics['gross_sales']
        );

        $this->assertSame(
            0,
            $metrics['paid_orders']
        );
    }

    private function createTicketOrganizerContext(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Ticket Events Ltd',
            'email' => 'ticket-events@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Organizer',
            'email' => 'ticket.organizer@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $organizer->organizations()->attach(
            $organization->id,
            [
                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    User::ORGANIZATION_STATUS_ACTIVE,

                'is_owner' =>
                    false,

                'joined_at' =>
                    now(),
            ]
        );

        return [
            $organizer,
            $organization,
        ];
    }
}