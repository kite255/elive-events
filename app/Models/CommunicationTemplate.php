<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationTemplate extends Model
{
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_WHATSAPP = 'whatsapp';

    /*
    |--------------------------------------------------------------------------
    | Email Template Keys
    |--------------------------------------------------------------------------
    */

    public const KEY_REGISTRATION_CONFIRMED_EMAIL =
        'registration_confirmed_email';

    public const KEY_EVENT_UPDATE_EMAIL =
        'event_update_email';

    public const KEY_EVENT_REMINDER_EMAIL =
        'event_reminder_email';

    public const KEY_VENUE_CHANGE_EMAIL =
        'venue_change_email';

    public const KEY_SCHEDULE_CHANGE_EMAIL =
        'schedule_change_email';

    public const KEY_GENERAL_ANNOUNCEMENT_EMAIL =
        'general_announcement_email';

    public const KEY_BADGE_DELIVERY_EMAIL =
        'badge_delivery_email';

    public const KEY_PAYMENT_CONFIRMATION_EMAIL =
        'payment_confirmation_email';

    public const KEY_PAYMENT_REMINDER_EMAIL =
        'payment_reminder_email';

    public const KEY_WAITLIST_UPDATE_EMAIL =
        'waitlist_update_email';

    public const KEY_CERTIFICATE_DELIVERY_EMAIL =
        'certificate_delivery_email';

    public const KEY_FEEDBACK_REQUEST_EMAIL =
        'feedback_request_email';

    public const KEY_POST_EVENT_THANK_YOU_EMAIL =
        'post_event_thank_you_email';

    /*
    |--------------------------------------------------------------------------
    | SMS Template Keys
    |--------------------------------------------------------------------------
    */

    public const KEY_REGISTRATION_RECEIVED_SMS =
        'registration_received_sms';

    public const KEY_REGISTRATION_CONFIRMED_SMS =
        'registration_confirmed_sms';

    public const KEY_EVENT_UPDATE_SMS =
        'event_update_sms';

    public const KEY_EVENT_REMINDER_SMS =
        'event_reminder_sms';

    public const KEY_VENUE_CHANGE_SMS =
        'venue_change_sms';

    public const KEY_SCHEDULE_CHANGE_SMS =
        'schedule_change_sms';

    public const KEY_GENERAL_ANNOUNCEMENT_SMS =
        'general_announcement_sms';

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Template Keys
    |--------------------------------------------------------------------------
    */

    public const KEY_REGISTRATION_RECEIVED_WHATSAPP =
        'registration_received_whatsapp';

    public const KEY_REGISTRATION_CONFIRMED_WHATSAPP =
        'registration_confirmed_whatsapp';

    public const KEY_EVENT_UPDATE_WHATSAPP =
        'event_update_whatsapp';

    public const KEY_EVENT_REMINDER_WHATSAPP =
        'event_reminder_whatsapp';

    public const KEY_VENUE_CHANGE_WHATSAPP =
        'venue_change_whatsapp';

    public const KEY_SCHEDULE_CHANGE_WHATSAPP =
        'schedule_change_whatsapp';

    public const KEY_GENERAL_ANNOUNCEMENT_WHATSAPP =
        'general_announcement_whatsapp';

    protected $fillable = [
        'organization_id',
        'name',
        'key',
        'channel',
        'subject',
        'body',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class
        );
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(
            CommunicationCampaign::class,
            'communication_template_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForOrganization(
        Builder $query,
        int $organizationId
    ): Builder {
        return $query->where(
            'organization_id',
            $organizationId
        );
    }

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where(
            'is_active',
            true
        );
    }

    public function scopeForChannel(
        Builder $query,
        string $channel
    ): Builder {
        return $query->where(
            'channel',
            $channel
        );
    }

    public function scopeNamed(
        Builder $query,
        string $name
    ): Builder {
        return $query->where(
            'name',
            $name
        );
    }

    public function scopeWithKey(
        Builder $query,
        string $key
    ): Builder {
        return $query->where(
            'key',
            $key
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isSms(): bool
    {
        return $this->channel === self::CHANNEL_SMS;
    }

    public function isEmail(): bool
    {
        return $this->channel === self::CHANNEL_EMAIL;
    }

    public function isWhatsApp(): bool
    {
        return $this->channel === self::CHANNEL_WHATSAPP;
    }

    public function isUsable(): bool
    {
        return (bool) $this->is_active
            && filled($this->body);
    }

    /*
    |--------------------------------------------------------------------------
    | Channel Options
    |--------------------------------------------------------------------------
    */

    public static function channelOptions(): array
    {
        return [
            self::CHANNEL_SMS =>
                'SMS',

            self::CHANNEL_EMAIL =>
                'Email',

            self::CHANNEL_WHATSAPP =>
                'WhatsApp',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Email Template Types
    |--------------------------------------------------------------------------
    */

    public static function emailTemplateTypes(): array
    {
        return [
            self::KEY_REGISTRATION_CONFIRMED_EMAIL =>
                'Registration Confirmation',

            self::KEY_EVENT_UPDATE_EMAIL =>
                'Event Update',

            self::KEY_EVENT_REMINDER_EMAIL =>
                'Event Reminder',

            self::KEY_VENUE_CHANGE_EMAIL =>
                'Venue Change Notice',

            self::KEY_SCHEDULE_CHANGE_EMAIL =>
                'Schedule Change Notice',

            self::KEY_GENERAL_ANNOUNCEMENT_EMAIL =>
                'General Announcement',

            self::KEY_BADGE_DELIVERY_EMAIL =>
                'Badge Delivery',

            self::KEY_PAYMENT_CONFIRMATION_EMAIL =>
                'Payment Confirmation',

            self::KEY_PAYMENT_REMINDER_EMAIL =>
                'Payment Reminder',

            self::KEY_WAITLIST_UPDATE_EMAIL =>
                'Waitlist Update',

            self::KEY_CERTIFICATE_DELIVERY_EMAIL =>
                'Certificate Delivery',

            self::KEY_FEEDBACK_REQUEST_EMAIL =>
                'Feedback Request',

            self::KEY_POST_EVENT_THANK_YOU_EMAIL =>
                'Post-Event Thank You',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SMS Template Types
    |--------------------------------------------------------------------------
    */

    public static function smsTemplateTypes(): array
    {
        return [
            self::KEY_REGISTRATION_RECEIVED_SMS =>
                'Registration Received',

            self::KEY_REGISTRATION_CONFIRMED_SMS =>
                'Registration Confirmation',

            self::KEY_EVENT_UPDATE_SMS =>
                'Event Update',

            self::KEY_EVENT_REMINDER_SMS =>
                'Event Reminder',

            self::KEY_VENUE_CHANGE_SMS =>
                'Venue Change Notice',

            self::KEY_SCHEDULE_CHANGE_SMS =>
                'Schedule Change Notice',

            self::KEY_GENERAL_ANNOUNCEMENT_SMS =>
                'General Announcement',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Template Types
    |--------------------------------------------------------------------------
    */

    public static function whatsappTemplateTypes(): array
    {
        return [
            self::KEY_REGISTRATION_RECEIVED_WHATSAPP =>
                'Registration Received',

            self::KEY_REGISTRATION_CONFIRMED_WHATSAPP =>
                'Registration Confirmation',

            self::KEY_EVENT_UPDATE_WHATSAPP =>
                'Event Update',

            self::KEY_EVENT_REMINDER_WHATSAPP =>
                'Event Reminder',

            self::KEY_VENUE_CHANGE_WHATSAPP =>
                'Venue Change Notice',

            self::KEY_SCHEDULE_CHANGE_WHATSAPP =>
                'Schedule Change Notice',

            self::KEY_GENERAL_ANNOUNCEMENT_WHATSAPP =>
                'General Announcement',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Template Types By Channel
    |--------------------------------------------------------------------------
    */

    public static function templateTypesForChannel(
        ?string $channel
    ): array {
        return match ($channel) {
            self::CHANNEL_EMAIL =>
                self::emailTemplateTypes(),

            self::CHANNEL_SMS =>
                self::smsTemplateTypes(),

            self::CHANNEL_WHATSAPP =>
                self::whatsappTemplateTypes(),

            default =>
                [],
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Friendly Name
    |--------------------------------------------------------------------------
    */

    public static function friendlyNameForKey(
        ?string $key
    ): ?string {
        if (blank($key)) {
            return null;
        }

        foreach (
            [
                self::emailTemplateTypes(),
                self::smsTemplateTypes(),
                self::whatsappTemplateTypes(),
            ]
            as $types
        ) {
            if (array_key_exists($key, $types)) {
                return $types[$key];
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Channel From Key
    |--------------------------------------------------------------------------
    */

    public static function channelForKey(
        ?string $key
    ): ?string {
        if (blank($key)) {
            return null;
        }

        if (
            array_key_exists(
                $key,
                self::emailTemplateTypes()
            )
        ) {
            return self::CHANNEL_EMAIL;
        }

        if (
            array_key_exists(
                $key,
                self::smsTemplateTypes()
            )
        ) {
            return self::CHANNEL_SMS;
        }

        if (
            array_key_exists(
                $key,
                self::whatsappTemplateTypes()
            )
        ) {
            return self::CHANNEL_WHATSAPP;
        }

        return null;
    }
}
