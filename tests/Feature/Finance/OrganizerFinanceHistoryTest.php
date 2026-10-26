<?php

namespace Tests\Feature\Finance;

use App\Filament\Pages\OrganizerTicketFinance;
use App\Filament\Pages\OrganizerMerchandiseFinance;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerFinanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_history_cannot_read_other_organization_events(): void
    {
        $owned = Organization::create(['name' => 'Owned']);
        $foreign = Organization::create(['name' => 'Foreign']);
        $owner = User::factory()->create(['is_super_admin' => false]);
        $owner->organizations()->attach($owned->id, [
            'role' => User::ORGANIZATION_ROLE_OWNER,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
            'is_owner' => true,
            'joined_at' => now(),
        ]);
        $ownedEvent = Event::create([
            'organization_id' => $owned->id, 'name' => 'Owned Event',
            'slug' => 'owned-'.uniqid(), 'status' => Event::STATUS_ACTIVE,
        ]);
        $foreignEvent = Event::create([
            'organization_id' => $foreign->id, 'name' => 'Foreign Event',
            'slug' => 'foreign-'.uniqid(), 'status' => Event::STATUS_ACTIVE,
        ]);
        TicketOrder::create([
            'event_id' => $foreignEvent->id, 'order_number' => 'PRIVATE-ORDER',
            'buyer_name' => 'Private Buyer', 'quantity' => 1,
            'subtotal' => 1000, 'total' => 1000, 'status' => TicketOrder::STATUS_PAID,
        ]);

        $this->actingAs($owner);

        $ticketPage = new OrganizerTicketFinance();
        $ticketPage->selectedEventId = $foreignEvent->id;
        $this->assertCount(0, $ticketPage->ticketTransactions());
        $merchandisePage = new OrganizerMerchandiseFinance();
        $merchandisePage->selectedEventId = $foreignEvent->id;
        $this->assertCount(0, $merchandisePage->merchandiseTransactions());

        $ticketPage->selectedEventId = $ownedEvent->id;
        $this->assertCount(0, $ticketPage->ticketTransactions());
    }
}
