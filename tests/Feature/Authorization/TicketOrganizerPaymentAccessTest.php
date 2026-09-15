<?php

namespace Tests\Feature\Authorization;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrganizerPaymentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_organizer_can_view_payments_but_cannot_create_edit_or_delete_them(): void
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
                'role' => User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $this->actingAs($organizer);

        $this->assertTrue(
            PaymentResource::canViewAny()
        );

        $this->assertFalse(
            PaymentResource::canCreate()
        );

        $this->assertFalse(
            PaymentResource::canEdit(
                new Payment()
            )
        );

        $this->assertFalse(
            PaymentResource::canDelete(
                new Payment()
            )
        );
    }
}