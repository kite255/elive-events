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

    public function test_ticket_organizer_only_sees_payments_from_own_organization(): void
    {
        $organizationA = Organization::query()->create([
            'name' => 'Organizer A',
            'email' => 'orga@example.com',
        ]);

        $organizationB = Organization::query()->create([
            'name' => 'Organizer B',
            'email' => 'orgb@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Organizer A',
            'email' => 'organizer-a@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $organizer->organizations()->attach(
            $organizationA->id,
            [
                'role' => User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $eventA = Event::query()->create([
            'organization_id' => $organizationA->id,
            'name' => 'Event A',
            'venue' => 'Venue A',
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $eventB = Event::query()->create([
            'organization_id' => $organizationB->id,
            'name' => 'Event B',
            'venue' => 'Venue B',
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $paymentA = Payment::query()->create([
            'organization_id' => $organizationA->id,
            'event_id' => $eventA->id,
            'reference' => 'PAY-A-001',
            'amount' => 50000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PENDING,
        ]);

        Payment::query()->create([
            'organization_id' => $organizationB->id,
            'event_id' => $eventB->id,
            'reference' => 'PAY-B-001',
            'amount' => 75000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PENDING,
        ]);

        $this->actingAs($organizer);

        $visibleIds = PaymentResource::getEloquentQuery()
            ->pluck('payments.id')
            ->all();

        $this->assertSame(
            [$paymentA->id],
            $visibleIds
        );
    }
}