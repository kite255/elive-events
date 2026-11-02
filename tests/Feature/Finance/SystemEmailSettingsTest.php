<?php

namespace Tests\Feature\Finance;

use App\Filament\Pages\SystemEmailSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SystemEmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_access_system_email_settings(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $regular = User::factory()->create(['is_super_admin' => false]);

        $this->actingAs($admin);
        $this->assertTrue(SystemEmailSettings::canAccess());

        $this->actingAs($regular);
        $this->assertFalse(SystemEmailSettings::canAccess());
    }

    public function test_super_admin_can_save_payout_email_recipients(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(SystemEmailSettings::class)
            ->set('payoutEnabled', true)
            ->set('payoutEmails', "Finance@Example.com\nadmin@example.com")
            ->call('save')
            ->assertHasNoErrors();

        $record = DB::table('system_notification_settings')->where('id', 1)->first();
        $this->assertTrue((bool) $record->payout_request_emails_enabled);
        $this->assertSame(
            ['finance@example.com', 'admin@example.com'],
            json_decode($record->payout_request_recipients, true)
        );
    }
}
