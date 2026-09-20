<?php

namespace App\Services;

class EventPresetService
{
    public static function eventTypes(): array
    {
        return [
            'conference' => 'Conference',
            'seminar' => 'Seminar',
            'workshop' => 'Workshop',
            'training' => 'Training',
            'corporate_event' => 'Corporate Event',
            'meeting' => 'Meeting',
            'networking_event' => 'Networking Event',
            'exhibition' => 'Exhibition',
            'expo' => 'Expo',
            'trade_fair' => 'Trade Fair',
            'product_launch' => 'Product Launch',

            'wedding' => 'Wedding',
            'send_off' => 'Send-off',
            'engagement' => 'Engagement',
            'birthday' => 'Birthday',
            'graduation' => 'Graduation',

            'church_event' => 'Church Event',
            'community_event' => 'Community Event',
            'charity_event' => 'Charity / Fundraising Event',

            'bonanza' => 'Bonanza',
            'sports_event' => 'Sports Event',
            'tournament' => 'Tournament',

            'festival' => 'Festival',
            'concert' => 'Concert / Live Performance',
            'cultural_event' => 'Cultural Event',

            'vip_ceremony' => 'VIP Ceremony',
            'government_event' => 'Government / Official Event',

            'webinar' => 'Webinar',
            'hybrid_event' => 'Hybrid Event',

            'other' => 'Other',
        ];
    }

    /**
     * Defines which major modules are relevant by default
     * for each event type.
     *
     * These values control Event form visibility.
     */
    public static function featureProfile(?string $eventType): array
    {
        $default = [
            'ticketing' => false,
            'registration' => true,
            'sessions' => false,
            'professional_fields' => false,
            'badges' => false,
            'guest_rsvp' => false,
        ];

        return match ($eventType) {
            /*
            |--------------------------------------------------------------------------
            | Ticket-first Events
            |--------------------------------------------------------------------------
            */

            'concert' => [
                'ticketing' => true,
                'registration' => false,
                'sessions' => false,
                'professional_fields' => false,
                'badges' => false,
                'guest_rsvp' => false,
            ],

            /*
            |--------------------------------------------------------------------------
            | Registration + Sessions + Professional Details
            |--------------------------------------------------------------------------
            */

            'conference',
            'seminar',
            'workshop',
            'training',
            'corporate_event',
            'meeting',
            'networking_event',
            'product_launch' => [
                'ticketing' => false,
                'registration' => true,
                'sessions' => true,
                'professional_fields' => true,
                'badges' => true,
                'guest_rsvp' => false,
            ],

            /*
            |--------------------------------------------------------------------------
            | Exhibition / Expo / Trade Fair
            |--------------------------------------------------------------------------
            |
            | These events can naturally combine registration,
            | exhibitor management, sessions, and paid admission.
            |
            */

            'exhibition',
            'expo',
            'trade_fair' => [
                'ticketing' => true,
                'registration' => true,
                'sessions' => true,
                'professional_fields' => true,
                'badges' => true,
                'guest_rsvp' => false,
            ],

            /*
            |--------------------------------------------------------------------------
            | Festival / Cultural Events
            |--------------------------------------------------------------------------
            */

            'festival',
            'cultural_event' => [
                'ticketing' => true,
                'registration' => false,
                'sessions' => true,
                'professional_fields' => false,
                'badges' => false,
                'guest_rsvp' => false,
            ],

            /*
            |--------------------------------------------------------------------------
            | Weddings / Social Events
            |--------------------------------------------------------------------------
            */

            'wedding',
            'send_off',
            'engagement',
            'birthday' => [
                'ticketing' => false,
                'registration' => true,
                'sessions' => false,
                'professional_fields' => false,
                'badges' => false,
                'guest_rsvp' => true,
            ],

            /*
            |--------------------------------------------------------------------------
            | Church / Community Events
            |--------------------------------------------------------------------------
            */

            'church_event',
            'community_event',
            'charity_event' => [
                'ticketing' => false,
                'registration' => true,
                'sessions' => true,
                'professional_fields' => false,
                'badges' => true,
                'guest_rsvp' => false,
            ],

            /*
            |--------------------------------------------------------------------------
            | Sports Events
            |--------------------------------------------------------------------------
            */

            'bonanza',
            'sports_event',
            'tournament' => [
                'ticketing' => false,
                'registration' => true,
                'sessions' => true,
                'professional_fields' => false,
                'badges' => true,
                'guest_rsvp' => false,
            ],

            /*
            |--------------------------------------------------------------------------
            | Graduation / VIP / Government
            |--------------------------------------------------------------------------
            */

            'graduation',
            'vip_ceremony',
            'government_event' => [
                'ticketing' => false,
                'registration' => true,
                'sessions' => true,
                'professional_fields' => true,
                'badges' => true,
                'guest_rsvp' => false,
            ],

            /*
            |--------------------------------------------------------------------------
            | Online Events
            |--------------------------------------------------------------------------
            */

            'webinar' => [
                'ticketing' => false,
                'registration' => true,
                'sessions' => true,
                'professional_fields' => true,
                'badges' => false,
                'guest_rsvp' => false,
            ],

            'hybrid_event' => [
                'ticketing' => false,
                'registration' => true,
                'sessions' => true,
                'professional_fields' => true,
                'badges' => true,
                'guest_rsvp' => false,
            ],

            default => $default,
        };
    }

