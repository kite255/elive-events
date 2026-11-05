<?php

namespace Tests\Feature\Tickets;

use App\Jobs\SendTicketAccessLinkJob;
use App\Models\CommunicationLog;
use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublicTicketCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function createEvent(): Event
    {
        $organization = Organization::query()->create([
            'name' => 'Checkout Organizer',
            'slug' => 'checkout-organizer-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'eLive Checkout Concert',
            'slug' => 'elive-checkout-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDays(10),
        ]);

        EventTicketSetting::query()->create([
            'event_id' => $event->id,
            'ticket_sales_enabled' => true,
            'reservation_minutes' => 15,
            'max_tickets_per_order' => 10,
            'allow_guest_checkout' => true,
        ]);

        PaymentGateway::query()->create([
            'organization_id' => null,
            'name' => 'Pesapal',
            'code' => 'pesapal',
            'is_enabled' => true,
            'is_default' => true,
            'environment' => 'sandbox',
        ]);

        return $event;
    }

    private function createTicketType(
        Event $event
    ): TicketType {
        return TicketType::query()->create([
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
            'sort_order' => 1,
        ]);
    }

    public function test_ticket_page_hides_purchase_limit_labels_but_keeps_constraints(): void
    {
        $event = $this->createEvent();

        $ticketType = $this->createTicketType(
            $event
        );

        $response = $this->get(
            route(
                'public.tickets.buy',
                [
                    'event' => $event->slug,
                ]
            )
        );

        $response
            ->assertOk()
            ->assertDontSee('Min:')
            ->assertDontSee('Max:')
            ->assertSee('min="0"', false)
            ->assertSee('max="5"', false)
            ->assertSee('data-minimum="1"', false)
            ->assertSee('data-maximum="5"', false);
    }

    public function test_ticket_hero_uses_event_date_and_reservation_settings(): void
    {
        $event = $this->createEvent();
        $event->update(['starts_at' => '2026-11-22 18:00:00']);
        $event->ticketSetting->update(['reservation_minutes' => 12]);
        $this->createTicketType($event);

        $this->get(route('public.tickets.buy', ['event' => $event->slug]))
            ->assertOk()
            ->assertSee('22 Nov 2026')
            ->assertSee('class="hero-meta-icon"', false)
            ->assertSee('class="hero-meta-item"', false)
            ->assertSee('Tickets are reserved for')
            ->assertSee('after proceeding to payment.')
            ->assertSee('12 minutes')
            ->assertDontSee('Ticketing powered by')
            ->assertDontSee('29 Nov 2026');
    }

    public function test_ticket_page_explains_contact_requirement(): void
    {
        $event = $this->createEvent();
        $this->createTicketType($event);

        $response = $this->get(
            route('public.tickets.buy', [
                'event' => $event->slug,
            ])
        );

        $response
            ->assertOk()
            ->assertSee(
                'Enter your full name, phone number, and email address'
            )
            ->assertSee('Phone number')
            ->assertSee('Email address');
    }

    public function test_all_existing_public_footers_link_to_ticket_terms(): void
    {
        $footerViews = [
            'welcome',
            'public.events.index',
            'public.events.show',
            'public.tickets.order',
            'public.tickets.show',
            'public.donations.index',
            'public.donations.show',
            'public.merchandise.index',
            'public.events.register',
            'public.events.success',
            'public.attendees.show',
            'public.event-communications.show',
            'public.payments.checkout',
        ];

        foreach ($footerViews as $view) {
            $path = resource_path('views/'.str_replace('.', '/', $view).'.blade.php');
            $this->assertFileExists($path);
            $this->assertStringContainsString(
                "route('public.terms')",
                (string) file_get_contents($path),
                $view.' must link to the Terms & Conditions page.'
            );
        }
    }

    public function test_public_ticket_terms_page_is_accessible_and_linked_from_checkout(): void
    {
        $event = $this->createEvent();
        $this->createTicketType($event);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('public.terms').'"', false);

        $this->get(route('public.events.index'))
            ->assertOk()
            ->assertSee('href="'.route('public.terms').'"', false);

        $this->get(route('public.terms'))
            ->assertOk()
            ->assertSee('Ticket Purchase Terms')
            ->assertSee('Refunds and cancellations')
            ->assertSee('ticket-purchase-2026-11-04-2')
            ->assertSee('Effective date: 4 November 2026')
            ->assertSee('Last updated: 4 November 2026')
            ->assertSee('To ask about a refund or cancellation')
            ->assertSee('href="'.route('public.terms').'"', false)
            ->assertDontSee('Draft for review.')
            ->assertDontSee('noindex, nofollow');

        $this->get(route('public.tickets.buy', ['event' => $event->slug]))
            ->assertOk()
            ->assertSee('href="'.route('public.terms').'"', false)
            ->assertSee('name="accept_terms"', false);
    }

    public function test_published_terms_are_available_at_permanent_versioned_url(): void
    {
        $version = \App\Support\TicketPurchaseTerms::CURRENT_VERSION;

        $this->get(route('public.terms.version', ['version' => $version]))
            ->assertOk()
            ->assertSee('Ticket Purchase Terms')
            ->assertSee('Refunds and cancellations')
            ->assertSee($version);

        $this->get(route('public.terms.version', ['version' => 'ticket-purchase-2026-11-04']))
            ->assertOk()
            ->assertSee('Version: ticket-purchase-2026-11-04')
            ->assertDontSee('Last updated: 4 November 2026');

        $this->get(route('public.terms.version', ['version' => 'unknown-policy']))
            ->assertNotFound();

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            \App\Support\TicketPurchaseTerms::snapshotHash()
        );
    }

    public function test_ticket_checkout_requires_explicit_terms_acceptance(): void
    {
        $event = $this->createEvent();
        $ticketType = $this->createTicketType($event);

        $this->get(route('public.tickets.buy', ['event' => $event->slug]))
            ->assertOk()
            ->assertSee('I agree to the')
            ->assertSee('Terms &amp; Conditions', false)
            ->assertSee('name="accept_terms"', false)
            ->assertSee('I agree to the')
            ->assertDontSee('Read ticket purchase conditions');

        $this->from('/events/'.$event->slug.'/tickets')
            ->post('/events/'.$event->slug.'/tickets', [
                'buyer_name' => 'Terms Buyer',
                'buyer_phone' => '255700000001',
                'buyer_email' => 'terms@example.com',
                'tickets' => [$ticketType->id => 1],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['accept_terms']);

        $this->assertDatabaseCount('ticket_orders', 0);
    }

    public function test_buyer_can_create_ticket_order_and_payment(): void
    {
        $event = $this->createEvent();

        $ticketType =
            $this->createTicketType(
                $event
            );

        $response =
            $this->post(
                '/events/'
                . $event->slug
                . '/tickets',
                [
                    'buyer_name' =>
                        'Lucas Buyer',

                    'buyer_phone' =>
                        '255700000001',

                    'buyer_email' =>
                        'buyer@example.com',

                    'accept_terms' => '1',
                    'tickets' => [
                        $ticketType->id => 2,
                    ],
                ]
            );

        $order =
            TicketOrder::query()
                ->latest('id')
                ->first();

        $this->assertNotNull(
            $order
        );

        $this->assertSame(
            'Lucas Buyer',
            $order->buyer_name
        );

        $this->assertSame(
            2,
            $order->quantity
        );

        $this->assertSame(
            'ticket-purchase-2026-11-04-2',
            data_get($order->metadata, 'purchase_terms_version')
        );
        $this->assertNotEmpty(data_get($order->metadata, 'purchase_terms_accepted_at'));

        $this->assertSame(
            \App\Support\TicketPurchaseTerms::snapshotHash(),
            data_get($order->metadata, 'purchase_terms_sha256')
        );
        $this->assertSame(
            route('public.terms.version', ['version' => \App\Support\TicketPurchaseTerms::CURRENT_VERSION]),
            data_get($order->metadata, 'purchase_terms_archive_url')
        );

        $this->assertSame(
            '100000.00',
            $order->total
        );

        $this->assertSame(
            TicketOrder::STATUS_PENDING,
            $order->status
        );

        $payment =
            Payment::query()
                ->where(
                    'ticket_order_id',
                    $order->id
                )
                ->first();

        $this->assertNotNull(
            $payment
        );

        $this->assertSame(
            Payment::STATUS_PENDING,
            $payment->status
        );

        $this->assertSame(
            '100000.00',
            $payment->amount
        );

        $response->assertRedirect(
            route(
                'payments.pay',
                $payment
            )
        );
    }

    public function test_global_selcom_default_validates_paid_ticket_phone(): void
    {
        $event = $this->createEvent();
        $ticketType = $this->createTicketType($event);

        PaymentGateway::query()->where('code', 'pesapal')->update(['is_default' => false]);
        PaymentGateway::query()->create([
            'organization_id' => null,
            'name' => 'Selcom',
            'code' => 'selcom',
            'is_enabled' => true,
            'is_default' => true,
            'environment' => 'sandbox',
        ]);

        // Format passes the generic form regex but is not a valid Tanzanian
        // mobile number; reject it before creating an order or contacting Selcom.
        $this->from('/events/'.$event->slug.'/tickets')
            ->post('/events/'.$event->slug.'/tickets', [
                'buyer_name' => 'Invalid Selcom Buyer',
                'buyer_phone' => '12345678',
                'buyer_email' => 'invalid@example.com',
                'accept_terms' => '1',
                'tickets' => [$ticketType->id => 1],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['buyer_phone']);

        $this->assertDatabaseCount('ticket_orders', 0);
    }

    public function test_checkout_rejects_phone_without_email(): void
    {
        $event = $this->createEvent();
        $ticketType = $this->createTicketType($event);

        $response = $this
            ->from('/events/'.$event->slug.'/tickets')
            ->post('/events/'.$event->slug.'/tickets', [
                'buyer_name' => 'Phone Buyer',
                'buyer_phone' => '0712345678',
                'buyer_email' => '',
                'accept_terms' => '1',
                    'tickets' => [$ticketType->id => 1],
            ]);

        $response->assertRedirect()->assertSessionHasErrors(['buyer_email']);
        $this->assertDatabaseCount('ticket_orders', 0);
    }

    public function test_checkout_rejects_email_without_phone(): void
    {
        $event = $this->createEvent();
        $ticketType = $this->createTicketType($event);

        $response = $this
            ->from('/events/'.$event->slug.'/tickets')
            ->post('/events/'.$event->slug.'/tickets', [
                'buyer_name' => 'Email Buyer',
                'buyer_phone' => '',
                'buyer_email' => 'email.buyer@example.com',
                'accept_terms' => '1',
                    'tickets' => [$ticketType->id => 1],
            ]);

        $response->assertRedirect()->assertSessionHasErrors(['buyer_phone']);
        $this->assertDatabaseCount('ticket_orders', 0);
    }

    public function test_checkout_requires_both_phone_and_email(): void
    {
        $event = $this->createEvent();
        $ticketType = $this->createTicketType($event);

        $response = $this
            ->from('/events/'.$event->slug.'/tickets')
            ->post(
                '/events/'.$event->slug.'/tickets',
                [
                    'buyer_name' => 'No Contact Buyer',
                    'buyer_phone' => '',
                    'buyer_email' => '',
                    'accept_terms' => '1',
                    'tickets' => [
                        $ticketType->id => 1,
                    ],
                ]
            );

        $response
            ->assertRedirect()
            ->assertSessionHasErrors([
                'buyer_phone',
                'buyer_email',
            ]);

        $this->assertDatabaseCount('ticket_orders', 0);
    }

    public function test_checkout_rejects_invalid_phone_when_email_is_absent(): void
    {
        $event = $this->createEvent();
        $ticketType = $this->createTicketType($event);

        $response = $this
            ->from('/events/'.$event->slug.'/tickets')
            ->post(
                '/events/'.$event->slug.'/tickets',
                [
                    'buyer_name' => 'Invalid Phone Buyer',
                    'buyer_phone' => '12345',
                    'buyer_email' => '',
                    'accept_terms' => '1',
                    'tickets' => [
                        $ticketType->id => 1,
                    ],
                ]
            );

        $response
            ->assertRedirect()
            ->assertSessionHasErrors([
                'buyer_phone',
            ]);

        $this->assertDatabaseCount('ticket_orders', 0);
    }

    public function test_buyer_can_complete_a_free_ticket_order_without_a_gateway(): void
    {
        Queue::fake();

        config([
            'services.whatsapp.access_token' =>
                'test-token',
            'services.whatsapp.phone_number_id' =>
                '123456789',
            'services.whatsapp.templates.registration_confirmation' =>
                'event_registration_confirmation',
            'services.whatsapp.templates.ticket_access' =>
                'concert_tickets_delivery_en',
        ]);

        $event = $this->createEvent();

        PaymentGateway::query()->delete();

        $ticketType =
            $this->createTicketType(
                $event
            );

        $ticketType->update([
            'price' => 0,
        ]);

        $response =
            $this->post(
                '/events/'
                . $event->slug
                . '/tickets',
                [
                    'buyer_name' =>
                        'Free Ticket Buyer',

                    'buyer_phone' =>
                        '255700000001',

                    'buyer_email' =>
                        'free@example.com',

                    'accept_terms' => '1',
                    'tickets' => [
                        $ticketType->id => 2,
                    ],
                ]
            );

        $order =
            TicketOrder::query()
                ->latest('id')
                ->firstOrFail();

        $payment =
            Payment::query()
                ->where(
                    'ticket_order_id',
                    $order->id
                )
                ->firstOrFail();

        $this->assertSame(
            TicketOrder::STATUS_PAID,
            $order->status
        );

        $this->assertSame(
            '0.00',
            $order->total
        );

        $this->assertSame(
            Payment::STATUS_COMPLETED,
            $payment->status
        );

        $this->assertSame(
            'free',
            $payment->payment_method
        );

        $this->assertNull(
            $payment->payment_gateway_id
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

        $this->assertSame(
            [
                CommunicationLog::CHANNEL_EMAIL,
                CommunicationLog::CHANNEL_SMS,
                CommunicationLog::CHANNEL_WHATSAPP,
            ],
            CommunicationLog::query()
                ->where(
                    'ticket_order_id',
                    $order->id
                )
                ->where('purpose', CommunicationLog::PURPOSE_TICKET_ACCESS)
                ->orderBy('channel')
                ->pluck('channel')
                ->all()
        );

        Queue::assertPushed(
            SendTicketAccessLinkJob::class,
            3
        );

        $response->assertRedirect(
            route(
                'public.ticket-orders.show',
                [
                    'token' =>
                        $order->public_token,
                ]
            )
        );
    }

    public function test_checkout_requires_buyer_name(): void
    {
        $event = $this->createEvent();

        $ticketType =
            $this->createTicketType(
                $event
            );

        $response =
            $this
                ->from(
                    '/events/'
                    . $event->slug
                    . '/tickets'
                )
                ->post(
                    '/events/'
                    . $event->slug
                    . '/tickets',
                    [
                        'buyer_name' => '',
                        'buyer_phone' =>
                            '255700000001',
                        'buyer_email' =>
                            'buyer@example.com',
                        'accept_terms' => '1',
                    'tickets' => [
                            $ticketType->id => 1,
                        ],
                    ]
                );

        $response
            ->assertRedirect()
            ->assertSessionHasErrors([
                'buyer_name',
            ]);

        $this->assertDatabaseCount(
            'ticket_orders',
            0
        );
    }

    public function test_checkout_requires_at_least_one_ticket(): void
    {
        $event = $this->createEvent();

        $response =
            $this
                ->from(
                    '/events/'
                    . $event->slug
                    . '/tickets'
                )
                ->post(
                    '/events/'
                    . $event->slug
                    . '/tickets',
                    [
                        'buyer_name' =>
                            'Lucas Buyer',
                        'buyer_phone' =>
                            '255700000001',
                        'buyer_email' =>
                            'buyer@example.com',
                        'accept_terms' => '1',
                    'tickets' => [],
                    ]
                );

        $response
            ->assertRedirect()
            ->assertSessionHasErrors([
                'tickets',
            ]);

        $this->assertDatabaseCount(
            'ticket_orders',
            0
        );
    }

    public function test_checkout_rejects_ticket_from_another_event(): void
    {
        $event = $this->createEvent();

        $otherEvent =
            $this->createEvent();

        $otherTicket =
            $this->createTicketType(
                $otherEvent
            );

        $response =
            $this
                ->from(
                    '/events/'
                    . $event->slug
                    . '/tickets'
                )
                ->post(
                    '/events/'
                    . $event->slug
                    . '/tickets',
                    [
                        'buyer_name' =>
                            'Lucas Buyer',

                        'buyer_phone' =>
                            '255700000001',

                        'buyer_email' =>
                            'buyer@example.com',

                        'accept_terms' => '1',
                    'tickets' => [
                            $otherTicket->id => 1,
                        ],
                    ]
                );

        $response
            ->assertRedirect()
            ->assertSessionHasErrors([
                'tickets',
            ]);

        $this->assertDatabaseCount(
            'ticket_orders',
            0
        );
    }

    public function test_checkout_is_blocked_when_ticket_sales_are_disabled(): void
    {
        $event =
            $this->createEvent();

        $ticketType =
            $this->createTicketType(
                $event
            );

        $event
            ->ticketSetting()
            ->update([
                'ticket_sales_enabled' =>
                    false,
            ]);

        $response =
            $this->post(
                '/events/'
                . $event->slug
                . '/tickets',
                [
                    'buyer_name' =>
                        'Lucas Buyer',

                    'buyer_phone' =>
                        '255700000001',

                    'buyer_email' =>
                        'buyer@example.com',

                    'accept_terms' => '1',
                    'tickets' => [
                        $ticketType->id => 1,
                    ],
                ]
            );

        $response->assertNotFound();

        $this->assertDatabaseCount(
            'ticket_orders',
            0
        );
    }
}
