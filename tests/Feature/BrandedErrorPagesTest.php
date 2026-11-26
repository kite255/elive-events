<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BrandedErrorPagesTest extends TestCase
{
    public function test_browser_errors_are_branded_and_preserve_status_codes(): void
    {
        config(['app.debug' => false]);

        foreach ([401, 403, 404, 419, 429, 500, 503, 405, 502] as $status) {
            Route::get("/_test/branded-error/{$status}", fn () => abort($status, 'Private diagnostic message'));

            $this->get("/_test/branded-error/{$status}")
                ->assertStatus($status)
                ->assertSee('eLive Events')
                ->assertSee('Back to homepage')
                ->assertSee('Contact support')
                ->assertDontSee('Private diagnostic message');
        }
    }

    public function test_json_errors_remain_json(): void
    {
        config(['app.debug' => false]);

        Route::get('/_test/branded-error/json', fn () => abort(404));

        $this->getJson('/_test/branded-error/json')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
