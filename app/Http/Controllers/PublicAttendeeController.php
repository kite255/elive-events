<?php

namespace App\Http\Controllers;

use App\Models\Attendee;
use Illuminate\View\View;

class PublicAttendeeController extends Controller
{
    public function show(string $token): View
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