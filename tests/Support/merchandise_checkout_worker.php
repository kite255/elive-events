<?php

// Test-only subprocess. Never point this at a non-disposable database.
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$expectedDatabase = 'elive_merchandise_test';
if (getenv('APP_ENV') !== 'testing' ||
    getenv('DB_CONNECTION') !== 'pgsql' ||
    getenv('DB_DATABASE') !== $expectedDatabase ||
    getenv('DB_HOST') !== '127.0.0.1' ||
    getenv('DB_PORT') !== '5433') {
    fwrite(STDERR, "Unsafe database configuration.\n");
    exit(2);
}

$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

if (config('database.default') !== 'pgsql' ||
    config('database.connections.pgsql.database') !== $expectedDatabase) {
    fwrite(STDERR, "Actual database configuration mismatch.\n");
    exit(2);
}

$path = $argv[1] ?? '';
$marker = $argv[2] ?? '';
if (! str_starts_with($path, '/events/') || ! str_contains($path, '/merchandise/') || ! $marker) {
    exit(2);
}

$request = Request::create($path, 'POST', [
    'buyer_name' => 'Concurrent Buyer',
    'buyer_phone' => '255700000000',
    'quantity' => 1,
    'fulfillment_method' => 'event_pickup',
], [], [], ['HTTP_ACCEPT' => 'application/json']);

file_put_contents($marker, 'ready');
$response = $kernel->handle($request);
echo 'HTTP_STATUS:' . $response->getStatusCode() . PHP_EOL;
$kernel->terminate($request, $response);
