<?php

use App\Http\Controllers\BadgePrintController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\PublicAttendeeController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\QrVerificationController;
use App\Models\Event;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Homepage
|--------------------------------------------------------------------------
|
| Main eLive Events landing page:
| https://events.elive.co.tz
|
*/

Route::view('/', 'welcome')
    ->name('home');

/*
|--------------------------------------------------------------------------
| Public Events Directory
|--------------------------------------------------------------------------
|
| Main public events page:
|
| /events
|
| This page shows:
| - Happening Now
| - Upcoming Events
| - Past Events
| - Search
| - Event filters
|
*/

Route::get('/events', function () {
    return view('public.events.index');
})->name('public.events.index');

/*
|--------------------------------------------------------------------------
| Public Event Details
|--------------------------------------------------------------------------
|
| Public event information page:
|
| /events/{event_slug}
|
| Example:
| /events/dcc-camp-meeting
|
*/

Route::get('/events/{event:slug}', function (Event $event) {
    abort_if(
        in_array($event->status, ['draft', 'cancelled'], true),
        404
    );

    return view('public.events.show', [
        'event' => $event,
    ]);
})->name('public.events.show');

/*
|--------------------------------------------------------------------------
| Public Event Registration
|--------------------------------------------------------------------------
|
| Primary public registration link:
|
| /register/{event_slug}
|
| Example:
| /register/elive-launch-conference
|
*/

Route::get(
    '/register/{event:slug}',
    [PublicRegistrationController::class, 'show']
)->name('public.registration.show');

Route::post(
    '/register/{event:slug}',
    [PublicRegistrationController::class, 'store']
)->name('public.registration.store');

Route::get(
    '/register/{event:slug}/success/{attendee}',
    [PublicRegistrationController::class, 'success']
)->name('public.registration.success');

/*
|--------------------------------------------------------------------------
| Public Attendee Badge / Confirmation Page
|--------------------------------------------------------------------------
|
| Attendee self-service link:
|
| /a/{public_token}
|
| Example:
| /a/lc2MfwdjNdOIrZjOmO9oH8j4Rlwpjkr9
|
*/

Route::get(
    '/a/{token}',
    [PublicAttendeeController::class, 'show']
)->name('public.attendees.show');

/*
|--------------------------------------------------------------------------
| Event Registration URL
|--------------------------------------------------------------------------
|
| Event-directory registration URL:
|
| /events/{event_slug}/register
|
| Example:
| /events/dcc-camp-meeting/register
|
*/

Route::get(
    '/events/{event:slug}/register',
    [PublicRegistrationController::class, 'show']
)->name('public.events.register');

Route::post(
    '/events/{event:slug}/register',
    [PublicRegistrationController::class, 'store']
)->name('public.events.register.store');

Route::get(
    '/events/{event:slug}/register/success/{attendee}',
    [PublicRegistrationController::class, 'success']
)->name('public.events.register.success');

/*
|--------------------------------------------------------------------------
| QR Verification
|--------------------------------------------------------------------------
|
| Public QR verification page.
|
| Example:
| /verify/{secure_token}
|
*/

Route::get(
    '/verify/{token}',
    [QrVerificationController::class, 'show']
)->name('qr.verify');

/*
|--------------------------------------------------------------------------
| QR Check-in
|--------------------------------------------------------------------------
|
| Used for attendee QR check-in.
|
| Example:
| /check-in/{secure_token}
|
*/

Route::get(
    '/check-in/{token}',
    [CheckInController::class, 'show']
)->name('qr.check-in');

/*
|--------------------------------------------------------------------------
| Badge Printing
|--------------------------------------------------------------------------
|
| Protected admin badge-printing endpoint.
|
*/

Route::get(
    '/admin/badges/print',
    BadgePrintController::class
)
    ->middleware(['auth'])
    ->name('badges.print');
