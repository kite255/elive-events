<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Payment;

class RegistrationCommunicationEligibility
{
    public function allows(Attendee $attendee): bool
    {
        $attendee->loadMissing('event.paymentSetting');

        if (! $attendee->event || ! $attendee->isApproved()) {
            return false;
        }

        if (! in_array($attendee->registration_source, ['public', 'public_form', 'public_registration'], true)) {
            return true;
        }

        $settings = $attendee->event->paymentSetting;

        if (! $settings?->payments_enabled || (float) $settings->registration_fee <= 0) {
            return true;
        }

        return Payment::query()
            ->where('event_id', $attendee->event_id)
            ->where('attendee_id', $attendee->id)
            ->whereNull('ticket_order_id')
            ->whereNull('ticket_upgrade_id')
            ->whereNull('donation_id')
            ->whereNull('merchandise_order_id')
            ->where('status', Payment::STATUS_COMPLETED)
            ->exists();
    }
}
