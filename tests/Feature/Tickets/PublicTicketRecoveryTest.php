<?php

namespace Tests\Feature\Tickets;

use App\Jobs\SendTicketAccessLinkJob;
use App\Models\CommunicationLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublicTicketRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(
        string $status = TicketOrder::STATUS_PAID,
        ?string $email = 'buyer@example.com',
        ?string $phone = '0712345678'
    ): TicketOrder {
        $organization = Organization::query()->create([
            'name' => 'Ticket Recovery Organization',
            'slug' => 'ticket-recovery-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Ticket Recovery Concert',
            'slug' => 'ticket-recovery-concert-' . uniqid(),
            'event_type' => 'concert',
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDays(7),
        ]);

        return TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ELV-REC-' . strtoupper(uniqid()),
            'buyer_name' => 'Recovery Buyer',
            'buyer_phone' => $phone,
            'buyer_email' => $email,
            'quantity' => 1,
            'subtotal' => 1000,
            'discount_amount' => 0,
            'total' => 1000,
            'currency' => 'TZS',
            'status' => $status,
            'paid_at' => $status === TicketOrder::STATUS_PAID
                ? now()
                : null,
        ]);
    }

    public function test_find_my_tickets_page_is_publicly_available(): void
    {
        $this->get('/events/tickets/find')
            ->assertOk()
            ->assertSee('Find My Tickets')
            ->assertSee('Order Number')
            ->assertSee('Email or Phone');
    }

    public function test_recovery_request_returns_generic_success_message(): void
    {
        Queue::fake();

        $order = $this->createOrder();

        $this->post('/events/tickets/find', [
            'order_number' => $order->order_number,
            'contact' => 'buyer@example.com',
        ])
            ->assertRedirect('/events/tickets/find')
            ->assertSessionHas(
                'status',
                'If the information matches a paid ticket order, we have sent the ticket access link to the contact used during purchase.'
            );
    }

    public function test_paid_order_with_matching_email_queues_email_access_link(): void
    {
        Queue::fake();

        $order = $this->createOrder();

        $this->post('/events/tickets/find', [
            'order_number' => $order->order_number,
            'contact' => 'BUYER@example.com',
        ])->assertRedirect('/events/tickets/find');

        Queue::assertPushed(
            SendTicketAccessLinkJob::class,
            function (SendTicketAccessLinkJob $job) use ($order): bool {
                $log = CommunicationLog::query()
                    ->find($job->communicationLogId);

                return $log?->ticket_order_id === $order->id
                    && $log->purpose
                        === CommunicationLog::PURPOSE_TICKET_ACCESS_RECOVERY
                    && $log->channel
                        === CommunicationLog::CHANNEL_EMAIL
                    && $log->recipient === 'buyer@example.com';
            }
        );
    }

    public function test_paid_order_with_matching_phone_queues_sms_access_link(): void
    {
        Queue::fake();

        $order = $this->createOrder();

        $this->post('/events/tickets/find', [
            'order_number' => $order->order_number,
            'contact' => '+255712345678',
        ])->assertRedirect('/events/tickets/find');

        Queue::assertPushed(
            SendTicketAccessLinkJob::class,
            function (SendTicketAccessLinkJob $job) use ($order): bool {
                $log = CommunicationLog::query()
                    ->find($job->communicationLogId);

                return $log?->ticket_order_id === $order->id
                    && $log->purpose
                        === CommunicationLog::PURPOSE_TICKET_ACCESS_RECOVERY
                    && $log->channel
                        === CommunicationLog::CHANNEL_SMS
                    && $log->recipient === '255712345678';
            }
        );
    }

    public function test_wrong_contact_returns_same_generic_response_and_queues_nothing(): void
    {
        Queue::fake();

        $order = $this->createOrder();

        $this->post('/events/tickets/find', [
            'order_number' => $order->order_number,
            'contact' => 'wrong@example.com',
        ])
            ->assertRedirect('/events/tickets/find')
            ->assertSessionHas(
                'status',
                'If the information matches a paid ticket order, we have sent the ticket access link to the contact used during purchase.'
            );

        Queue::assertNothingPushed();
    }

    public function test_unpaid_order_queues_nothing(): void
    {
        Queue::fake();

        $order = $this->createOrder(
            status: TicketOrder::STATUS_PROCESSING
        );

        $this->post('/events/tickets/find', [
            'order_number' => $order->order_number,
            'contact' => 'buyer@example.com',
        ])
            ->assertRedirect('/events/tickets/find')
            ->assertSessionHas('status');

        Queue::assertNothingPushed();
    }
}
