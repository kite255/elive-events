<?php

namespace Tests\Feature\Payments;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\TicketOrder;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function createScenario(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Ticket Payment Organization',
            'slug' => 'ticket-payment-org-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Ticket Payment Concert',
            'slug' => 'ticket-payment-concert-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $gateway = PaymentGateway::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Pesapal',
            'code' => 'pesapal',
            'is_enabled' => true,
            'is_default' => true,
            'environment' => 'sandbox',
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
            'status' => TicketOrder::STATUS_PENDING,
            'expires_at' => now()->addMinutes(15),
        ]);

        return compact(
            'organization',
            'event',
            'gateway',
            'order'
        );
    }

    public function test_it_creates_payment_for_ticket_order(): void
    {
        [
            'organization' => $organization,
            'event' => $event,
            'gateway' => $gateway,
            'order' => $order,
        ] = $this->createScenario();

        $payment = app(PaymentService::class)
            ->createForTicketOrder($order);

        $this->assertSame(
            $organization->id,
            $payment->organization_id
        );

        $this->assertSame(
            $event->id,
            $payment->event_id
        );

        $this->assertSame(
            $order->id,
            $payment->ticket_order_id
        );

        $this->assertNull(
            $payment->attendee_id
        );

        $this->assertSame(
            $gateway->id,
            $payment->payment_gateway_id
        );

        $this->assertSame(
            '100000.00',
            $payment->amount
        );

        $this->assertSame(
            'TZS',
            $payment->currency
        );

        $this->assertSame(
            Payment::STATUS_PENDING,
            $payment->status
        );
    }

    public function test_it_reuses_existing_pending_ticket_payment(): void
    {
        [
            'order' => $order,
        ] = $this->createScenario();

        $service = app(PaymentService::class);

        $first = $service->createForTicketOrder($order);
        $second = $service->createForTicketOrder($order);

        $this->assertTrue(
            $first->is($second)
        );

        $this->assertSame(
            1,
            Payment::query()
                ->where(
                    'ticket_order_id',
                    $order->id
                )
                ->count()
        );
    }
}