    public static function usesTicketing(
        ?string $eventType
    ): bool {
        return self::featureProfile(
            $eventType
        )['ticketing'];
    }

    public static function usesRegistration(
        ?string $eventType
    ): bool {
        return self::featureProfile(
            $eventType
        )['registration'];
    }

    public static function usesSessions(
        ?string $eventType
    ): bool {
        return self::featureProfile(
            $eventType
        )['sessions'];
    }

    public static function usesProfessionalFields(
        ?string $eventType
    ): bool {
        return self::featureProfile(
            $eventType
        )['professional_fields'];
    }

    public static function usesBadges(
        ?string $eventType
    ): bool {
        return self::featureProfile(
            $eventType
        )['badges'];
    }

    public static function usesGuestRsvp(
        ?string $eventType
    ): bool {
        return self::featureProfile(
            $eventType
        )['guest_rsvp'];
    }

    public static function preset(?string $eventType): array
    {
        return match ($eventType) {
            'conference',
            'seminar',
            'workshop',
            'training',
            'corporate_event',
            'meeting',
            'networking_event',
            'exhibition',
            'expo',
            'trade_fair',
            'product_launch' => [
                'registration_is_open' => false,

                'registration_show_phone' => true,
                'registration_require_phone' => true,

                'registration_show_email' => true,
                'registration_require_email' => true,

                'registration_show_organization' => true,
                'registration_require_organization' => false,

                'registration_show_position' => true,
                'registration_require_position' => false,

                'registration_show_category' => true,
                'registration_require_category' => false,

                'registration_show_badge_type' => false,
                'registration_require_badge_type' => false,

                'schedule_mode' => 'single_day',

                'sessions_enabled' => true,
                'session_registration_enabled' => true,
                'session_check_in_enabled' => true,
            ],

            'concert' => [
                'registration_is_open' => false,

                'registration_show_phone' => true,
                'registration_require_phone' => true,

                'registration_show_email' => true,
                'registration_require_email' => false,

                'registration_show_organization' => false,
                'registration_require_organization' => false,

                'registration_show_position' => false,
                'registration_require_position' => false,

                'registration_show_category' => false,
                'registration_require_category' => false,

                'registration_show_badge_type' => false,
                'registration_require_badge_type' => false,

                'schedule_mode' => 'single_day',

                'sessions_enabled' => false,
                'session_registration_enabled' => false,
                'session_check_in_enabled' => false,

                'ticketSetting.ticket_sales_enabled' => true,
                'ticketSetting.reservation_minutes' => 15,
                'ticketSetting.max_tickets_per_order' => 10,
                'ticketSetting.allow_guest_checkout' => true,
            ],

            'festival',
            'cultural_event' => [
                'registration_is_open' => false,

                'registration_show_phone' => true,
                'registration_require_phone' => true,

                'registration_show_email' => true,
                'registration_require_email' => false,

                'registration_show_organization' => false,
                'registration_require_organization' => false,

                'registration_show_position' => false,
                'registration_require_position' => false,

                'registration_show_category' => true,
                'registration_require_category' => false,

                'registration_show_badge_type' => false,
                'registration_require_badge_type' => false,

                'schedule_mode' => 'multi_day',

                'sessions_enabled' => true,
                'session_registration_enabled' => true,
                'session_check_in_enabled' => true,

                'ticketSetting.ticket_sales_enabled' => false,
                'ticketSetting.reservation_minutes' => 15,
                'ticketSetting.max_tickets_per_order' => 10,
                'ticketSetting.allow_guest_checkout' => true,
            ],

            'bonanza',
            'sports_event',
            'tournament' => [
                'registration_is_open' => false,

                'registration_show_phone' => true,
                'registration_require_phone' => true,

                'registration_show_email' => false,
                'registration_require_email' => false,

                'registration_show_organization' => true,
                'registration_require_organization' => false,

                'registration_show_position' => false,
                'registration_require_position' => false,

                'registration_show_category' => true,
                'registration_require_category' => true,

                'registration_show_badge_type' => false,
                'registration_require_badge_type' => false,

                'schedule_mode' => 'single_day',

                'sessions_enabled' => true,
                'session_registration_enabled' => true,
                'session_check_in_enabled' => true,
            ],

            'church_event',
            'community_event',
            'charity_event' => [
                'registration_is_open' => false,

                'registration_show_phone' => true,
                'registration_require_phone' => true,

                'registration_show_email' => false,
                'registration_require_email' => false,

                'registration_show_organization' => true,
                'registration_require_organization' => false,

                'registration_show_position' => false,
                'registration_require_position' => false,

                'registration_show_category' => true,
                'registration_require_category' => false,

                'registration_show_badge_type' => false,
                'registration_require_badge_type' => false,

                'schedule_mode' => 'multi_day',

                'sessions_enabled' => true,
                'session_registration_enabled' => true,
                'session_check_in_enabled' => true,
            ],

            'wedding',
            'send_off',
            'engagement',
            'birthday' => [
                'registration_is_open' => false,

                'registration_show_phone' => true,
                'registration_require_phone' => true,

                'registration_show_email' => false,
                'registration_require_email' => false,

                'registration_show_organization' => false,
                'registration_require_organization' => false,

                'registration_show_position' => false,
                'registration_require_position' => false,

                'registration_show_category' => true,
                'registration_require_category' => false,

                'registration_show_badge_type' => false,
                'registration_require_badge_type' => false,

                'schedule_mode' => 'single_day',

                'sessions_enabled' => false,
                'session_registration_enabled' => false,
                'session_check_in_enabled' => false,
            ],

            'graduation',
            'vip_ceremony',
            'government_event' => [
                'registration_is_open' => false,

                'registration_show_phone' => true,
                'registration_require_phone' => true,

                'registration_show_email' => true,
                'registration_require_email' => false,

                'registration_show_organization' => true,
                'registration_require_organization' => false,

                'registration_show_position' => false,
                'registration_require_position' => false,

                'registration_show_category' => true,
                'registration_require_category' => false,

                'registration_show_badge_type' => false,
                'registration_require_badge_type' => false,

                'schedule_mode' => 'single_day',

                'sessions_enabled' => true,
                'session_registration_enabled' => true,
                'session_check_in_enabled' => true,
            ],

            'webinar',
            'hybrid_event' => [
                'registration_is_open' => false,

                'registration_show_phone' => true,
                'registration_require_phone' => false,

                'registration_show_email' => true,
                'registration_require_email' => true,

                'registration_show_organization' => true,
                'registration_require_organization' => false,

                'registration_show_position' => true,
                'registration_require_position' => false,

                'registration_show_category' => true,
                'registration_require_category' => false,

                'registration_show_badge_type' => false,
                'registration_require_badge_type' => false,

                'schedule_mode' => 'single_day',

                'sessions_enabled' => true,
                'session_registration_enabled' => true,
                'session_check_in_enabled' => true,
            ],

            default => [],
        };
    }

