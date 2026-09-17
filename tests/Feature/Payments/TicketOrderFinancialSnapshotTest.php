<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventPaymentSetting;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use App\Services\Payments\PaymentFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TicketOrderFinancialSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_completed_ticket_payment_creates_financial_snapshot(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Test Organization',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Finance Test Event',
            'event_type' => 'conference',
            'venue' => 'Test Venue',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'capacity' => 500,
            'status' => Event::STATUS_ACTIVE,
        ]);

        EventPaymentSetting::query()->create([
            'event_id' => $event->id,
            'payments_enabled' => true,
            'currency' => 'TZS',
            'registration_fee' => 0,

            'platform_commission_rate' => 7,
            'gateway_fee_rate' => 3.5,
            'gateway_fee_bearer' => 'organizer',

            'payment_required_before_confirmation' => true,
            'payment_required_before_badge' => true,
            'block_check_in_if_unpaid' => false,
            'allow_manual_payment' => false,
        ]);

        $ticketType = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'code' => 'REG',
            'description' => 'Regular admission ticket',
            'price' => 100000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addDay(),
            'is_active' => true,
            'is_public' => true,
            'requires_holder_details' => false,
            'sort_order' => 1,
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'TEST-ORDER-001',
            'buyer_name' => 'Test Buyer',
            'buyer_phone' => '255700000001',
            'buyer_email' => 'buyer1@example.com',

            'quantity' => 1,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total' => 100000,

            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PENDING,

            'expires_at' => now()->addMinutes(30),
        ]);

        TicketOrderItem::query()->create([
            'ticket_order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,

            'quantity' => 1,
            'unit_price' => 100000,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total' => 100000,
        ]);

        $gateway = PaymentGateway::query()->create([
            'name' => 'Pesapal',
            'code' => 'pesapal',
            'is_enabled' => true,
            'is_default' => true,
            'environment' => 'sandbox',
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'payment_gateway_id' => $gateway->id,

            'reference' => 'TEST-PAY-001',
            'provider_reference' => 'PROVIDER-001',
            'provider_tracking_id' => 'TRACK-001',

            'amount' => 100000,
            'currency' => 'TZS',

            'status' => Payment::STATUS_COMPLETED,

            'payment_method' => 'card',
            'description' => 'Ticket purchase',

            'initiated_at' => now()->subMinute(),
            'paid_at' => now(),
        ]);

        app(
            PaymentFulfillmentService::class
        )->fulfill(
            $payment
        );

        $order->refresh();

        $this->assertSame(
            TicketOrder::STATUS_PAID,
            $order->status
        );

        $this->assertSame(
            '100000.00',
            $order->gross_amount
        );

        $this->assertSame(
            '7.00',
            $order->platform_commission_rate
        );

        $this->assertSame(
            '7000.00',
            $order->platform_commission_amount
        );

        $this->assertSame(
            '3.50',
            $order->gateway_fee_rate
        );

        $this->assertSame(
            '3500.00',
            $order->gateway_fee_amount
        );

        $this->assertSame(
            '10500.00',
            $order->total_charges
        );

        $this->assertSame(
            '89500.00',
            $order->organizer_net_amount
        );

        $this->assertNotNull(
            $order->financial_snapshot_at
        );

        $payment->refresh();

        $this->assertNotNull(
            $payment->fulfilled_at
        );
    }

    public function test_existing_financial_snapshot_is_not_recalculated(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Second Test Organization',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Historical Finance Test Event',
            'event_type' => 'conference',
            'venue' => 'Test Venue',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'capacity' => 500,
            'status' => Event::STATUS_ACTIVE,
        ]);

        $settings = EventPaymentSetting::query()->create([
            'event_id' => $event->id,
            'payments_enabled' => true,
            'currency' => 'TZS',
            'registration_fee' => 0,

            'platform_commission_rate' => 7,
            'gateway_fee_rate' => 3.5,
            'gateway_fee_bearer' => 'organizer',

            'payment_required_before_confirmation' => true,
            'payment_required_before_badge' => true,
            'block_check_in_if_unpaid' => false,
            'allow_manual_payment' => false,
        ]);

        $ticketType = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'VIP',
            'code' => 'VIP',
            'description' => 'VIP admission ticket',
            'price' => 100000,
            'currency' => 'TZS',
            'capacity' => 50,
            'min_per_order' => 1,
            'max_per_order' => 5,
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addDay(),
            'is_active' => true,
            'is_public' => true,
            'requires_holder_details' => false,
            'sort_order' => 1,
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'TEST-ORDER-002',
            'buyer_name' => 'Historical Buyer',
            'buyer_phone' => '255700000002',
            'buyer_email' => 'buyer2@example.com',

            'quantity' => 1,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total' => 100000,

            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PENDING,

            'expires_at' => now()->addMinutes(30),
        ]);

        TicketOrderItem::query()->create([
            'ticket_order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,

            'quantity' => 1,
            'unit_price' => 100000,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total' => 100000,
        ]);

        $gateway = PaymentGateway::query()->create([
            'name' => 'Pesapal',
            'code' => 'pesapal',
            'is_enabled' => true,
            'is_default' => true,
            'environment' => 'sandbox',
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'payment_gateway_id' => $gateway->id,

            'reference' => 'TEST-PAY-002',
            'provider_reference' => 'PROVIDER-002',
            'provider_tracking_id' => 'TRACK-002',

            'amount' => 100000,
            'currency' => 'TZS',

            'status' => Payment::STATUS_COMPLETED,

            'payment_method' => 'card',
            'description' => 'Ticket purchase',

            'initiated_at' => now()->subMinute(),
            'paid_at' => now(),
        ]);

        app(
            PaymentFulfillmentService::class
        )->fulfill(
            $payment
        );

        $order->refresh();

        $originalSnapshotTime =
            $order->financial_snapshot_at
                ?->copy();

        $this->assertSame(
            '7.00',
            $order->platform_commission_rate
        );

        $this->assertSame(
            '7000.00',
            $order->platform_commission_amount
        );

        $this->assertSame(
            '10500.00',
            $order->total_charges
        );

        $this->assertSame(
            '89500.00',
            $order->organizer_net_amount
        );

        /*
         * Change future commercial terms.
         *
         * Existing paid orders must retain the rate that
         * applied when their payment was fulfilled.
         */
        $settings->update([
            'platform_commission_rate' => 5,
            'gateway_fee_rate' => 2,
        ]);

        $payment->refresh();

        /*
         * Simulate a later retry/reconciliation call.
         *
         * Payment fulfillment is idempotent and must not
         * recalculate the historical financial snapshot.
         */
        app(
            PaymentFulfillmentService::class
        )->fulfill(
            $payment
        );

        $order->refresh();

        $this->assertSame(
            '7.00',
            $order->platform_commission_rate
        );

        $this->assertSame(
            '7000.00',
            $order->platform_commission_amount
        );

        $this->assertSame(
            '3.50',
            $order->gateway_fee_rate
        );

        $this->assertSame(
            '3500.00',
            $order->gateway_fee_amount
        );

        $this->assertSame(
            '10500.00',
            $order->total_charges
        );

        $this->assertSame(
            '89500.00',
            $order->organizer_net_amount
        );

        $this->assertNotNull(
            $order->financial_snapshot_at
        );

        $this->assertTrue(
            $order
                ->financial_snapshot_at
                ->equalTo(
                    $originalSnapshotTime
                )
        );
    }
}
