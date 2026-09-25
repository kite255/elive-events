<?php

namespace Tests\Feature\Payments;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Models\TicketUpgrade;
use App\Services\Payments\PesapalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PesapalTicketUpgradeBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_checkout_uses_original_order_buyer_when_payment_has_no_attendee(): void
    {
        config([
            'services.pesapal.base_url' => 'https://pesapal.test',
            'services.pesapal.consumer_key' => 'test-key',
            'services.pesapal.consumer_secret' => 'test-secret',
            'services.pesapal.ipn_id' => 'ipn-test',
            'services.pesapal.country_code' => 'TZ',
        ]);

        Http::fake([
            'https://pesapal.test/api/Auth/RequestToken' => Http::response([
                'token' => 'test-bearer-token',
            ], 200),
            'https://pesapal.test/api/Transactions/SubmitOrderRequest' => Http::response([
                'merchant_reference' => 'PAY-UPGRADE-BILLING',
                'order_tracking_id' => 'TRACK-UPGRADE-BILLING',
                'redirect_url' => 'https://pesapal.test/checkout/upgrade',
            ], 200),
        ]);

        $organization = Organization::query()->create([
            'name' => 'Pesapal Upgrade Organization',
            'slug' => 'pesapal-upgrade-org-' . uniqid(),
        ]);
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Pesapal Upgrade Event',
            'slug' => 'pesapal-upgrade-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);
        $regular = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'code' => 'REG',
            'price' => 30000,
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
            'price' => 50000,
            'currency' => 'TZS',
            'capacity' => 20,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);
        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ELV-BILLING-' . strtoupper(uniqid()),
            'buyer_name' => 'Asha Mtemi',
            'buyer_phone' => '255712345678',
            'buyer_email' => 'asha@example.com',
            'quantity' => 1,
            'subtotal' => 30000,
            'discount_amount' => 0,
            'total' => 30000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);
        $ticket = Ticket::query()->create([
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'ticket_type_id' => $regular->id,
            'ticket_number' => 'ELV-BILLING-TKT-' . strtoupper(uniqid()),
            'public_token' => 'billing-ticket-' . uniqid(),
            'qr_token_hash' => hash('sha256', 'billing-' . uniqid()),
            'holder_name' => $order->buyer_name,
            'price' => 30000,
            'currency' => 'TZS',
            'status' => Ticket::STATUS_ISSUED,
            'issued_at' => now(),
        ]);
        $upgrade = TicketUpgrade::query()->create([
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'ticket_id' => $ticket->id,
            'from_ticket_type_id' => $regular->id,
            'to_ticket_type_id' => $vip->id,
            'reference' => 'ELV-UPG-BILLING',
            'original_price' => 30000,
            'target_price' => 50000,
            'upgrade_amount' => 20000,
            'currency' => 'TZS',
            'status' => TicketUpgrade::STATUS_PROCESSING,
            'initiated_at' => now(),
            'expires_at' => now()->addHour(),
        ]);
        $payment = Payment::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'attendee_id' => null,
            'ticket_order_id' => null,
            'ticket_upgrade_id' => $upgrade->id,
            'reference' => 'PAY-UPGRADE-BILLING',
            'amount' => 20000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PENDING,
            'description' => 'Ticket upgrade',
            'initiated_at' => now(),
        ]);

        app(PesapalService::class)->createPayment($payment);

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/api/Transactions/SubmitOrderRequest')) {
                return false;
            }

            return data_get($request->data(), 'billing_address.email_address') === 'asha@example.com'
                && data_get($request->data(), 'billing_address.phone_number') === '255712345678'
                && data_get($request->data(), 'billing_address.first_name') === 'Asha'
                && data_get($request->data(), 'billing_address.last_name') === 'Mtemi';
        });
    }
}
