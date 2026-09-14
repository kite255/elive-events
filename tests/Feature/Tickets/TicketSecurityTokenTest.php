<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Services\Tickets\TicketIssuanceService;
use App\Services\Tickets\TicketOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketSecurityTokenTest extends TestCase
{
    use RefreshDatabase;

    private function createEvent(): Event
    {
        $organization = Organization::query()->create([
            'name' => 'Secure Ticket Organizer',
            'slug' => 'secure-ticket-organizer-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Secure Ticket Event',
            'slug' => 'secure-ticket-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDays(7),
        ]);

        EventTicketSetting::query()->create([
            'event_id' => $event->id,
            'ticket_sales_enabled' => true,
            'reservation_minutes' => 15,
            'max_tickets_per_order' => 10,
            'allow_guest_checkout' => true,
        ]);

        return $event;
    }

    private function createPaidOrder(): TicketOrder
    {
        $event = $this->createEvent();

        $ticketType = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'VIP',
            'code' => 'VIP',
            'price' => 50000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 5,
            'is_active' => true,
            'is_public' => true,
        ]);

        $order = app(TicketOrderService::class)
            ->createOrder(
                event: $event,
                buyer: [
                    'name' => 'Lucas Buyer',
                    'phone' => '255700000001',
                    'email' => 'buyer@example.com',
                ],
                items: [
                    [
                        'ticket_type_id' =>
                            $ticketType->id,

                        'quantity' => 1,
                    ],
                ],
            );

        $order->update([
            'status' =>
                TicketOrder::STATUS_PAID,

            'paid_at' =>
                now(),
        ]);

        return $order->fresh();
    }

    public function test_ticket_order_receives_secure_public_token(): void
    {
        $order = $this->createPaidOrder();

        $this->assertNotEmpty(
            $order->public_token
        );

        $this->assertGreaterThanOrEqual(
            40,
            strlen(
                $order->public_token
            )
        );

        $this->assertSame(
            1,
            TicketOrder::query()
                ->where(
                    'public_token',
                    $order->public_token
                )
                ->count()
        );
    }

    public function test_issued_ticket_keeps_recoverable_encrypted_qr_secret(): void
    {
        $order = $this->createPaidOrder();

        $tickets = app(
            TicketIssuanceService::class
        )->issueForOrder(
            $order
        );

        $ticket =
            $tickets->first();

        $this->assertNotNull(
            $ticket
        );

        /*
         * The model should expose the decrypted token
         * through Laravel's encrypted cast.
         */
        $rawQrToken =
            $ticket->qr_token_encrypted;

        $this->assertNotEmpty(
            $rawQrToken
        );

        $this->assertGreaterThanOrEqual(
            64,
            strlen(
                $rawQrToken
            )
        );

        /*
         * Scanner lookup must continue to use
         * the SHA-256 hash.
         */
        $this->assertSame(
            hash(
                'sha256',
                $rawQrToken
            ),
            $ticket->qr_token_hash
        );

        /*
         * The actual database value must never equal
         * the raw QR credential.
         */
        $storedValue =
            $ticket->getRawOriginal(
                'qr_token_encrypted'
            );

        $this->assertNotEmpty(
            $storedValue
        );

        $this->assertNotSame(
            $rawQrToken,
            $storedValue
        );

        /*
         * Refreshing proves Laravel can decrypt the
         * stored value later when rendering the ticket.
         */
        $freshTicket =
            $ticket->fresh();

        $this->assertSame(
            $rawQrToken,
            $freshTicket
                ->qr_token_encrypted
        );
    }

    public function test_ticket_qr_hash_is_not_changed_after_reload(): void
    {
        $order = $this->createPaidOrder();

        $ticket =
            app(
                TicketIssuanceService::class
            )
                ->issueForOrder(
                    $order
                )
                ->first();

        $originalHash =
            $ticket->qr_token_hash;

        $freshTicket =
            $ticket->fresh();

        $this->assertSame(
            $originalHash,
            $freshTicket->qr_token_hash
        );

        $this->assertSame(
            $originalHash,
            hash(
                'sha256',
                $freshTicket
                    ->qr_token_encrypted
            )
        );
    }
}
