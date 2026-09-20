<?php

namespace Tests\Feature\Payments;

use App\Models\Event;
use App\Models\EventPaymentSetting;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventFinancialSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_financial_settings_can_be_saved(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Finance Settings Organization',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Finance Settings Event',
            'event_type' => 'conference',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $settings = EventPaymentSetting::query()->create([
            'event_id' => $event->id,

            'payments_enabled' => true,
            'currency' => 'TZS',
            'registration_fee' => 0,

            'platform_commission_rate' => 7,
            'gateway_fee_rate' => 3.5,
            'gateway_fee_bearer' =>
                EventPaymentSetting::GATEWAY_FEE_BEARER_ORGANIZER,

            'payment_required_before_confirmation' => true,
            'payment_required_before_badge' => true,
            'block_check_in_if_unpaid' => false,
            'allow_manual_payment' => false,
        ]);

        $settings->update([
            'platform_commission_rate' => 5.5,
            'gateway_fee_rate' => 3.25,
            'gateway_fee_bearer' =>
                EventPaymentSetting::GATEWAY_FEE_BEARER_ELIVE,
        ]);

        $settings->refresh();

        $this->assertSame(
            '5.50',
            $settings->platform_commission_rate
        );

        $this->assertSame(
            '3.25',
            $settings->gateway_fee_rate
        );

        $this->assertSame(
            EventPaymentSetting::GATEWAY_FEE_BEARER_ELIVE,
            $settings->gateway_fee_bearer
        );
    }

    public function test_new_event_payment_settings_default_to_seven_percent_commission(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Default Finance Organization',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Default Finance Event',
            'event_type' => 'conference',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $settings = EventPaymentSetting::query()->create([
            'event_id' => $event->id,
            'payments_enabled' => true,
            'currency' => 'TZS',
            'registration_fee' => 0,

            'payment_required_before_confirmation' => true,
            'payment_required_before_badge' => true,
            'block_check_in_if_unpaid' => false,
            'allow_manual_payment' => false,
        ]);

        $settings->refresh();

        $this->assertSame(
            '7.00',
            $settings->platform_commission_rate
        );

        $this->assertSame(
            '0.00',
            $settings->gateway_fee_rate
        );

        $this->assertSame(
            EventPaymentSetting::GATEWAY_FEE_BEARER_ORGANIZER,
            $settings->gateway_fee_bearer
        );
    }
}