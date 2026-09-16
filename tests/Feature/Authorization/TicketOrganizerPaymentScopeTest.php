<?php

namespace Tests\Feature\Authorization;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrganizerPaymentScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticketing_manager_only_sees_payments_from_assigned_events(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organizer A',
            'email' => 'orga@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Manager A',
            'email' => 'organizer-a@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $organizer->organizations()->attach(
            $organization->id,
            [
                'role' => User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $assignedEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Assigned Event',
            'venue' => 'Venue A',
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $unassignedEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Unassigned Event',
            'venue' => 'Venue B',
            'starts_at' => now()->addDays(2),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $organizer->assignToEvent(
            $assignedEvent,
            User::ORGANIZATION_ROLE_TICKET_ORGANIZER
        );

        $assignedPayment = Payment::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $assignedEvent->id,
            'reference' => 'PAY-ASSIGNED-001',
            'amount' => 50000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PENDING,
        ]);

        $unassignedPayment = Payment::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $unassignedEvent->id,
            'reference' => 'PAY-UNASSIGNED-001',
            'amount' => 75000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PENDING,
        ]);

        $this->actingAs($organizer);

        $visibleIds = PaymentResource::getEloquentQuery()
            ->pluck('payments.id')
            ->all();

        $this->assertContains(
            $assignedPayment->id,
            $visibleIds
        );

        $this->assertNotContains(
            $unassignedPayment->id,
            $visibleIds
        );

        $this->assertCount(
            1,
            $visibleIds
        );
    }
}
