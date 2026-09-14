<?php

use App\Http\Controllers\BadgePrintController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\EventCommunicationPreviewController;
use App\Http\Controllers\Payments\PaymentController;
use App\Http\Controllers\Payments\PesapalCallbackController;
use App\Http\Controllers\Payments\PesapalIpnController;
use App\Http\Controllers\PublicAttendeeController;
use App\Http\Controllers\PublicEventCommunicationController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\PublicTicketController;
use App\Http\Controllers\PublicTicketOrderController;
use App\Http\Controllers\PublicTicketRecoveryController;
use App\Http\Controllers\PublicTicketViewController;
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

Route::view(
    '/',
    'welcome'
)->name(
    'home'
);

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

Route::get(
    '/events',
    function () {
        return view(
            'public.events.index'
        );
    }
)->name(
    'public.events.index'
);

/*
|--------------------------------------------------------------------------
| Public Event Communications
|--------------------------------------------------------------------------
|
| Reusable public pages for:
| - Today's Highlights
| - Announcements
| - Event Updates
| - Schedule / Program
| - Reminders
| - Notices
| - Custom Communications
|
| Example:
| /events/dcc-camp-meeting-2026/communications/todays-highlights-23-august-2026
|
| This route MUST stay above the generic /events/{event:slug} route.
|
*/

Route::get(
    '/events/{event:slug}/communications/{communication:slug}',
    PublicEventCommunicationController::class
)->name(
    'public.event-communications.show'
);

/*
|--------------------------------------------------------------------------
| Public Ticket Sales
|--------------------------------------------------------------------------
|
| Public ticket-selection and checkout page.
|
| Example:
| /events/dcc-concert-2026/tickets
|
| GET:
| - show event ticket types
| - show real-time availability
| - show ticket prices
| - show buyer checkout form
|
| POST:
| - validate buyer details
| - validate ticket quantities
| - create temporary ticket reservation
| - create pending payment
| - redirect buyer to payment checkout
|
| These routes MUST stay above the generic /events/{event:slug} route.
|
*/

Route::get(
    '/events/{event}/tickets',
    [
        PublicTicketController::class,
        'show',
    ]
)
    ->middleware(
        'throttle:120,1'
    )
    ->name(
        'public.tickets.buy'
    );

Route::post(
    '/events/{event}/tickets',
    [
        PublicTicketController::class,
        'store',
    ]
)
    ->middleware(
        'throttle:20,1'
    )
    ->name(
        'public.tickets.store'
    );

/*
|--------------------------------------------------------------------------
| Public Ticket Order / My Tickets
|--------------------------------------------------------------------------
|
| Secure ticket-buyer access page.
|
| Example:
| /events/tickets/order/AbCdEf123456...
|
| The URL uses TicketOrder.public_token instead of the numeric database ID.
|
| Only PAID ticket orders are exposed by the controller.
|
| The page shows:
| - event details
| - order number
| - buyer details
| - total paid
| - issued tickets
|
| QR secrets and QR hashes must never be exposed on this page.
|
| This route MUST stay above the generic /events/{event:slug} route.
|
*/

Route::get(
    '/events/tickets/order/{token}',
    [
        PublicTicketOrderController::class,
        'show',
    ]
)
    ->middleware(
        'throttle:120,1'
    )
    ->name(
        'public.ticket-orders.show'
    );

/*
|--------------------------------------------------------------------------
| Public Individual Ticket
|--------------------------------------------------------------------------
|
| Secure individual public ticket page.
|
| Example:
| /tickets/AbCdEf123456...
|
| The URL uses Ticket.public_token instead of the numeric database ID.
|
| The controller:
| - loads the ticket using its public token
| - verifies the ticket is not cancelled or refunded
| - verifies the parent order is paid
| - decrypts qr_token_encrypted
| - renders the secure QR code
|
| The QR payload itself contains only the secure raw scan credential.
|
| It must never contain:
| - ticket database ID
| - Ticket.public_token
| - qr_token_hash
|
*/

Route::get(
    '/tickets/{token}',
    [
        PublicTicketViewController::class,
        'show',
    ]
)
    ->middleware(
        'throttle:120,1'
    )
    ->name(
        'public.tickets.show'
    );

/*
|--------------------------------------------------------------------------
| Authenticated Event Communication Preview
|--------------------------------------------------------------------------
|
| Used by Filament:
| Events -> Event Communications -> Preview
|
| Example:
| /admin/event-communications/12/preview
|
| This route is protected by authentication. The preview controller performs
| the event-access authorization check before rendering the communication.
|
*/

Route::get(
    '/admin/event-communications/{communication}/preview',
    EventCommunicationPreviewController::class
)
    ->middleware([
        'auth',
    ])
    ->name(
        'admin.event-communications.preview'
    );

/*
|--------------------------------------------------------------------------
| Online Payments
|--------------------------------------------------------------------------
|
| Public payment entry point used after registration or ticket checkout.
|
| The payment route uses the eLive payment reference instead of the numeric
| database ID so public checkout URLs do not expose sequential record IDs.
|
| Example:
| /payments/ELV-PAY-EBC26-260826123456-ABC123/pay
|
*/

