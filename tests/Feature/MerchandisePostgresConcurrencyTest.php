<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventMerchandise;
use App\Models\MerchandiseVariant;
use App\Models\MerchandiseOrder;
use App\Models\MerchandiseProduct;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MerchandisePostgresConcurrencyTest extends TestCase
{
    /**
     * No RefreshDatabase: it wraps the parent connection in a transaction,
     * making newly created fixtures invisible to separate worker processes.
     * Run against the existing, migrated, disposable Docker test database.
     */
    public function test_two_simultaneous_checkouts_cannot_reserve_the_same_last_item(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL-only concurrency test.');
        }

        $connection = config('database.connections.pgsql');
        if (($connection['database'] ?? null) !== 'elive_merchandise_test'
            || ! in_array((string) ($connection['port'] ?? ''), ['5433', '5435'], true)
            || ($connection['host'] ?? null) !== '127.0.0.1') {
            $this->fail('Refusing concurrency test on any database other than the isolated local PostgreSQL test database.');
        }

        $organization = Organization::query()->create(['name' => 'Concurrent Shop Test ' . Str::lower(Str::random(12))]);
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Concurrent Test Event',
            'slug' => 'concurrent-' . Str::lower(Str::random(12)),
            'status' => 'active',
            'merchandise_section_enabled' => true,
        ]);
        $product = MerchandiseProduct::query()->create([
            'event_id' => $event->id,
            'name' => 'Last Shirt',
            'slug' => 'last-shirt',
            'price_tzs' => 10000,
            'stock_quantity' => 1,
            'is_active' => true,
        ]);

        $workers = [];
        $markers = [];
        $lockHeld = false;
        try {
            // Block checkout product row until both HTTP workers have started.
            DB::beginTransaction();
            $lockHeld = true;
            DB::table('merchandise_products')->where('id', $product->id)->lockForUpdate()->first();

            $path = parse_url(route('public.merchandise.store', [$event, $product]), PHP_URL_PATH);
            $workerScript = base_path('tests/Support/merchandise_checkout_worker.php');
            $config = config('database.connections.pgsql');
            $env = [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => '127.0.0.1',
                'DB_PORT' => (string) $config['port'],
                'DB_DATABASE' => 'elive_merchandise_test',
                'DB_USERNAME' => (string) $config['username'],
                'DB_PASSWORD' => (string) $config['password'],
                'DB_URL' => '',
            ];

            for ($i = 0; $i < 2; $i++) {
                $marker = tempnam(sys_get_temp_dir(), 'elive_checkout_');
                unlink($marker);
                $markers[] = $marker;
                $process = new Process([PHP_BINARY, $workerScript, $path, $marker], base_path(), $env);
                $process->setTimeout(20);
                $process->start();
                $workers[] = $process;
            }

            $deadline = microtime(true) + 10;
            while (microtime(true) < $deadline && count(array_filter($markers, 'file_exists')) < 2) {
                usleep(25000);
            }
            $diagnostics = implode("\n", array_map(fn (Process $process) => $process->getErrorOutput() . $process->getOutput(), $workers));
            $this->assertCount(2, array_filter($markers, 'file_exists'), 'Both checkout workers must start. ' . $diagnostics);

            DB::commit();
            $lockHeld = false;

            $statuses = [];
            foreach ($workers as $process) {
                $process->wait();
                $output = $process->getOutput();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $output);
                $this->assertMatchesRegularExpression('/HTTP_STATUS:(302|422)/', $output, $output);
                preg_match('/HTTP_STATUS:(\\d+)/', $output, $match);
                $statuses[] = (int) $match[1];
            }

            sort($statuses);
            $this->assertSame([302, 422], $statuses);
            $this->assertSame(0, $product->fresh()->stock_quantity);
            $this->assertSame(1, MerchandiseOrder::query()->where('merchandise_product_id', $product->id)->count());
        } finally {
            if ($lockHeld) {
                DB::rollBack();
            }
            foreach ($workers as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            foreach ($markers as $marker) {
                @unlink($marker);
            }
            MerchandiseOrder::query()->where('merchandise_product_id', $product->id)->delete();
            $product->delete();
            $event->delete();
            $organization->delete();
        }
    }
    public function test_two_simultaneous_public_checkouts_cannot_oversell_shared_variant(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL-only concurrency test.');
        }

        $connection = config('database.connections.pgsql');
        if (($connection['database'] ?? null) !== 'elive_merchandise_test'
            || ! in_array((string) ($connection['port'] ?? ''), ['5433', '5435'], true)
            || ($connection['host'] ?? null) !== '127.0.0.1') {
            $this->fail('Refusing to run outside the isolated local PostgreSQL database.');
        }

        $organization = Organization::query()->create(['name' => 'Linked Variant Race ' . Str::random(12)]);
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Linked Variant Checkout Event',
            'slug' => 'linked-race-' . Str::lower(Str::random(12)),
            'status' => 'active',
            'merchandise_section_enabled' => true,
        ]);
        $item = EventMerchandise::query()->create([
            'event_id' => $event->id,
            'name' => 'Shirt',
            'selection_type' => 'optional',
            'maximum_per_attendee' => 2,
            'is_active' => true,
        ]);
        $variant = MerchandiseVariant::query()->create([
            'event_merchandise_id' => $item->id,
            'name' => 'Standard',
            'stock_quantity' => 1,
            'price' => 15000,
            'currency' => 'TZS',
            'is_active' => true,
        ]);
        $product = MerchandiseProduct::query()->create([
            'event_id' => $event->id,
            'event_merchandise_id' => $item->id,
            'name' => 'Shirt',
            'slug' => 'shirt',
            'price_tzs' => 15000,
            'is_active' => true,
        ]);

        $workers = [];
        $markers = [];
        $lockHeld = false;

        try {
            DB::beginTransaction();
            $lockHeld = true;
            DB::table('merchandise_variants')->where('id', $variant->id)->lockForUpdate()->first();

            $urlPath = parse_url(route('public.merchandise.store', [$event, $product]), PHP_URL_PATH);
            $config = config('database.connections.pgsql');
            $env = [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => '127.0.0.1',
                'DB_PORT' => (string) $config['port'],
                'DB_DATABASE' => 'elive_merchandise_test',
                'DB_USERNAME' => (string) $config['username'],
                'DB_PASSWORD' => (string) $config['password'],
                'DB_URL' => '',
            ];

            for ($i = 0; $i < 2; $i++) {
                $marker = tempnam(sys_get_temp_dir(), 'elive_variant_');
                unlink($marker);
                $markers[] = $marker;
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/merchandise_checkout_worker.php'),
                    $urlPath,
                    $marker,
                    (string) $variant->id,
                ], base_path(), $env);
                $process->setTimeout(20);
                $process->start();
                $workers[] = $process;
            }

            $deadline = microtime(true) + 10;
            while (microtime(true) < $deadline && count(array_filter($markers, 'file_exists')) < 2) {
                usleep(25000);
            }
            $this->assertCount(2, array_filter($markers, 'file_exists'), 'Both variant checkout workers must start.');

            DB::commit();
            $lockHeld = false;

            $statuses = [];
            foreach ($workers as $process) {
                $process->wait();
                $output = $process->getOutput();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $output);
                preg_match('/HTTP_STATUS:(\\d+)/', $output, $matches);
                $this->assertNotEmpty($matches, $output);
                $statuses[] = (int) $matches[1];
            }

            sort($statuses);
            $this->assertSame([302, 422], $statuses);
            $this->assertSame(1, $variant->fresh()->stock_quantity);
            $this->assertSame(0, $variant->fresh()->remainingQuantity());
            $this->assertSame(1, MerchandiseOrder::query()->where('merchandise_variant_id', $variant->id)->count());
        } finally {
            if ($lockHeld) {
                DB::rollBack();
            }
            foreach ($workers as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            foreach ($markers as $marker) {
                @unlink($marker);
            }
            MerchandiseOrder::query()->where('merchandise_product_id', $product->id)->delete();
            $product->delete();
            $variant->delete();
            $item->delete();
            $event->delete();
            $organization->delete();
        }
    }

}
