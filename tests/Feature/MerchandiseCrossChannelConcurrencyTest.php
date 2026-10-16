<?php

namespace Tests\Feature;

use App\Models\AttendeeMerchandise;
use App\Models\Event;
use App\Models\EventMerchandise;
use App\Models\MerchandiseOrder;
use App\Models\MerchandiseProduct;
use App\Models\MerchandiseVariant;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MerchandiseCrossChannelConcurrencyTest extends TestCase
{
    public function test_attendee_registration_and_public_checkout_compete_for_last_variant(): void
    {
        $cfg = config('database.connections.pgsql');
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL integration test only.');
        }
        if (($cfg['host'] ?? '') !== '127.0.0.1'
            || ! in_array((string) ($cfg['port'] ?? ''), ['5433', '5435'], true)
            || ($cfg['database'] ?? '') !== 'elive_merchandise_test') {
            $this->fail('Unsafe database: only the disposable local PostgreSQL test database is permitted.');
        }

        $org = Organization::create(['name' => 'Cross Channel Race ' . Str::random(12)]);
        $event = Event::create([
            'organization_id' => $org->id,
            'name' => 'Cross Channel Event',
            'slug' => 'cross-channel-' . Str::lower(Str::random(12)),
            'status' => 'active',
            'registration_is_open' => true,
            'registration_show_phone' => false,
            'registration_show_email' => false,
            'registration_show_category' => false,
            'registration_show_badge_type' => false,
            'registration_auto_generate_badge' => false,
            'merchandise_section_enabled' => true,
        ]);
        $item = EventMerchandise::create([
            'event_id' => $event->id,
            'name' => 'T-shirt',
            'selection_type' => 'optional',
            'maximum_per_attendee' => 1,
            'is_active' => true,
        ]);
        $variant = MerchandiseVariant::create([
            'event_merchandise_id' => $item->id,
            'name' => 'Standard',
            'stock_quantity' => 1,
            'price' => 10000,
            'currency' => 'TZS',
            'is_active' => true,
        ]);
        $product = MerchandiseProduct::create([
            'event_id' => $event->id,
            'event_merchandise_id' => $item->id,
            'name' => 'T-shirt',
            'slug' => 'shirt',
            'price_tzs' => 10000,
            'is_active' => true,
        ]);

        $workers = [];
        $markers = [];
        $holding = false;
        try {
            DB::beginTransaction();
            $holding = true;
            DB::table('merchandise_variants')->where('id', $variant->id)->lockForUpdate()->first();
            $env = [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => '127.0.0.1',
                'DB_PORT' => (string) $cfg['port'],
                'DB_DATABASE' => 'elive_merchandise_test',
                'DB_USERNAME' => (string) $cfg['username'],
                'DB_PASSWORD' => (string) $cfg['password'],
                'DB_URL' => '',
                'MAIL_MAILER' => 'array',
                'QUEUE_CONNECTION' => 'sync',
                'CACHE_STORE' => 'array',
                'SESSION_DRIVER' => 'array',
            ];
            $work = [
                [base_path('tests/Support/merchandise_checkout_worker.php'),
                    parse_url(route('public.merchandise.store', [$event, $product]), PHP_URL_PATH),
                    (string) $variant->id],
                [base_path('tests/Support/merchandise_registration_worker.php'),
                    parse_url(route('public.registration.store', $event), PHP_URL_PATH),
                    (string) $item->id, (string) $variant->id],
            ];
            foreach ($work as $arguments) {
                $marker = tempnam(sys_get_temp_dir(), 'elive_cross_');
                unlink($marker);
                $markers[] = $marker;
                $process = new Process(array_merge([PHP_BINARY, $arguments[0], $arguments[1], $marker], array_slice($arguments, 2)), base_path(), $env);
                $process->setTimeout(20);
                $process->start();
                $workers[] = $process;
            }
            $deadline = microtime(true) + 10;
            while (microtime(true) < $deadline && count(array_filter($markers, 'file_exists')) < 2) {
                usleep(25000);
            }
            $diagnostics = implode("\n", array_map(fn (Process $p) => $p->getErrorOutput() . $p->getOutput(), $workers));
            $this->assertCount(2, array_filter($markers, 'file_exists'), $diagnostics);

            DB::commit();
            $holding = false;
            foreach ($workers as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());
                $this->assertStringContainsString('HTTP_STATUS:', $process->getOutput(), $process->getErrorOutput());
            }

            $orders = MerchandiseOrder::where('merchandise_variant_id', $variant->id)->sum('quantity');
            $attendees = AttendeeMerchandise::where('merchandise_variant_id', $variant->id)
                ->whereIn('status', ['selected', 'reserved', 'paid', 'distributed'])->sum('quantity');
            $this->assertSame(1, (int) ($orders + $attendees), 'Both channels must not reserve the same final unit.');
            $this->assertSame(0, $variant->fresh()->remainingQuantity());
        } finally {
            if ($holding) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop(1);
                }
            }
            foreach ($markers as $marker) {
                @unlink($marker);
            }
            // This test intentionally leaves any attendee fixture untouched if an
            // unexpected failure occurs; the database is disposable and isolated.
            // Normal success cleans up all related records.
            if (isset($item)) {
                DB::table('attendee_merchandises')->where('event_merchandise_id', $item->id)->delete();
                DB::table('attendees')->where('event_id', $event->id)->delete();
                DB::table('merchandise_orders')->where('merchandise_product_id', $product->id)->delete();
                $product->delete();
                $variant->delete();
                $item->delete();
                $event->delete();
                $org->delete();
            }
        }
    }
}
