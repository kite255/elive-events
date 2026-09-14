<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrderTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_reservation_expiry_preserves_twenty_minute_interval(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Timezone Test Organization',
            'slug' => 'timezone-test-organization',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Timezone Test Event',
            'slug' => 'timezone-test-event',
            'event_code' => 'TZTEST',
            'event_type' => 'concert',
            'status' => 'active',
        ]);

        $createdAt = now()->startOfSecond();

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'TIMEZONE-TEST',
            'buyer_name' => 'Timezone Test',
            'quantity' => 1,
            'subtotal' => 1000,
            'discount_amount' => 0,
            'total' => 1000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PENDING,
            'expires_at' => $createdAt
                ->copy()
                ->addMinutes(20),
        ]);

        $order->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        $fresh = $order->fresh();

        $this->assertSame(
            20,
            (int) $fresh->created_at->diffInMinutes(
                $fresh->expires_at,
                false
            )
        );
    }
}