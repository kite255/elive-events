<?php

namespace App\Services\Payments;

use App\Models\Event;
use App\Models\Payment;
use Illuminate\Support\Str;

class PaymentReferenceService
{
    /**
     * Generate an eLive merchant reference accepted by Pesapal API 3.0.
     *
     * Pesapal allows letters, numbers, dash, underscore, dot and colon,
     * with a maximum length of 50 characters.
     */
    public function generate(
        Event $event
    ): string {
        do {
            $eventCode =
                filled($event->event_code)
                    ? Str::upper(
                        preg_replace(
                            '/[^A-Za-z0-9_-]/',
                            '',
                            (string) $event->event_code
                        )
                    )
                    : 'EV' . $event->getKey();

            $reference =
                'ELV-PAY-'
                . $eventCode
                . '-'
                . now()->format('ymdHis')
                . '-'
                . Str::upper(
                    Str::random(6)
                );

            $reference =
                Str::limit(
                    $reference,
                    50,
                    ''
                );
        } while (
            Payment::query()
                ->where(
                    'reference',
                    $reference
                )
                ->exists()
        );

        return $reference;
    }
}
