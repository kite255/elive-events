<?php

namespace Tests\Feature\Communications;

use App\Filament\Pages\CommunicationCenter;
use App\Jobs\SendAutomaticCommunicationJob;
use App\Models\CommunicationLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\CommunicationCampaignService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class TicketBuyerCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_campaign_queues_one_message_per_paid_order_for_the_event(): void
    {
        Bus::fake();
        $organization = Organization::create(['name' => 'Concert', 'slug' => 'concert']);
        $event = Event::create(['organization_id' => $organization->id, 'name' => 'Launch', 'slug' => 'launch', 'status' => Event::STATUS_ACTIVE]);
        $other = Event::create(['organization_id' => $organization->id, 'name' => 'Other', 'slug' => 'other', 'status' => Event::STATUS_ACTIVE]);
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        $paid = $this->order($event, 'PAID', TicketOrder::STATUS_PAID);
        $this->order($event, 'PENDING', TicketOrder::STATUS_PENDING);
        $this->order($other, 'OTHER', TicketOrder::STATUS_PAID);

        $service = app(CommunicationCampaignService::class);
        $preview = $service->preview($event, 'paid_ticket_buyers');
        $this->assertSame(1, $preview['valid']);

        $campaign = $service->queueSmsCampaign($event, 'Reminder', 'Hi #PURCHASER_NAME#, tickets: #TICKET_LINK#', 'paid_ticket_buyers');
        $this->assertSame(1, $campaign->queued_count);
        $this->assertSame($paid->id, $campaign->logs()->sole()->ticket_order_id);
        $this->assertSame($paid->id, $campaign->recipients()->sole()->ticket_order_id);
        $this->assertStringNotContainsString($paid->public_token, $campaign->logs()->sole()->message);
        $retry = $service->queueSmsCampaign($event, 'Reminder', 'Hi #PURCHASER_NAME#, tickets: #TICKET_LINK#', 'paid_ticket_buyers');
        $this->assertSame($campaign->id, $retry->id);

        $url = route('public.ticket-orders.show', ['token' => $paid->public_token]);
        $this->mock(SmsService::class)
            ->shouldReceive('send')->once()
            ->with('255712345678', 'Hi Test Buyer, tickets: '.$url)
            ->andReturn(['success' => true, 'provider_message_id' => 'test-sms']);
        (new SendAutomaticCommunicationJob($campaign->logs()->sole()->id))->handle();

        $this->assertSame(1, $campaign->fresh()->sent_count);
    }

    public function test_center_previews_and_queues_buyer_email_with_a_safe_test_link(): void
    {
        Bus::fake();
        $organization = Organization::create(['name' => 'Concert', 'slug' => 'concert']);
        $event = Event::create(['organization_id' => $organization->id, 'name' => 'Launch', 'slug' => 'launch', 'status' => Event::STATUS_ACTIVE]);
        $paid = $this->order($event, 'ORDER-1', TicketOrder::STATUS_PAID);
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        config(['mail.default' => 'array']);

        Livewire::test(CommunicationCenter::class)
            ->set('eventId', $event->id)
            ->set('channel', 'sms')
            ->set('audience', 'paid_ticket_buyers')
            ->set('message', str_repeat('A', 470).' #TICKET_LINK#')
            ->assertSet('preview.above_three_sms_segments', 1)
            ->set('message', str_repeat('A', 195).' 漢 #TICKET_LINK#')
            ->assertSet('preview.above_three_sms_segments', 1)
            ->set('channel', 'email')
            ->set('subject', 'Reminder for #PURCHASER_NAME#')
            ->set('message', 'Hi #NAME#')
            ->call('queueCampaign')
            ->assertHasErrors('message')
            ->set('message', 'Hi #PURCHASER_NAME#, tickets: #TICKET_LINK#')
            ->set('testEmail', 'preview@example.com')
            ->call('sendTestEmail')
            ->assertHasNoErrors()
            ->call('queueCampaign')
            ->assertHasNoErrors();

        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $sample = $messages->first()->getOriginalMessage();
        $this->assertSame('preview@example.com', $sample->getTo()[0]->getAddress());
        $this->assertSame('[TEST] Reminder for Test Buyer', $sample->getSubject());
        $this->assertStringContainsString('/events/tickets/find', $sample->getHtmlBody());
        $this->assertStringNotContainsString($paid->public_token, $sample->getHtmlBody());

        $log = CommunicationLog::query()->sole();
        (new SendAutomaticCommunicationJob($log->id))->handle();

        $delivery = $messages->last()->getOriginalMessage();
        $this->assertSame('buyer@example.com', $delivery->getTo()[0]->getAddress());
        $this->assertStringContainsString('href="'.route('public.ticket-orders.show', ['token' => $paid->public_token]).'"', $delivery->getHtmlBody());
        $this->assertSame(CommunicationLog::STATUS_SENT, $log->fresh()->status);
        $this->assertStringNotContainsString($paid->public_token, $log->fresh()->message);
        $this->assertSame(1, $log->campaign->sent_count);
    }

    public function test_campaign_does_not_send_a_ticket_link_to_an_old_buyer_address(): void
    {
        Bus::fake();
        $organization = Organization::create(['name' => 'Concert', 'slug' => 'concert']);
        $event = Event::create(['organization_id' => $organization->id, 'name' => 'Launch', 'slug' => 'launch', 'status' => Event::STATUS_ACTIVE]);
        $paid = $this->order($event, 'ORDER-1', TicketOrder::STATUS_PAID);
        config(['mail.default' => 'array']);

        $campaign = app(CommunicationCampaignService::class)->queueEmailCampaign(
            $event, 'Reminder', 'Launch', 'Hi #PURCHASER_NAME#: #TICKET_LINK#', 'paid_ticket_buyers',
        );

        $paid->update(['buyer_email' => 'updated@example.com']);
        $log = $campaign->logs()->sole();
        (new SendAutomaticCommunicationJob($log->id))->handle();

        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
        $this->assertSame(CommunicationLog::STATUS_FAILED, $log->fresh()->status);
    }

    private function order(Event $event, string $reference, string $status): TicketOrder
    {
        return TicketOrder::create([
            'event_id' => $event->id,
            'order_number' => $reference,
            'buyer_name' => 'Test Buyer',
            'buyer_phone' => '255712345678',
            'buyer_email' => 'buyer@example.com',
            'quantity' => 3,
            'subtotal' => 15000,
            'total' => 15000,
            'currency' => 'TZS',
            'status' => $status,
            'public_token' => 'token-'.$reference,
            'paid_at' => $status === TicketOrder::STATUS_PAID ? now() : null,
        ]);
    }
}
