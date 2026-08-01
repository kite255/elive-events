<?php

use App\Http\Controllers\BadgePrintController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\PublicAttendeeController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\QrVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Public Event Registration
|--------------------------------------------------------------------------
|
| Recommended public link:
| /register/{event_slug}
|
| Example:
| /register/elive-launch-conference
|
*/

Route::get('/register/{event:slug}', [PublicRegistrationController::class, 'show'])
    ->name('public.registration.show');

Route::post('/register/{event:slug}', [PublicRegistrationController::class, 'store'])
    ->name('public.registration.store');

Route::get('/register/{event:slug}/success/{attendee}', [PublicRegistrationController::class, 'success'])
    ->name('public.registration.success');

/*
|--------------------------------------------------------------------------
| Public Attendee Badge / Confirmation Page
|--------------------------------------------------------------------------
|
| Attendee self-service link:
| /a/{public_token}
|
| Example:
| /a/lc2MfwdjNdOIrZjOmO9oH8j4Rlwpjkr9
|
*/

Route::get('/a/{token}', [PublicAttendeeController::class, 'show'])
    ->name('public.attendees.show');

/*
|--------------------------------------------------------------------------
| Alternative Event Registration URL
|--------------------------------------------------------------------------
|
| This keeps your current URL style working:
| /events/{event_slug}/register
|
*/

Route::get('/events/{event:slug}/register', [PublicRegistrationController::class, 'show'])
    ->name('public.events.register');

Route::post('/events/{event:slug}/register', [PublicRegistrationController::class, 'store'])
    ->name('public.events.register.store');

Route::get('/events/{event:slug}/register/success/{attendee}', [PublicRegistrationController::class, 'success'])
    ->name('public.events.register.success');

/*
|--------------------------------------------------------------------------
| QR Verification and Check-in
|--------------------------------------------------------------------------
*/

Route::get('/verify/{token}', [QrVerificationController::class, 'show'])
    ->name('qr.verify');

Route::get('/check-in/{token}', [CheckInController::class, 'show'])
    ->name('qr.check-in');

/*
|--------------------------------------------------------------------------
| Badge Printing
|--------------------------------------------------------------------------
*/

Route::get('/admin/badges/print', BadgePrintController::class)
    ->middleware(['auth'])
    ->name('badges.print');