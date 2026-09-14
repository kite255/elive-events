<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\Payments\PaymentService;
use App\Services\Tickets\TicketAvailabilityService;
use App\Services\Tickets\TicketOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicTicketController extends Controller
{
    public function __construct(
        protected TicketAvailabilityService $availabilityService,
        protected TicketOrderService $ticketOrderService,
        protected PaymentService $paymentService
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
                    'nullable',
                    'string',
                    'max:30',
                ],

                'buyer_email' => [
                    'nullable',
                    'email',
                    'max:255',
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
                            $validated[
                                'buyer_phone'
                            ] ?? null,

                        'email' =>
                            $validated[
                                'buyer_email'
                            ] ?? null,
                    ],

                    items: $items
                );

        $payment =
            $this->paymentService
                ->createForTicketOrder(
                    $order
                );

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