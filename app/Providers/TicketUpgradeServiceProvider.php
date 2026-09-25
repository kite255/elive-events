<?php

namespace App\Providers;

use App\Http\Controllers\PublicTicketUpgradeController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TicketUpgradeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Route::middleware('web')->group(function (): void {
            Route::get(
                '/upgrade/{token}',
                [PublicTicketUpgradeController::class, 'show']
            )
                ->middleware('throttle:120,1')
                ->name('tickets.upgrades.show');

            Route::post(
                '/upgrade/{token}/pay',
                [PublicTicketUpgradeController::class, 'pay']
            )
                ->middleware('throttle:20,1')
                ->name('tickets.upgrades.pay');
        });
    }
}
