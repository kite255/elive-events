<?php

namespace Tests\Feature\Payments;

use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Services\Tickets\TicketIssuanceService;
use App\Services\Tickets\TicketOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketPaymentStatusPageTest extends TestCase
{
    use RefreshDatabase;

    private function createCompletedTicketPayment(): Payment
    {
        $organization =
            Organization::query()->create([
                'name' =>
                    'Payment Status Organizer',

                'slug' =>
                    'payment-status-organizer-' . uniqid(),
            ]);

        $event =
            Event::query()->create([
                'organization_id' =>
                    $organization->id,

                'name' =>
                    'Payment Status Concert',

                'slug' =>
                    'payment-status-concert-' . uniqid(),

                'status' =>
                    Event::STATUS_ACTIVE,

                'starts_at' =>
                    now()->addDays(4),
            ]);

        EventTicketSetting::query()->create([
            'event_id' =>
                $event->id,

            'ticket_sales_enabled' =>
                true,

            'reservation_minutes' =>
                15,

            'max_tickets_per_order' =>
                10,

            'allow_guest_checkout' =>
                true,
        ]);

        $ticketType =
            TicketType::query()->create([
                'event_id' =>
                    $event->id,

                'name' =>
                    'VIP',

                'code' =>
                    'VIP',

                'price' =>
                    50000,

                'currency' =>
                    'TZS',

                'capacity' =>
                    100,

                'min_per_order' =>
                    1,

                'max_per_order' =>
                    5,

                'is_active' =>
                    true,

                'is_public' =>
                    true,
            ]);

        $order =
            app(
                TicketOrderService::class
            )->createOrder(
                event: $event,
                buyer: [
                    'name' =>
                        'Lucas Buyer',

                    'phone' =>
                        '255700000001',

                    'email' =>
                        'buyer@example.com',
                ],
                items: [
                    [
                        'ticket_type_id' =>
                            $ticketType->id,

                        'quantity' =>
                            1,
                    ],
                ],
            );

        $order->update([
            'status' =>
                TicketOrder::STATUS_PAID,

            'paid_at' =>
                now(),
        ]);

        app(
            TicketIssuanceService::class
        )->issueForOrder(
            $order->fresh()
        );

        return Payment::query()->create([
            'organization_id' =>
                $organization->id,

            'event_id' =>
                $event->id,

            'attendee_id' =>
                null,

            'ticket_order_id' =>
                $order->id,

            'payment_gateway_id' =>
                null,

            'reference' =>
                'ELV-PAY-STATUS-' . strtoupper(
                    uniqid()
                ),

            'amount' =>
                $order->total,

            'currency' =>
                $order->currency,

            'status' =>
                Payment::STATUS_COMPLETED,

            'paid_at' =>
                now(),

            'metadata' => [
                'purpose' =>
                    'ticket_order',
            ],
        ]);
    }

    public function test_completed_ticket_payment_shows_view_my_tickets_button(): void
    {
        $payment =
            $this->createCompletedTicketPayment();

        $order =
            $payment
                ->ticketOrder()
                ->firstOrFail();

        $response =
            $this->get(
                route(
                    'payments.status',
                    [
                        'payment' =>
                            $payment->reference,
                    ]
                )
            );

        $response
            ->assertOk()
            ->assertSee(
                'View My Tickets'
            )
            ->assertSee(
                route(
                    'public.ticket-orders.show',
                    [
                        'token' =>
                            $order->public_token,
                    ]
                ),
                false
            );
    }

    public function test_completed_ticket_payment_does_not_require_attendee(): void
    {
        $payment =
            $this->createCompletedTicketPayment();

        $this->assertNull(
            $payment->attendee_id
        );

        $response =
            $this->get(
                route(
                    'payments.status',
                    [
                        'payment' =>
                            $payment->reference,
                    ]
                )
            );

        $response->assertOk();
    }
}
