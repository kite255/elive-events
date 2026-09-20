<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use App\Services\Tickets\OrganizerSalesMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerSalesMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_core_sales_metrics_for_one_event(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Sales Metrics Organization',
            'slug' => 'sales-metrics-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Sales Metrics Event',
            'slug' => 'sales-metrics-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $otherEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Other Event',
            'slug' => 'other-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $this->createOrder(
            event: $event,
            status: TicketOrder::STATUS_PAID,
            quantity: 2,
            total: 30000,
            totalCharges: 3000,
            organizerNetAmount: 27000,
        );

        $this->createOrder(
            event: $event,
            status: TicketOrder::STATUS_PAID,
            quantity: 1,
            total: 20000,
            totalCharges: 2000,
            organizerNetAmount: 18000,
        );

        $this->createOrder(
            event: $event,
            status: TicketOrder::STATUS_PENDING,
            quantity: 3,
            total: 45000,
        );

        $this->createOrder(
            event: $event,
            status: TicketOrder::STATUS_PROCESSING,
            quantity: 1,
            total: 15000,
        );

        $this->createOrder(
            event: $event,
            status: TicketOrder::STATUS_EXPIRED,
            quantity: 4,
            total: 60000,
        );

        $this->createOrder(
            event: $otherEvent,
            status: TicketOrder::STATUS_PAID,
            quantity: 10,
            total: 999999,
            totalCharges: 99999,
            organizerNetAmount: 900000,
        );

        $metrics = app(
            OrganizerSalesMetricsService::class
        )->forEvent($event);

        $this->assertSame(
            50000.0,
            (float) $metrics['gross_sales']
        );

        $this->assertSame(
            2,
            $metrics['paid_orders']
        );

        $this->assertSame(
            2,
            $metrics['pending_orders']
        );

        $this->assertSame(
            1,
            $metrics['expired_orders']
        );

        $this->assertSame(
            3,
            $metrics['tickets_sold']
        );

        $this->assertSame(
            'TZS',
            $metrics['currency']
        );

        $this->assertArrayHasKey(
            'total_charges',
            $metrics
        );

        $this->assertArrayHasKey(
            'net_payable',
            $metrics
        );

        $this->assertSame(
            5000.0,
            (float) $metrics['total_charges']
        );

        $this->assertSame(
            45000.0,
            (float) $metrics['net_payable']
        );
    }

    public function test_it_returns_sales_by_ticket_type_and_recent_orders(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Detailed Sales Organization',
            'slug' => 'detailed-sales-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Detailed Sales Event',
            'slug' => 'detailed-sales-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $regular = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'code' => 'REG',
            'price' => 10000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $vip = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'VIP',
            'code' => 'VIP',
            'price' => 20000,
            'currency' => 'TZS',
            'capacity' => 20,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $order = $this->createOrder(
            event: $event,
            status: TicketOrder::STATUS_PAID,
            quantity: 3,
            total: 40000,
            totalCharges: 4000,
            organizerNetAmount: 36000,
        );

        TicketOrderItem::query()->create([
            'ticket_order_id' => $order->id,
            'ticket_type_id' => $regular->id,
            'quantity' => 2,
            'unit_price' => 10000,
            'subtotal' => 20000,
            'discount_amount' => 0,
            'total' => 20000,
        ]);

        TicketOrderItem::query()->create([
            'ticket_order_id' => $order->id,
            'ticket_type_id' => $vip->id,
            'quantity' => 1,
            'unit_price' => 20000,
            'subtotal' => 20000,
            'discount_amount' => 0,
            'total' => 20000,
        ]);

        $metrics = app(
            OrganizerSalesMetricsService::class
        )->forEvent($event);

        $this->assertArrayHasKey(
            'sales_by_ticket_type',
            $metrics
        );

        $this->assertArrayHasKey(
            'recent_orders',
            $metrics
        );

        $this->assertCount(
            2,
            $metrics['sales_by_ticket_type']
        );

        $this->assertSame(
            3,
            (int) collect(
                $metrics['sales_by_ticket_type']
            )->sum('quantity')
        );

        $this->assertSame(
            40000.0,
            (float) collect(
                $metrics['sales_by_ticket_type']
            )->sum('revenue')
        );

        $this->assertNotEmpty(
            $metrics['recent_orders']
        );

        $this->assertSame(
            $order->order_number,
            $metrics['recent_orders'][0]['order_number']
        );

        $this->assertSame(
            TicketOrder::STATUS_PAID,
            $metrics['recent_orders'][0]['status']
        );
    }

    public function test_financial_metrics_only_include_paid_orders(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Financial Metrics Organization',
            'slug' => 'financial-metrics-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Financial Metrics Event',
            'slug' => 'financial-metrics-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $this->createOrder(
            event: $event,
            status: TicketOrder::STATUS_PAID,
            quantity: 2,
            total: 100000,
            totalCharges: 10000,
            organizerNetAmount: 90000,
        );

        $this->createOrder(
            event: $event,
            status: TicketOrder::STATUS_PENDING,
            quantity: 1,
            total: 50000,
            totalCharges: 5000,
            organizerNetAmount: 45000,
        );

        $this->createOrder(
            event: $event,
            status: TicketOrder::STATUS_EXPIRED,
            quantity: 1,
            total: 25000,
            totalCharges: 2500,
            organizerNetAmount: 22500,
        );

        $metrics = app(
            OrganizerSalesMetricsService::class
        )->forEvent($event);

        $this->assertSame(
            100000.0,
            (float) $metrics['gross_sales']
        );

        $this->assertSame(
            10000.0,
            (float) $metrics['total_charges']
        );

        $this->assertSame(
            90000.0,
            (float) $metrics['net_payable']
        );

        $this->assertSame(
            1,
            $metrics['paid_orders']
        );
    }

    public function test_event_metrics_do_not_include_orders_from_other_events(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Event Isolation Organization',
            'slug' => 'event-isolation-' . uniqid(),
        ]);

        $eventA = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Event A',
            'slug' => 'event-a-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $eventB = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Event B',
            'slug' => 'event-b-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $this->createOrder(
            event: $eventA,
            status: TicketOrder::STATUS_PAID,
            quantity: 1,
            total: 20000,
            totalCharges: 2000,
            organizerNetAmount: 18000,
        );

        $this->createOrder(
            event: $eventB,
            status: TicketOrder::STATUS_PAID,
            quantity: 10,
            total: 500000,
            totalCharges: 50000,
            organizerNetAmount: 450000,
        );

        $metrics = app(
            OrganizerSalesMetricsService::class
        )->forEvent($eventA);

        $this->assertSame(
            20000.0,
            (float) $metrics['gross_sales']
        );

        $this->assertSame(
            2000.0,
            (float) $metrics['total_charges']
        );

        $this->assertSame(
            18000.0,
            (float) $metrics['net_payable']
        );

        $this->assertSame(
            1,
            $metrics['paid_orders']
        );
    }

    private function createOrder(
        Event $event,
        string $status,
        int $quantity,
        int $total,
        int $totalCharges = 0,
        ?int $organizerNetAmount = null,
    ): TicketOrder {
        $organizerNetAmount ??=
            $total - $totalCharges;

        $isPaid =
            $status === TicketOrder::STATUS_PAID;

        return TicketOrder::query()->create([
            'event_id' =>
                $event->id,

            'order_number' =>
                'ORD-' . strtoupper(uniqid()),

            'buyer_name' =>
                'Sales Test Buyer',

            'buyer_phone' =>
                '255700000001',

            'buyer_email' =>
                'buyer@example.com',

            'quantity' =>
                $quantity,

            'subtotal' =>
                $total,

            'discount_amount' =>
                0,

            'total' =>
                $total,

            'gross_amount' =>
                $total,

            'platform_commission_rate' =>
                0,

            'platform_commission_amount' =>
                $totalCharges,

            'gateway_fee_rate' =>
                0,

            'gateway_fee_amount' =>
                0,

            'total_charges' =>
                $totalCharges,

            'organizer_net_amount' =>
                $organizerNetAmount,

            'financial_snapshot_at' =>
                $isPaid
                    ? now()
                    : null,

            'currency' =>
                'TZS',

            'status' =>
                $status,

            'paid_at' =>
                $isPaid
                    ? now()
                    : null,

            'expires_at' =>
                now()->addMinutes(20),
        ]);
    }
}