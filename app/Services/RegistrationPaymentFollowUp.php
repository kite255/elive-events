<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Payment;
use App\Models\User;

class RegistrationPaymentFollowUp
{
    public function allows(Attendee $attendee, ?User $user): bool
    {
        $attendee->loadMissing('event.paymentSetting');
        $event = $attendee->event;

        return $user !== null
            && $event !== null
            && ! $user->is_gate_staff
            && $event->isAccessibleBy($user)
            && $event->canManageRegistrationBy($user)
            && $event->isActive()
            && $attendee->isApproved()
            && filled($attendee->public_token)
            && in_array($attendee->registration_source, ['public', 'public_form', 'public_registration'], true)
            && $event->paymentSetting?->payments_enabled
            && (float) $event->paymentSetting->registration_fee > 0
            && ! Payment::query()
                ->where('event_id', $event->id)
                ->where('attendee_id', $attendee->id)
                ->whereNull('ticket_order_id')
                ->whereNull('ticket_upgrade_id')
                ->whereNull('donation_id')
                ->whereNull('merchandise_order_id')
                ->where('status', Payment::STATUS_COMPLETED)
                ->exists();
    }

    public function url(Attendee $attendee, ?User $user): string
    {
        abort_unless($this->allows($attendee, $user), 403);

        return route('public.registration.success', [
            'event' => $attendee->event->slug,
            'attendee' => $attendee->public_token,
        ]);
    }
}
