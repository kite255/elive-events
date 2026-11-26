<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Services\EventAdmissionConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EventAdmissionPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_modes_persist_consistent_flags_and_preserve_prices(): void
    {
        $organization = Organization::create(['name' => 'Admission tests']);
        $event = Event::create(['organization_id' => $organization->id, 'name' => 'Admission test']);
        $event->paymentSetting()->create(['registration_fee' => 25000, 'currency' => 'TZS']);
        $event->ticketSetting()->create(['max_tickets_per_order' => 6]);
        $service = app(EventAdmissionConfiguration::class);

        foreach ([
            'paid_combined' => [true, true, true],
            'free_registration' => [true, false, false],
            'tickets' => [false, true, false],
            'paid_registration' => [true, false, true],
            'combined' => [true, true, false],
            'manual' => [false, false, false],
        ] as $mode => [$registration, $tickets, $paid]) {
            $service->persist($event, $mode);
            $saved = $event->fresh(['ticketSetting', 'paymentSetting']);
            $this->assertSame($registration, $saved->registration_is_open, $mode);
            $this->assertSame($tickets, $saved->ticketSetting->ticket_sales_enabled, $mode);
            $this->assertSame($paid, $saved->paymentSetting->payments_enabled, $mode);
            $this->assertSame('25000.00', $saved->paymentSetting->registration_fee);
            $this->assertSame(6, $saved->ticketSetting->max_tickets_per_order);
        }
    }

    public function test_manual_mode_clears_registration_access_code(): void
    {
        $organization = Organization::create(['name' => 'Access tests']);
        $event = Event::create(['organization_id' => $organization->id, 'name' => 'Restricted event', 'registration_access_code_hash' => Hash::make('TestCode2026')]);
        app(EventAdmissionConfiguration::class)->persist($event, 'manual');
        $this->assertNull($event->fresh()->registration_access_code_hash);
    }

    public function test_unknown_admission_mode_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        EventAdmissionConfiguration::flags('invalid');
    }
}
