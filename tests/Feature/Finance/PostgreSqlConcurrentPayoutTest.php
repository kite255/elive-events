<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\EventFinanceClearanceService;
use App\Services\EventPayoutService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Separate PHP processes provide independent database sockets.
 * Do not use RefreshDatabase: its transaction hides fixtures from the child.
 */
class PostgreSqlConcurrentPayoutTest extends TestCase
{
    public function test_concurrent_requests_cannot_overallocate_cleared_funds(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! function_exists('proc_open')) {
            $this->markTestSkipped('Requires PostgreSQL and proc_open.');
        }

        // Never create financial test fixtures in the normal application database.
        $connection = DB::connection();
        $database = $connection->getDatabaseName();
        if ($database !== 'elive_events_concurrency_test' || app()->environment() !== 'testing') {
            $this->fail('Concurrency tests require APP_ENV=testing and the isolated elive_events_concurrency_test database.');
        }

        config(['finance.payout_requests_enabled' => true]);
        $admin = User::factory()->create(['is_super_admin' => true]);
        $org = Organization::query()->create(['name' => 'Payout Race '.uniqid()]);
        $event = Event::query()->create([
            'organization_id' => $org->id, 'name' => 'Concurrent Payout',
            'slug' => 'payout-race-'.uniqid(), 'status' => Event::STATUS_ACTIVE,
        ]);
        TicketOrder::query()->create([
            'event_id' => $event->id, 'order_number' => 'RACE-'.uniqid(),
            'buyer_name' => 'Buyer', 'buyer_phone' => '255700000111',
            'quantity' => 1, 'subtotal' => 100, 'total' => 100,
            'gross_amount' => 100, 'organizer_net_amount' => 100,
            'currency' => 'TZS', 'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);
        $reference = 'RACE-SETTLEMENT-'.uniqid();
        app(EventFinanceClearanceService::class)->record(
            $event, $admin, 'settlement', '100.00',
            $reference, 'Settlement statement was checked'
        );
        $verifier = User::factory()->create(['is_super_admin' => true]);
        $entryId = (int) DB::table('event_finance_clearance_entries')
            ->where('external_reference', $reference)->value('id');
        app(EventFinanceClearanceService::class)->independentlyVerify(
            $entryId, $verifier, 'Second administrator verified the settlement against evidence'
        );

        $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (\Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'elive_events_concurrency_test' || ! $app->environment('testing')) {
    fwrite(STDERR, 'Child process refused an unsafe database connection.');
    exit(2);
}
config(['finance.payout_requests_enabled' => true]);
$event = \App\Models\Event::findOrFail((int) $argv[1]);
$admin = \App\Models\User::findOrFail((int) $argv[2]);
try {
    app(\App\Services\EventPayoutService::class)->request($event, $admin, 60.0, 'bank', 'Independent account');
    echo 'accepted';
} catch (\Illuminate\Validation\ValidationException $exception) {
    echo 'rejected';
}
PHP;

        // PHPUnit XML environment values are not automatically passed to PHP -r.
        // Propagate the verified connection to the independent database process.
        $dbConfig = $connection->getConfig();
        $childEnv = array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_URL' => '',
            'DB_HOST' => (string) ($dbConfig['host'] ?? '127.0.0.1'),
            'DB_PORT' => (string) ($dbConfig['port'] ?? '5432'),
            'DB_DATABASE' => $database,
            'DB_USERNAME' => (string) ($dbConfig['username'] ?? ''),
            'DB_PASSWORD' => (string) ($dbConfig['password'] ?? ''),
        ]);

        DB::beginTransaction();
        $process = null;
        $pipes = [];
        try {
            Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $process = proc_open(
                [PHP_BINARY, '-r', $script, (string) $event->id, (string) $admin->id],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                base_path(),
                $childEnv
            );
            $this->assertIsResource($process);
            fclose($pipes[0]);

            // First reservation becomes visible before the other connection acquires the lock.
            app(EventPayoutService::class)->request($event, $admin, 60.0, 'bank', 'Primary account');
            DB::commit();

            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exit = proc_close($process);
            $process = null;

            $this->assertSame(0, $exit, $errors);
            $this->assertSame('rejected', $output, $errors);
            $this->assertSame(1, DB::table('event_payout_requests')->where('event_id', $event->id)->count());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }
    }
}
