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
            'concert' => 'Concert',
            'cultural_event' => 'Cultural Event',

            'vip_ceremony' => 'VIP Ceremony',
            'government_event' => 'Government / Official Event',

            'webinar' => 'Webinar',
            'hybrid_event' => 'Hybrid Event',

            'other' => 'Other',
        ];
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
                'sessions_enabled' => true,
                'session_registration_enabled' => true,
                'session_check_in_enabled' => true,
            ],

            'bonanza',
            'sports_event',
            'tournament' => [
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
                'sessions_enabled' => true,
                'session_registration_enabled' => true,
                'session_check_in_enabled' => true,
            ],

            'church_event',
            'community_event',
            'charity_event' => [
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
                'sessions_enabled' => true,
                'session_registration_enabled' => true,
                'session_check_in_enabled' => true,
            ],

            'wedding',
            'send_off',
            'engagement',
            'birthday' => [
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
                'sessions_enabled' => false,
                'session_registration_enabled' => false,
                'session_check_in_enabled' => false,
            ],

            'graduation',
            'festival',
            'concert',
            'cultural_event',
            'vip_ceremony',
            'government_event' => [
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
                'sessions_enabled' => true,
                'session_registration_enabled' => true,
                'session_check_in_enabled' => true,
            ],

            'webinar',
            'hybrid_event' => [
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
                'sessions_enabled' => true,
                'session_registration_enabled' => true,
                'session_check_in_enabled' => true,
            ],

            default => [],
        };
    }

    public static function registrationLabels(?string $eventType): array
    {
        return match ($eventType) {
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
        if ($eventType === 'other' && filled($customEventType)) {
            return $customEventType;
        }

        return self::eventTypes()[$eventType] ?? 'Other';
    }
}
