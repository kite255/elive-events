<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // A shared reverse-proxy IP must not make unrelated orders exhaust
        // the same checkout-view quota. Never use this limiter to submit money.
        RateLimiter::for('payment-checkout-view', function (Request $request): Limit {
            $payment = $request->route('payment');
            $reference = $payment instanceof \App\Models\Payment
                ? $payment->reference
                : (string) $payment;

            return Limit::perMinute(60)->by(hash('sha256', $request->ip() . '|' . $reference));
        });

        if (config('app.env') !== 'local') {
            URL::forceScheme('https');
        }
    }
}