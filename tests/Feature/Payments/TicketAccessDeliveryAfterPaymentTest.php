<?php

namespace Tests\Feature\Payments;

use App\Jobs\SendTicketAccessLinkJob;
use App\Models\CommunicationLog;
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

    public function test_successful_payment_queues_whatsapp_and_email_ticket_delivery(): void
    {
        Queue::fake();
        $this->configureWhatsApp();

        [
            'order' => $order,
            'payment' => $payment,
        ] = $this->createScenario();

        app(PaymentFulfillmentService::class)
            ->fulfill($payment);

        $this->assertDeliveryChannels(
            $order,
            [
                CommunicationLog::CHANNEL_EMAIL,
                CommunicationLog::CHANNEL_WHATSAPP,
            ]
        );

        Queue::assertPushed(
            SendTicketAccessLinkJob::class,
            2
        );
    }

    public function test_ticket_access_delivery_is_not_queued_twice_when_fulfillment_retries(): void
    {
        Queue::fake();
        $this->configureWhatsApp();

        [
            'order' => $order,
            'payment' => $payment,
        ] = $this->createScenario();

        $service = app(PaymentFulfillmentService::class);

        $service->fulfill($payment);
        $service->fulfill($payment->fresh());

        $this->assertSame(
            2,
            CommunicationLog::query()
                ->where('ticket_order_id', $order->id)
                ->where(
                    'purpose',
                    CommunicationLog::PURPOSE_TICKET_ACCESS
                )
                ->count()
        );

        Queue::assertPushed(
            SendTicketAccessLinkJob::class,
            2
        );
    }

    public function test_successful_payment_queues_only_whatsapp_when_email_is_missing(): void
    {
        Queue::fake();
        $this->configureWhatsApp();

        [
            'order' => $order,
            'payment' => $payment,
        ] = $this->createScenario(
            email: null
        );

        app(PaymentFulfillmentService::class)
            ->fulfill($payment);

        $this->assertDeliveryChannels(
            $order,
            [
                CommunicationLog::CHANNEL_WHATSAPP,
            ]
        );

        Queue::assertPushed(
            SendTicketAccessLinkJob::class,
            1
        );
    }

    public function test_successful_payment_uses_sms_fallback_when_whatsapp_is_unavailable(): void
    {
        Queue::fake();

        config([
            'services.whatsapp.access_token' => null,
            'services.whatsapp.phone_number_id' => null,
        ]);

        [
            'order' => $order,
            'payment' => $payment,
        ] = $this->createScenario();

        app(PaymentFulfillmentService::class)
            ->fulfill($payment);

        $this->assertDeliveryChannels(
            $order,
            [
                CommunicationLog::CHANNEL_EMAIL,
                CommunicationLog::CHANNEL_SMS,
            ]
        );

        Queue::assertPushed(
            SendTicketAccessLinkJob::class,
            2
        );
    }

    private function configureWhatsApp(): void
    {
        config([
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.phone_number_id' => '123456789',
            'services.whatsapp.templates.registration_confirmation' =>
                'event_registration_confirmation',
            'services.whatsapp.templates.ticket_access' =>
                'concert_tickets_delivery_en',
        ]);
    }

    /**
     * @param array<int, string> $expectedChannels
     */
    private function assertDeliveryChannels(
        TicketOrder $order,
        array $expectedChannels
    ): void {
        $actualChannels = CommunicationLog::query()
            ->where('ticket_order_id', $order->id)
            ->where(
                'purpose',
                CommunicationLog::PURPOSE_TICKET_ACCESS
            )
            ->orderBy('channel')
            ->pluck('channel')
            ->all();

        sort($expectedChannels);

        $this->assertSame(
            $expectedChannels,
            $actualChannels
        );
    }
}
