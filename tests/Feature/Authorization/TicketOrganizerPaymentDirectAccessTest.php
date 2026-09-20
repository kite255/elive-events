<?php

namespace Tests\Feature\Authorization;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrganizerPaymentDirectAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_organizer_can_open_payments_list(): void
    {
        [
            $organizer,
        ] = $this->createTicketOrganizerContext();

        $this->actingAs($organizer);

        $this->get(
            PaymentResource::getUrl('index')
        )->assertOk();
    }

    public function test_ticket_organizer_can_directly_view_assigned_event_payment(): void
    {
        [
            $organizer,
            $organization,
        ] = $this->createTicketOrganizerContext();

        $event = $this->createEvent(
            $organization,
            'Assigned Organizer Concert'
        );

        $organizer->assignToEvent(
            $event,
            User::ORGANIZATION_ROLE_TICKET_ORGANIZER
        );

        $payment = $this->createPayment(
            $organization,
            $event,
            'PAY-ASSIGNED-001'
        );

        $this->actingAs($organizer);

        $this->get(
            PaymentResource::getUrl(
                'view',
                [
                    'record' => $payment,
                ]
            )
        )->assertOk();
    }

    public function test_ticket_organizer_cannot_directly_view_unassigned_same_organization_payment(): void
    {
        [
            $organizer,
            $organization,
        ] = $this->createTicketOrganizerContext();

        $event = $this->createEvent(
            $organization,
            'Unassigned Organizer Concert'
        );

        $payment = $this->createPayment(
            $organization,
            $event,
            'PAY-UNASSIGNED-001'
        );

        $this->actingAs($organizer);

        $response = $this->get(
            PaymentResource::getUrl(
                'view',
                [
                    'record' => $payment,
                ]
            )
        );

        $this->assertContains(
            $response->getStatusCode(),
            [
                403,
                404,
            ],
            'Ticket Organizer must not directly view a payment from an unassigned event.'
        );
    }

    public function test_ticket_organizer_cannot_directly_view_another_organization_payment(): void
    {
        [
            $organizer,
        ] = $this->createTicketOrganizerContext();

        $otherOrganization =
            Organization::query()->create([
                'name' =>
                    'Other Events Ltd',

                'email' =>
                    'other-events@example.com',
            ]);

        $otherEvent = $this->createEvent(
            $otherOrganization,
            'Other Organization Concert'
        );

        $otherPayment = $this->createPayment(
            $otherOrganization,
            $otherEvent,
            'PAY-OTHER-001'
        );

        $this->actingAs($organizer);

        $response = $this->get(
            PaymentResource::getUrl(
                'view',
                [
                    'record' => $otherPayment,
                ]
            )
        );

        $this->assertContains(
            $response->getStatusCode(),
            [
                403,
                404,
            ],
            'Ticket Organizer must not directly view another organization payment.'
        );
    }

    private function createTicketOrganizerContext(): array
    {
        $organization =
            Organization::query()->create([
                'name' =>
                    'Ticket Events Ltd',

                'email' =>
                    'ticket-events@example.com',
            ]);

        $organizer =
            User::query()->create([
                'name' =>
                    'Ticket Organizer',

                'email' =>
                    'ticket.organizer@example.com',

                'password' =>
                    'password',

                'is_super_admin' =>
                    false,
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

    private function createEvent(
        Organization $organization,
        string $name
    ): Event {
        return Event::query()->create([
            'organization_id' =>
                $organization->id,

            'name' =>
                $name,

            'status' =>
                Event::STATUS_ACTIVE,
        ]);
    }

    private function createPayment(
        Organization $organization,
        Event $event,
        string $reference
    ): Payment {
        return Payment::query()->create([
            'organization_id' =>
                $organization->id,

            'event_id' =>
                $event->id,

            'reference' =>
                $reference,

            'amount' =>
                50000,

            'currency' =>
                'TZS',

            'status' =>
                Payment::STATUS_COMPLETED,

            'payment_method' =>
                'card',

            'initiated_at' =>
                now(),

            'paid_at' =>
                now(),
        ]);
    }
}
