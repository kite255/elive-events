<?php

namespace Tests\Feature\Tickets;

use App\Jobs\SendTicketAccessLinkJob;
use App\Models\CommunicationLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Services\Tickets\TicketAccessDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketAccessDeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_whatsapp_email_and_sms_when_whatsapp_is_configured(): void
    {
        Queue::fake();
        $this->configureWhatsApp();
        $order = $this->createPaidOrder();

        app(TicketAccessDeliveryService::class)
            ->queueAutomatic($order);

        $this->assertDeliveryChannels(
            $order,
            [
                CommunicationLog::CHANNEL_EMAIL,
                CommunicationLog::CHANNEL_SMS,
                CommunicationLog::CHANNEL_WHATSAPP,
            ]
        );

        Queue::assertPushed(
            SendTicketAccessLinkJob::class,
            3
        );
    }

    public function test_it_queues_email_and_sms_when_whatsapp_is_unavailable(): void
    {
        Queue::fake();

        config([
            'services.whatsapp.access_token' => null,
            'services.whatsapp.phone_number_id' => null,
        ]);

        $order = $this->createPaidOrder();

        app(TicketAccessDeliveryService::class)
            ->queueAutomatic($order);

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

    public function test_repeated_automatic_delivery_does_not_duplicate_logs_or_jobs(): void
    {
        Queue::fake();
        $this->configureWhatsApp();
        $order = $this->createPaidOrder();
        $service = app(TicketAccessDeliveryService::class);

        $service->queueAutomatic($order);
        $service->queueAutomatic($order->fresh());

        $this->assertSame(
            3,
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
            3
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

    private function createPaidOrder(): TicketOrder
    {
        $organization = Organization::query()->create([
            'name' => 'Delivery Service Organization',
            'slug' => 'delivery-service-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Delivery Service Concert',
            'slug' => 'delivery-service-concert-' . uniqid(),
            'event_type' => 'concert',
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDay(),
        ]);

        return TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ELV-DEL-' . strtoupper(uniqid()),
            'buyer_name' => 'Ticket Buyer',
            'buyer_phone' => '0712345678',
            'buyer_email' => 'buyer@example.com',
            'quantity' => 2,
            'subtotal' => 2000,
            'discount_amount' => 0,
            'total' => 2000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
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
