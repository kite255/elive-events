<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\PaymentGateway;
use App\Models\TicketType;
use App\Services\Payments\PaymentFulfillmentService;
use App\Services\Payments\PaymentService;
use App\Services\Tickets\TicketAvailabilityService;
use App\Services\Tickets\TicketOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicTicketController extends Controller
{
    public function __construct(
        protected TicketAvailabilityService $availabilityService,
        protected TicketOrderService $ticketOrderService,
        protected PaymentService $paymentService,
        protected PaymentFulfillmentService $paymentFulfillmentService
    ) {
    }

    /**
     * Show the public ticket purchase page.
     */
    public function show(
        string $event
    ): View {
        $event = $this->findPublicEvent(
            $event
        );

        $settings =
            $event->ticketSetting;

        abort_if(
            ! $settings
            || ! $settings->ticket_sales_enabled,
            404
        );

        abort_if(
            ! $settings->salesAreOpen(),
            404
        );

        $ticketTypes =
            $event->ticketTypes()
                ->onSale()
                ->orderBy('sort_order')
                ->orderBy('price')
                ->orderBy('id')
                ->get()
                ->map(
                    function ($ticketType) use (
                        $settings
                    ) {
                        $available =
                            $this
                                ->availabilityService
                                ->availableQuantity(
                                    $ticketType
                                );

                        $typeMax =
                            max(
                                1,
                                (int) $ticketType
                                    ->max_per_order
                            );

                        $eventMax =
                            $settings
                                ->maxTicketsPerOrder();

                        $purchaseMax =
                            min(
                                $typeMax,
                                $eventMax
                            );

                        if ($available !== null) {
                            $purchaseMax =
                                min(
                                    $purchaseMax,
                                    $available
                                );
                        }

                        $ticketType->setAttribute(
                            'available_quantity',
                            $available
                        );

                        $ticketType->setAttribute(
                            'public_purchase_max',
                            max(
                                0,
                                $purchaseMax
                            )
                        );

                        return $ticketType;
                    }
                );

        return view(
            'public.tickets.buy',
            [
                'event' =>
                    $event,

                'ticketTypes' =>
                    $ticketTypes,

                'ticketSettings' =>
                    $settings,

                'reservationMinutes' =>
                    $settings
                        ->reservationMinutes(),
            ]
        );
    }

    /**
     * Create a public ticket reservation and payment.
     */
    public function store(
        Request $request,
        string $event
    ): RedirectResponse {
        $event = $this->findPublicEvent(
            $event
        );

        $settings =
            $event->ticketSetting;

        abort_if(
            ! $settings
            || ! $settings->ticket_sales_enabled,
            404
        );

        abort_if(
            ! $settings->salesAreOpen(),
            404
        );

        $validated =
            $request->validate([
                'buyer_name' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'buyer_phone' => [
                    'required_without:buyer_email',
                    'nullable',
                    'string',
                    'max:30',
                    'regex:/^\+?[0-9][0-9\s().-]{7,29}$/',
                ],

                'buyer_email' => [
                    'required_without:buyer_phone',
                    'nullable',
                    'email',
                    'max:255',
                ],

                'accept_terms' => [
                    'required',
                    'accepted',
                ],

                'tickets' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'tickets.*' => [
                    'nullable',
                    'integer',
                    'min:0',
                ],
            ]);

        /*
         * Convert the public form structure:
         *
         * tickets[12] = 2
         *
         * into the structure expected by
         * TicketOrderService:
         *
         * [
         *     [
         *         'ticket_type_id' => 12,
         *         'quantity' => 2,
         *     ],
         * ]
         */
        $items =
            collect(
                $validated['tickets']
            )
                ->map(
                    function (
                        $quantity,
                        $ticketTypeId
                    ): array {
                        return [
                            'ticket_type_id' =>
                                (int) $ticketTypeId,

                            'quantity' =>
                                (int) $quantity,
                        ];
                    }
                )
                ->filter(
                    fn (array $item): bool =>
                        $item['ticket_type_id'] > 0
                        && $item['quantity'] > 0
                )
                ->values()
                ->all();

        if (empty($items)) {
            return back()
                ->withInput()
                ->withErrors([
                    'tickets' =>
                        'Select at least one ticket.',
                ]);
        }

        // Normalize supported Tanzanian mobile numbers, preserving international ones.
        $phoneInput = trim((string) ($validated['buyer_phone'] ?? ''));
        $phoneDigits = preg_replace('/[^0-9]/', '', $phoneInput) ?? '';
        $normalizedPhone = match (true) {
            preg_match('/^0[0-9]{9}$/', $phoneDigits) === 1 => '255'.substr($phoneDigits, 1),
            preg_match('/^[0-9]{9}$/', $phoneDigits) === 1 => '255'.$phoneDigits,
            preg_match('/^255[0-9]{9}$/', $phoneDigits) === 1 => $phoneDigits,
            default => null,
        };

        // Match PaymentService gateway selection: organization default, then global default.
        $defaultGateway = PaymentGateway::query()
            ->where('is_enabled', true)
            ->where('is_default', true)
            ->where(fn ($query) => $query
                ->where('organization_id', $event->organization_id)
                ->orWhereNull('organization_id'))
            ->orderByRaw('CASE WHEN organization_id = ? THEN 0 ELSE 1 END', [(int) $event->organization_id])
            ->first();

        $containsPaidTickets = TicketType::query()
            ->where('event_id', $event->getKey())
            ->whereIn('id', collect($items)->pluck('ticket_type_id'))
            ->where('price', '>', 0)
            ->exists();

        if ($containsPaidTickets && $defaultGateway?->code === 'selcom') {
            $errors = [];
            if (blank($validated['buyer_email'] ?? null)) {
                $errors['buyer_email'] = 'Email is required for Selcom paid ticket checkout.';
            }
            if ($normalizedPhone === null || preg_match('/^255[0-9]{9}$/', $normalizedPhone) !== 1) {
                $errors['buyer_phone'] = 'Enter a valid Tanzanian phone number, such as 0712345678.';
            }
            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        $order =
            $this->ticketOrderService
                ->createOrder(
                    event: $event,

                    buyer: [
                        'name' =>
                            $validated[
                                'buyer_name'
                            ],

                        'phone' =>
                            $normalizedPhone ?? ($validated['buyer_phone'] ?? null),

                        'email' =>
                            $validated[
                                'buyer_email'
                            ] ?? null,
                    ],

                    items: $items
                );

        // Audit the exact version of terms accepted at checkout.
        $order->update([
            'metadata' => array_merge($order->metadata ?? [], [
                'purchase_terms_accepted_at' => now()->toIso8601String(),
                'purchase_terms_version' => \App\Support\TicketPurchaseTerms::CURRENT_VERSION,
                'purchase_terms_sha256' => \App\Support\TicketPurchaseTerms::snapshotHash(),
                'purchase_terms_archive_url' => route('public.terms.version', ['version' => \App\Support\TicketPurchaseTerms::CURRENT_VERSION]),
            ]),
        ]);

        $payment =
            $this->paymentService
                ->createForTicketOrder(
                    $order
                );

        if ($payment->isCompleted()) {
            $this->paymentFulfillmentService
                ->fulfill(
                    $payment
                );

            return redirect()
                ->route(
                    'public.ticket-orders.show',
                    [
                        'token' =>
                            $order->public_token,
                    ]
                )
                ->with(
                    'success',
                    'Your free tickets are ready.'
                );
        }

        return redirect()
            ->route(
                'payments.pay',
                $payment
            );
    }

    /**
     * Resolve a public active event by slug.
     */
    private function findPublicEvent(
        string $event
    ): Event {
        return Event::query()
            ->with([
                'organization',
                'ticketSetting',
            ])
            ->where(
                'slug',
                $event
            )
            ->where(
                'status',
                Event::STATUS_ACTIVE
            )
            ->firstOrFail();
    }
}
