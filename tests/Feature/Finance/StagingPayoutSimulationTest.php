<?php

namespace Tests\Feature\Finance;

use App\Filament\Pages\StagingPayoutSimulation;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StagingPayoutSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulation_is_hidden_outside_staging(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin);
        $this->assertFalse(StagingPayoutSimulation::canAccess());
    }

    public function test_simulation_requires_named_test_event_and_staging_environment(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin);
        $this->app->detectEnvironment(fn (): string => 'staging');
        $this->assertFalse(StagingPayoutSimulation::canAccess());

        $organization = Organization::create(['id' => 7, 'name' => 'Test Organization']);
        Event::create([
            'id' => 10,
            'organization_id' => $organization->id,
            'name' => 'eLive Payout Testing - SIMULATED',
            'slug' => 'elive-payout-testing-simulated',
            'status' => Event::STATUS_ACTIVE,
        ]);
        $this->assertTrue(StagingPayoutSimulation::canAccess());
    }
}
