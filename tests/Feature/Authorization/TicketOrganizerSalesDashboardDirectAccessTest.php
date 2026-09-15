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

        Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Organizer Concert',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $this->actingAs($organizer);

        $this->get(
            OrganizerSalesDashboard::getUrl()
        )->assertOk();
    }

    public function test_sales_dashboard_only_exposes_organizer_events(): void
    {
        [
            $organizer,
            $organizationA,
        ] = $this->createTicketOrganizerContext();

        $organizationB = Organization::query()->create([
            'name' => 'Other Organization',
            'email' => 'other@example.com',
        ]);

        $eventA = Event::query()->create([
            'organization_id' => $organizationA->id,
            'name' => 'Organizer Event',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $eventB = Event::query()->create([
            'organization_id' => $organizationB->id,
            'name' => 'Other Event',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $this->actingAs($organizer);

        $component = Livewire::test(
            OrganizerSalesDashboard::class
        );

        $options = $component
            ->instance()
            ->eventOptions();

        $this->assertArrayHasKey(
            $eventA->id,
            $options
        );

        $this->assertArrayNotHasKey(
            $eventB->id,
            $options
        );
    }

    public function test_sales_dashboard_defaults_to_an_allowed_event(): void
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

        $this->actingAs($organizer);

        Livewire::test(
            OrganizerSalesDashboard::class
        )->assertSet(
            'selectedEventId',
            $event->id
        );
    }

    public function test_ticket_organizer_cannot_force_sales_metrics_for_another_organization_event(): void
    {
        [
            $organizer,
        ] = $this->createTicketOrganizerContext();

        $otherOrganization = Organization::query()->create([
            'name' => 'Other Organization',
            'email' => 'other@example.com',
        ]);

        $otherEvent = Event::query()->create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Other Event',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $this->actingAs($organizer);

        $component = Livewire::test(
            OrganizerSalesDashboard::class
        )
            ->set(
                'selectedEventId',
                $otherEvent->id
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