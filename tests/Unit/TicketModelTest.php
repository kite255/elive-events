<?php

namespace Tests\Unit;

use App\Models\Ticket;
use PHPUnit\Framework\TestCase;

class TicketModelTest extends TestCase
{
    public function test_ticket_refunded_at_is_fillable_and_cast_to_datetime(): void
    {
        $ticket = new Ticket();

        $this->assertContains(
            'refunded_at',
            $ticket->getFillable()
        );

        $this->assertArrayHasKey(
            'refunded_at',
            $ticket->getCasts()
        );

        $this->assertSame(
            'datetime',
            $ticket->getCasts()['refunded_at']
        );
    }

    public function test_transferred_status_is_not_part_of_mvp_ticket_model(): void
    {
        $this->assertFalse(
            defined(Ticket::class . '::STATUS_TRANSFERRED')
        );
    }
}
