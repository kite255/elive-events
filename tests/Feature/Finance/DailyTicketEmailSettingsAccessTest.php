<?php

namespace Tests\Feature\Finance;

use App\Filament\Pages\DailyTicketEmailSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyTicketEmailSettingsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_daily_email_settings(): void
    {
        $this->assertFalse(DailyTicketEmailSettings::canAccess());
    }

    public function test_regular_user_cannot_access_daily_email_settings(): void
    {
        $user = User::factory()->create(['is_super_admin' => false]);
        $this->actingAs($user);
        $this->assertFalse(DailyTicketEmailSettings::canAccess());
    }

    public function test_super_admin_can_access_daily_email_settings(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($user);
        $this->assertTrue(DailyTicketEmailSettings::canAccess());
    }
}
