<?php

use App\Http\Controllers\PublicEventsApiController;
use Illuminate\Support\Facades\Route;

Route::get('/public/events', PublicEventsApiController::class)
    ->middleware('throttle:120,1')
    ->name('api.public.events');