Route::get(
    '/payments/{payment:reference}/pay',
    [
        PaymentController::class,
        'pay',
    ]
)
    ->middleware(
        'throttle:30,1'
    )
    ->name(
        'payments.pay'
    );

/*
|--------------------------------------------------------------------------
| Public Payment Status
|--------------------------------------------------------------------------
|
| Public attendee/ticket-buyer payment result page.
|
| This page can display:
| - successful payments
| - payments still processing
| - failed payments
| - cancelled payments
|
| It uses the eLive payment reference instead of the numeric database ID.
|
| Example:
| /payments/ELV-PAY-EBC26-260826123456-ABC123/status
|
*/

Route::get(
    '/payments/{payment:reference}/status',
    [
        PaymentController::class,
        'status',
    ]
)
    ->middleware(
        'throttle:60,1'
    )
    ->name(
        'payments.status'
    );

/*
|--------------------------------------------------------------------------
| Pesapal Browser Callback
|--------------------------------------------------------------------------
|
| Pesapal redirects the attendee/ticket buyer's browser here after checkout.
|
| IMPORTANT:
| The callback itself is not proof of payment. The controller verifies the
| transaction directly with Pesapal before updating the eLive payment.
|
| After verification, the customer is redirected to the branded public
| payment status page.
|
| Production URL:
| https://events.elive.co.tz/payments/pesapal/callback
|
*/

Route::get(
    '/payments/pesapal/callback',
    PesapalCallbackController::class
)->name(
    'payments.pesapal.callback'
);

/*
|--------------------------------------------------------------------------
| Pesapal IPN
|--------------------------------------------------------------------------
|
| Server-to-server Instant Payment Notification endpoint.
|
| Pesapal may call this endpoint using GET or POST depending on the IPN
| registration configuration. The controller accepts both methods and then
| verifies the transaction using the Pesapal transaction-status endpoint.
|
| IMPORTANT:
| If POST IPN is used, this URI must remain excluded from Laravel CSRF
| verification.
|
| Production URL:
| https://events.elive.co.tz/payments/pesapal/ipn
|
*/

Route::match(
    [
        'get',
        'post',
    ],
    '/payments/pesapal/ipn',
    PesapalIpnController::class
)->name(
    'payments.pesapal.ipn'
);

/*
|--------------------------------------------------------------------------
| Public Ticket Recovery / Find My Tickets
|--------------------------------------------------------------------------
|
| Allows a paid ticket buyer to request their existing secure My Tickets
| link using the order number plus the email address or phone number used
| during checkout.
|
| IMPORTANT:
| These routes MUST stay above the generic /events/{event:slug} route.
|
| The POST endpoint always returns a generic response to prevent attackers
| from discovering whether a ticket order or buyer contact exists.
|
*/

Route::get(
    '/events/tickets/find',
    [
        PublicTicketRecoveryController::class,
        'show',
    ]
)
    ->middleware(
        'throttle:30,10'
    )
    ->name(
        'public.ticket-recovery.show'
    );

Route::post(
    '/events/tickets/find',
    [
        PublicTicketRecoveryController::class,
        'store',
    ]
)
    ->middleware(
        'throttle:5,10'
    )
    ->name(
        'public.ticket-recovery.store'
    );

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

Route::get(
    '/events/{event:slug}',
    function (
        Event $event
    ) {
        abort_if(
            in_array(
                $event->status,
                [
                    'draft',
                    'cancelled',
                ],
                true
            ),
            404
        );

        return view(
            'public.events.show',
            [
                'event' =>
                    $event,
            ]
        );
    }
)->name(
    'public.events.show'
);

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
    [
        PublicRegistrationController::class,
        'show',
    ]
)->name(
    'public.registration.show'
);

Route::post(
    '/register/{event:slug}',
    [
        PublicRegistrationController::class,
        'store',
    ]
)->name(
    'public.registration.store'
);

Route::get(
    '/register/{event:slug}/success/{attendee}',
    [
        PublicRegistrationController::class,
        'success',
    ]
)->name(
    'public.registration.success'
);

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
    [
        PublicAttendeeController::class,
        'show',
    ]
)->name(
    'public.attendees.show'
);

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
    [
        PublicRegistrationController::class,
        'show',
    ]
)->name(
    'public.events.register'
);

Route::post(
    '/events/{event:slug}/register',
    [
        PublicRegistrationController::class,
        'store',
    ]
)->name(
    'public.events.register.store'
);

Route::get(
    '/events/{event:slug}/register/success/{attendee}',
    [
        PublicRegistrationController::class,
        'success',
    ]
)->name(
    'public.events.register.success'
);

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
    [
        QrVerificationController::class,
        'show',
    ]
)->name(
    'qr.verify'
);

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
    [
        CheckInController::class,
        'show',
    ]
)->name(
    'qr.check-in'
);

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
    ->middleware([
        'auth',
    ])
    ->name(
        'badges.print'
    );
