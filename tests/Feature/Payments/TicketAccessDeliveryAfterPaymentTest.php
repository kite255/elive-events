<?php

namespace Tests\Feature\Payments;

use App\Jobs\SendTicketAccessLinkJob;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use App\Services\Payments\PaymentFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketAccessDeliveryAfterPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function createScenario(
        ?string $email = 'buyer@example.com',
        ?string $phone = '0712345678'
    ): array {
        $organization = Organization::query()->create([
            'name' => 'Ticket Delivery Organization',
            'slug' => 'ticket-delivery-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Ticket Delivery Concert',
            'slug' => 'ticket-delivery-concert-' . uniqid(),
            'event_type' => 'concert',
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDays(7),
        ]);

        $ticketType = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'code' => 'REG',
            'price' => 1000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ELV-DEL-' . strtoupper(uniqid()),
            'buyer_name' => 'Ticket Buyer',
            'buyer_phone' => $phone,
            'buyer_email' => $email,
            'quantity' => 1,
            'subtotal' => 1000,
            'discount_amount' => 0,
            'total' => 1000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PROCESSING,
            'expires_at' => now()->addMinutes(15),
        ]);

        TicketOrderItem::query()->create([
            'ticket_order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
            'discount_amount' => 0,
            'total' => 1000,
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'reference' => 'TEST-DELIVERY-' . uniqid(),
            'amount' => 1000,
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
            'payment'
        );
    }

    public function test_successful_ticket_payment_queues_my_tickets_email(): void
    {
        Queue::fake();

        [
            'order' => $order,
            'payment' => $payment,
        ] = $this->createScenario();

        app(PaymentFulfillmentService::class)
            ->fulfill($payment);

        Queue::assertPushed(
            SendTicketAccessLinkJob::class,
            function (SendTicketAccessLinkJob $job) use ($order): bool {
                return $job->ticketOrderId === $order->id
                    && $job->channel === 'email'
                    && $job->recipient === 'buyer@example.com';
            }
        );
    }

    public function test_ticket_access_delivery_is_not_queued_twice_when_fulfillment_retries(): void
    {
        Queue::fake();

        [
            'order' => $order,
            'payment' => $payment,
        ] = $this->createScenario();

        $service = app(PaymentFulfillmentService::class);

        $service->fulfill($payment);
        $service->fulfill($payment->fresh());

        Queue::assertPushed(
            SendTicketAccessLinkJob::class,
            1
        );

        Queue::assertPushed(
            SendTicketAccessLinkJob::class,
            function (SendTicketAccessLinkJob $job) use ($order): bool {
                return $job->ticketOrderId === $order->id
                    && $job->channel === 'email'
                    && $job->recipient === 'buyer@example.com';
            }
        );
    }

    public function test_successful_ticket_payment_falls_back_to_sms_when_email_is_missing(): void
    {
        Queue::fake();

        [
            'order' => $order,
            'payment' => $payment,
        ] = $this->createScenario(
            email: null,
            phone: '0712345678'
        );

        app(PaymentFulfillmentService::class)
            ->fulfill($payment);

        Queue::assertPushed(
            SendTicketAccessLinkJob::class,
            function (SendTicketAccessLinkJob $job) use ($order): bool {
                return $job->ticketOrderId === $order->id
                    && $job->channel === 'sms'
                    && $job->recipient === '255712345678';
            }
        );
    }
}
