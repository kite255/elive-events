<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\EventFinanceClearanceService;
use App\Services\EventPayoutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Uses real separate PostgreSQL connections. Do not use RefreshDatabase:
 * its wrapping transaction would hide fixture rows from the child process.
 */
class PostgreSqlConcurrentPayoutTest extends TestCase
{
    public function test_two_connections_cannot_reserve_more_than_the_cleared_balance(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requires PostgreSQL and pcntl.');
        }

        config(['finance.payout_requests_enabled' => true]);
        $admin = User::factory()->create(['is_super_admin' => true]);
        $org = Organization::query()->create(['name' => 'Payout Concurrency '.uniqid()]);
        $event = Event::query()->create([
            'organization_id' => $org->id, 'name' => 'Concurrency Event',
            'slug' => 'payout-concurrency-'.uniqid(), 'status' => Event::STATUS_ACTIVE,
        ]);
        TicketOrder::query()->create([
            'event_id' => $event->id, 'order_number' => 'CONCURRENT-'.uniqid(),
            'buyer_name' => 'Buyer', 'buyer_phone' => '255700000111',
            'quantity' => 1, 'subtotal' => 100, 'total' => 100,
            'gross_amount' => 100, 'organizer_net_amount' => 100,
            'currency' => 'TZS', 'status' => TicketOrder::STATUS_PAID, 'paid_at' => now(),
        ]);
        app(EventFinanceClearanceService::class)->record(
            $event, $admin, 'settlement', '100.00',
            'CONCURRENT-SETTLEMENT-'.uniqid(), 'Verified independent test settlement'
        );

        $resultPath = tempnam(sys_get_temp_dir(), 'payout-race-');
        DB::beginTransaction();
        try {
            // Parent holds the same event lock used by the payout request service.
            Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            $childPid = pcntl_fork();
            if ($childPid === -1) {
                $this->fail('Could not start a second PostgreSQL connection process.');
            }

            if ($childPid === 0) {
                DB::disconnect();
                try {
                    app(EventPayoutService::class)->request($event, $admin, 60.00, 'bank', 'Concurrent test account');
                    file_put_contents($resultPath, 'accepted');
                } catch (ValidationException $exception) {
                    file_put_contents($resultPath, 'rejected');
                } catch (\Throwable $exception) {
                    file_put_contents($resultPath, 'error:'.get_class($exception).':'.$exception->getMessage());
                }
                exit(0);
            }

            // The first reservation commits before the second connection is released.
            app(EventPayoutService::class)->request($event, $admin, 60.00, 'bank', 'Primary test account');
            DB::commit();
            pcntl_waitpid($childPid, $status);
            $this->assertSame('rejected', file_get_contents($resultPath));
            $this->assertSame(1, DB::table('event_payout_requests')->where('event_id', $event->id)->count());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            @unlink($resultPath);
        }
    }
}
