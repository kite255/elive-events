<?php
// Runs only against the disposable local PostgreSQL test database.
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'pgsql'
    || getenv('DB_HOST') !== '127.0.0.1' || ! in_array(getenv('DB_PORT'), ['5433', '5435'], true)
    || getenv('DB_DATABASE') !== 'elive_merchandise_test') {
    fwrite(STDERR, "Unsafe database configuration.\n");
    exit(2);
}

[$script, $path, $marker, $itemId, $variantId] = array_pad($argv, 5, null);
if (! str_starts_with((string) $path, '/register/') || ! $marker || ! $itemId || ! $variantId) {
    exit(2);
}

file_put_contents($marker, 'started');
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$request = Request::create($path, 'POST', [
    'full_name' => 'Race Registration ' . bin2hex(random_bytes(4)),
    'merchandise' => [
        (int) $itemId => [
            'selected' => '1',
            'variant_id' => (int) $variantId,
            'quantity' => 1,
        ],
    ],
], [], [], ['HTTP_ACCEPT' => 'application/json']);
$app->instance('request', $request);
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

if (config('database.default') !== 'pgsql'
    || config('database.connections.pgsql.database') !== 'elive_merchandise_test'
    || ! in_array((string) config('database.connections.pgsql.port'), ['5433', '5435'], true)) {
    fwrite(STDERR, "Actual DB configuration mismatch.\n");
    exit(2);
}

file_put_contents($marker, 'ready');
$response = $kernel->handle($request);
echo 'HTTP_STATUS:' . $response->getStatusCode() . PHP_EOL;
echo 'LOCATION:' . ($response->headers->get('Location') ?? '') . PHP_EOL;
$kernel->terminate($request, $response);