    public static function registrationLabels(
        ?string $eventType
    ): array {
        return match ($eventType) {
            'concert' => [
                'personal' => 'Guest Details',
                'professional' => 'Guest Information',
                'attendance' => 'Attendance',
                'sessions' => 'Performances / Activities',
                'additional' => 'Additional Guest Information',
            ],

            'bonanza',
            'sports_event',
            'tournament' => [
                'personal' => 'Participant Details',
                'professional' => 'Team / Participant Details',
                'attendance' => 'Competition Days',
                'sessions' => 'Games & Competitions',
                'additional' => 'Additional Participant Information',
            ],

            'church_event',
            'community_event',
            'charity_event' => [
                'personal' => 'Personal Details',
                'professional' => 'Church / Ministry Details',
                'attendance' => 'Attendance Days',
                'sessions' => 'Programs & Services',
                'additional' => 'Additional Information',
            ],

            'wedding',
            'send_off',
            'engagement',
            'birthday' => [
                'personal' => 'Guest Details',
                'professional' => 'Guest Information',
                'attendance' => 'Attendance',
                'sessions' => 'Program Selection',
                'additional' => 'Additional Guest Information',
            ],

            'training' => [
                'personal' => 'Personal Details',
                'professional' => 'Professional Details',
                'attendance' => 'Training Days',
                'sessions' => 'Training Modules',
                'additional' => 'Additional Information',
            ],

            'festival',
            'cultural_event' => [
                'personal' => 'Guest Details',
                'professional' => 'Guest Information',
                'attendance' => 'Festival Days',
                'sessions' => 'Performances / Activities',
                'additional' => 'Additional Information',
            ],

            'webinar',
            'hybrid_event' => [
                'personal' => 'Participant Details',
                'professional' => 'Professional Details',
                'attendance' => 'Attendance',
                'sessions' => 'Online Sessions',
                'additional' => 'Additional Information',
            ],

            default => [
                'personal' => 'Personal Details',
                'professional' => 'Professional / Registration Details',
                'attendance' => 'Attendance Selection',
                'sessions' => 'Sessions / Activities',
                'additional' => 'Additional Information',
            ],
        };
    }

    public static function eventTypeLabel(
        ?string $eventType,
        ?string $customEventType = null
    ): string {
        if (
            $eventType === 'other'
            && filled($customEventType)
        ) {
            return $customEventType;
        }

        return self::eventTypes()[$eventType]
            ?? 'Other';
    }

    public static function isTicketFocused(
        ?string $eventType
    ): bool {
        return self::usesTicketing(
            $eventType
        );
    }

    public static function isRegistrationFocused(
        ?string $eventType
    ): bool {
        return self::usesRegistration(
            $eventType
        );
    }
}