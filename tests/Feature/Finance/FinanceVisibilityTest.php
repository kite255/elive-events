<?php

namespace Tests\Feature\Finance;

use App\Filament\Resources\Payouts\PayoutResource;
use App\Filament\Widgets\OrganizerFinanceOverview;
use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_see_finance_or_payout_resource(): void
    {
        $this->assertFalse(OrganizerFinanceOverview::canView());
        $this->assertFalse(PayoutResource::canViewAny());
        $this->assertSame(0, PayoutResource::getEloquentQuery()->count());
    }

    public function test_unassigned_user_cannot_see_finance(): void
    {
        $user = User::factory()->create(['is_super_admin' => false]);
        $this->actingAs($user);

        $this->assertFalse(OrganizerFinanceOverview::canView());
        $this->assertFalse(PayoutResource::canViewAny());
    }

    public function test_organization_finance_is_not_visible_to_other_organization_owner(): void
    {
        $organizationA = Organization::query()->create(['name' => 'Finance Org A']);
        $organizationB = Organization::query()->create(['name' => 'Finance Org B']);
        $eventA = Event::query()->create([
            'organization_id' => $organizationA->id,
            'name' => 'Finance Event A',
            'slug' => 'finance-event-a',
            'status' => Event::STATUS_ACTIVE,
        ]);
        $eventB = Event::query()->create([
            'organization_id' => $organizationB->id,
            'name' => 'Finance Event B',
            'slug' => 'finance-event-b',
            'status' => Event::STATUS_ACTIVE,
        ]);
        $owner = User::factory()->create(['is_super_admin' => false]);
        $owner->organizations()->attach($organizationA->id, [
            'role' => User::ORGANIZATION_ROLE_OWNER,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
            'is_owner' => true,
        ]);
        $this->actingAs($owner);

        $this->assertTrue(OrganizerFinanceOverview::canView());
        $this->assertTrue(PayoutResource::canViewAny());
        $this->assertTrue($owner->managedOrganizations()->whereKey($organizationA->id)->exists());
        $this->assertFalse($owner->managedOrganizations()->whereKey($organizationB->id)->exists());

        $scopedEvents = Event::query()->whereIn(
            'organization_id', $owner->managedOrganizations()->select('organizations.id')
        )->pluck('events.id')->all();
        $this->assertContains($eventA->id, $scopedEvents);
        $this->assertNotContains($eventB->id, $scopedEvents);

        $payoutA = EventPayoutRequest::query()->create([
            'event_id' => $eventA->id, 'requested_by' => $owner->id,
            'reference' => 'SCOPE-A-'.uniqid(), 'amount' => '100.00',
            'destination_type' => 'bank', 'destination_details' => 'Test account A',
        ]);
        $payoutB = EventPayoutRequest::query()->create([
            'event_id' => $eventB->id, 'requested_by' => $owner->id,
            'reference' => 'SCOPE-B-'.uniqid(), 'amount' => '100.00',
            'destination_type' => 'bank', 'destination_details' => 'Test account B',
        ]);
        $visiblePayouts = PayoutResource::getEloquentQuery()->pluck('event_payout_requests.id')->all();
        $this->assertContains($payoutA->id, $visiblePayouts);
        $this->assertNotContains($payoutB->id, $visiblePayouts);
        $this->assertTrue(PayoutResource::canView($payoutA));
        $this->assertFalse(PayoutResource::canView($payoutB));
    }
}
