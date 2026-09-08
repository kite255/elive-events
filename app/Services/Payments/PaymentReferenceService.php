<?php

namespace App\Services\Payments;

use App\Models\Event;
use App\Models\Payment;
use Illuminate\Support\Str;

class PaymentReferenceService
{
    /**
     * Generate a unique eLive merchant reference
     * suitable for Pesapal API 3.0.
     */
    public function generate(Event $event): string
    {
        do {
            $eventCode = filled($event->event_code)
                ? Str::upper(
                    preg_replace(
                        '/[^A-Za-z0-9_-]/',
                        '',
                        (string) $event->event_code
                    )
                )
                : 'EV' . $event->getKey();

            $eventCode = Str::limit(
                $eventCode,
                12,
                ''
            );

            $reference = sprintf(
                'ELV-PAY-%s-%s-%s',
                $eventCode,
                now()->format('ymdHis'),
                Str::upper(Str::random(6))
            );

            $reference = Str::limit(
                $reference,
                50,
                ''
            );
        } while (
            Payment::query()
                ->where('reference', $reference)
                ->exists()
        );

        return $reference;
    }
}