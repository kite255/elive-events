<?php

namespace App\Http\Controllers;

use App\Models\Attendee;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PublicAttendeeController extends Controller
{
    public function show(string $token): View|RedirectResponse
    {
        $attendee = Attendee::query()
            ->where('public_token', $token)
            ->with([
                'event.organization',
                'category',
                'badgeType',
                'qrToken',
                'registrationAnswers',
            ])
            ->firstOrFail();

        $event = $attendee->event;

        // Public registration badges must not be released before required payment.
        if ($event && in_array($attendee->registration_source, ['public', 'public_form', 'public_registration'], true)) {
            $settings = $event->paymentSetting;
            $requiresPayment = $settings?->payments_enabled
                && (float) $settings->registration_fee > 0;

            $paid = ! $requiresPayment || Payment::query()
                ->where('event_id', $event->id)
                ->where('attendee_id', $attendee->id)
                ->whereNull('ticket_order_id')
                ->whereNull('ticket_upgrade_id')
                ->whereNull('donation_id')
                ->whereNull('merchandise_order_id')
                ->where('status', Payment::STATUS_COMPLETED)
                ->exists();

            if (! $attendee->isApproved() || ! $paid) {
                return redirect()->route('public.registration.success', [
                    'event' => $event->slug,
                    'attendee' => $attendee->public_token,
                ]);
            }
        }

        return view('public.attendees.show', [
            'attendee' => $attendee,
            'event' => $event,
            'branding' => $this->branding($event),
        ]);
    }

    protected function branding($event): array
    {
        $organization = $event?->organization;

        return [
            'logo' => $event?->registration_logo_path ?: $organization?->logo_path,
            'banner' => $event?->registration_banner_image_path,

            'primary_color' => $event?->registration_primary_color
                ?: $organization?->primary_color
                ?: '#161943',

            'background_color' => $event?->registration_background_color
                ?: $organization?->background_color
                ?: '#F8FAFC',

            'button_color' => $event?->registration_button_color
                ?: $organization?->button_color
                ?: '#161943',

            'support_email' => $organization?->support_email ?: $organization?->email,
            'support_phone' => $organization?->support_phone ?: $organization?->phone,
        ];
    }
}