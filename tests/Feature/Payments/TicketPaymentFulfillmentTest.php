<?php

namespace Tests\Feature\Payments;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use App\Services\Payments\PaymentFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketPaymentFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    private function createScenario(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Ticket Fulfillment Organization',
            'slug' => 'ticket-fulfillment-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Ticket Fulfillment Concert',
            'slug' => 'ticket-fulfillment-concert-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $ticketType = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'VIP',
            'code' => 'VIP',
            'price' => 50000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ORD-' . uniqid(),
            'buyer_name' => 'Ticket Buyer',
            'buyer_phone' => '255700000001',
            'buyer_email' => 'buyer@example.com',
            'quantity' => 2,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total' => 100000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PROCESSING,
            'expires_at' => now()->addMinutes(15),
        ]);

        $item = TicketOrderItem::query()->create([
            'ticket_order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 2,
            'unit_price' => 50000,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total' => 100000,
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'reference' => 'TEST-TICKET-PAY-' . uniqid(),
            'amount' => 100000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
            'initiated_at' => now()->subMinute(),
            'paid_at' => now(),
        ]);

        return compact(
            'organization',
            'event',
            'ticketType',
            'order',
            'item',
            'payment'
        );
    }

    public function test_completed_ticket_payment_marks_order_paid_and_issues_tickets(): void
    {
        [
            'order' => $order,
            'payment' => $payment,
        ] = $this->createScenario();

        app(PaymentFulfillmentService::class)
            ->fulfill($payment);

        $order->refresh();
        $payment->refresh();

        $this->assertSame(
            TicketOrder::STATUS_PAID,
            $order->status
        );

        $this->assertNotNull(
            $order->paid_at
        );

        $this->assertNotNull(
            $payment->fulfilled_at
        );

        $this->assertSame(
            2,
            Ticket::query()
                ->where(
                    'ticket_order_id',
                    $order->id
                )
                ->count()
        );
    }

    public function test_ticket_payment_fulfillment_is_idempotent(): void
    {
        [
            'order' => $order,
            'payment' => $payment,
        ] = $this->createScenario();

        $service =
            app(PaymentFulfillmentService::class);

        $service->fulfill($payment);

        $service->fulfill(
            $payment->fresh()
        );

        $this->assertSame(
            2,
            Ticket::query()
                ->where(
                    'ticket_order_id',
                    $order->id
                )
                ->count()
        );

        $this->assertNotNull(
            $payment->fresh()->fulfilled_at
        );
    }

    public function test_incomplete_payment_cannot_be_fulfilled(): void
    {
        [
            'payment' => $payment,
        ] = $this->createScenario();

        $payment->update([
            'status' => Payment::STATUS_PROCESSING,
            'paid_at' => null,
        ]);

        $this->expectException(
            \RuntimeException::class
        );

        app(PaymentFulfillmentService::class)
            ->fulfill(
                $payment->fresh()
            );
    }

    public function test_expired_ticket_order_cannot_be_fulfilled_when_capacity_is_no_longer_available(): void
    {
        [
            'event' => $event,
            'ticketType' => $ticketType,
            'order' => $expiredOrder,
            'payment' => $payment,
        ] = $this->createScenario();

        /*
         * Original reservation expired.
         */
        $expiredOrder->update([
            'status' => TicketOrder::STATUS_EXPIRED,
            'expires_at' => now()->subMinute(),
        ]);

        /*
         * Make this ticket type have capacity for only
         * the two tickets from this order.
         */
        $ticketType->update([
            'capacity' => 2,
        ]);

        /*
         * Another valid paid order has already consumed
         * the inventory after the first reservation expired.
         */
        $replacementOrder = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ORD-REPLACEMENT-' . uniqid(),
            'buyer_name' => 'Replacement Buyer',
            'buyer_phone' => '255700000999',
            'buyer_email' => 'replacement@example.com',
            'quantity' => 2,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total' => 100000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $replacementItem = TicketOrderItem::query()->create([
            'ticket_order_id' => $replacementOrder->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 2,
            'unit_price' => 50000,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total' => 100000,
        ]);

        for ($i = 1; $i <= 2; $i++) {
            Ticket::query()->create([
                'event_id' => $event->id,
                'ticket_order_id' => $replacementOrder->id,
                'ticket_order_item_id' => $replacementItem->id,
                'ticket_type_id' => $ticketType->id,
                'ticket_number' => 'REPLACEMENT-' . uniqid() . '-' . $i,
                'public_token' => \Illuminate\Support\Str::random(40),
                'qr_token_hash' => hash(
                    'sha256',
                    \Illuminate\Support\Str::random(64)
                ),
                'holder_name' => 'Replacement Buyer',
                'price' => 50000,
                'currency' => 'TZS',
                'status' => Ticket::STATUS_ISSUED,
                'issued_at' => now(),
            ]);
        }

        $this->expectException(
            \RuntimeException::class
        );

        app(PaymentFulfillmentService::class)
            ->fulfill(
                $payment->fresh()
            );

        $this->assertSame(
            0,
            Ticket::query()
                ->where(
                    'ticket_order_id',
                    $expiredOrder->id
                )
                ->count()
        );
    }
}
