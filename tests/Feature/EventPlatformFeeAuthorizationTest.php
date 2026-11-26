<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventPlatformFeeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        $organization = Organization::create(['name' => 'Fee authorization test']);
        return Event::create(['organization_id' => $organization->id, 'name' => 'Fee test event']);
    }

    public function test_organizer_cannot_change_platform_fee_fields(): void
    {
        $event = $this->event();
        $setting = $event->paymentSetting()->create([
            'platform_commission_rate' => 7,
            'gateway_fee_rate' => 0,
            'gateway_fee_bearer' => 'organizer',
        ]);
        $user = User::factory()->create(['is_super_admin' => false]);
        $event->organization->attachUser($user, User::ORGANIZATION_ROLE_EVENT_MANAGER);
        $this->actingAs($user);

        foreach (['platform_commission_rate' => 1, 'gateway_fee_rate' => 2, 'gateway_fee_bearer' => 'elive'] as $field => $value) {
            try {
                $setting->refresh()->update([$field => $value]);
                $this->fail('Unauthorized fee update was accepted: '.$field);
            } catch (AuthorizationException) {
                $this->assertSame(['7.00', '0.00', 'organizer'], [
                    $setting->fresh()->platform_commission_rate,
                    $setting->fresh()->gateway_fee_rate,
                    $setting->fresh()->gateway_fee_bearer,
                ]);
            }
        }
    }

    public function test_organizer_can_set_registration_price_without_changing_platform_fees(): void
    {
        $event = $this->event();
        $setting = $event->paymentSetting()->create(['platform_commission_rate' => 7]);
        $user = User::factory()->create(['is_super_admin' => false]);
        $event->organization->attachUser($user, User::ORGANIZATION_ROLE_EVENT_MANAGER);
        $this->actingAs($user);
        $setting->update(['registration_fee' => 25000, 'payments_enabled' => true]);
        $this->assertSame('25000.00', $setting->fresh()->registration_fee);
        $this->assertSame('7.00', $setting->fresh()->platform_commission_rate);
    }

    public function test_super_admin_can_change_platform_fees(): void
    {
        $setting = $this->event()->paymentSetting()->create(['platform_commission_rate' => 7]);
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        $setting->update(['platform_commission_rate' => 8, 'gateway_fee_rate' => 2, 'gateway_fee_bearer' => 'elive']);
        $this->assertSame('8.00', $setting->fresh()->platform_commission_rate);
        $this->assertSame('2.00', $setting->fresh()->gateway_fee_rate);
        $this->assertSame('elive', $setting->fresh()->gateway_fee_bearer);
    }

    public function test_organizer_cannot_supply_platform_fee_on_creation(): void
    {
        $event = $this->event();
        $user = User::factory()->create(['is_super_admin' => false]);
        $event->organization->attachUser($user, User::ORGANIZATION_ROLE_EVENT_MANAGER);
        $this->actingAs($user);
        $this->expectException(AuthorizationException::class);
        $event->paymentSetting()->create(['platform_commission_rate' => 0]);
    }
}
