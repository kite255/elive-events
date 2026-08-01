<?php

namespace App\Models;

use App\Services\BadgeNumberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Event extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'event_code',
        'event_type',
        'venue',
        'description',
        'starts_at',
        'ends_at',
        'capacity',
        'status',

        'registration_is_open',
        'registration_requires_approval',
        'registration_banner_image_path',
        'registration_logo_path',
        'registration_primary_color',
        'registration_background_color',
        'registration_button_color',
        'registration_welcome_title',
        'registration_welcome_message',
        'registration_success_message',
        'registration_auto_generate_badge',

        'registration_waitlist_enabled',
        'registration_waitlist_message',

        'show_merchandise_images',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',

            'registration_is_open' => 'boolean',
            'registration_requires_approval' => 'boolean',
            'registration_auto_generate_badge' => 'boolean',
            'registration_waitlist_enabled' => 'boolean',

            'show_merchandise_images' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            if (blank($event->slug)) {
                $event->slug = static::generateUniqueSlug($event->name);
            }

            if (filled($event->event_code)) {
                $event->event_code = strtoupper($event->event_code);
            }
        });

        static::created(function (Event $event): void {
            app(BadgeNumberService::class)->assignEventCode($event);
        });

        static::updating(function (Event $event): void {
            if ($event->isDirty('name') && blank($event->slug)) {
                $event->slug = static::generateUniqueSlug(
                    $event->name,
                    $event->id
                );
            }

            if (
                $event->isDirty('event_code')
                && filled($event->event_code)
            ) {
                $event->event_code = strtoupper($event->event_code);
            }
        });
    }

    public static function generateUniqueSlug(
        ?string $name,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug($name ?: 'event');

        if (blank($baseSlug)) {
            $baseSlug = 'event';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when(
                    $ignoreId,
                    fn ($query) => $query->where('id', '!=', $ignoreId)
                )
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /*
    |--------------------------------------------------------------------------
    | Organization
    |--------------------------------------------------------------------------
    */

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Event Setup
    |--------------------------------------------------------------------------
    */

    public function attendeeCategories(): HasMany
    {
        return $this->hasMany(AttendeeCategory::class);
    }

    public function badgeTypes(): HasMany
    {
        return $this->hasMany(BadgeType::class);
    }

    public function badgeTemplates(): HasMany
    {
        return $this->hasMany(BadgeTemplate::class);
    }

    public function registrationFields(): HasMany
    {
        return $this->hasMany(EventRegistrationField::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Event Days
    |--------------------------------------------------------------------------
    */

    public function days(): HasMany
    {
        return $this->hasMany(EventDay::class)
            ->orderBy('event_date')
            ->orderBy('display_order')
            ->orderBy('id');
    }

    public function activeDays(): HasMany
    {
        return $this->days()
            ->where('status', 'active');
    }

    public function openRegistrationDays(): HasMany
    {
        return $this->days()
            ->where('status', 'active')
            ->where('is_registration_open', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Merchandise
    |--------------------------------------------------------------------------
    */

    public function merchandise(): HasMany
    {
        return $this->hasMany(EventMerchandise::class)
            ->orderBy('display_order')
            ->orderBy('id');
    }

    public function activeMerchandise(): HasMany
    {
        return $this->merchandise()
            ->where('is_active', true);
    }

    /**
     * All attendee merchandise orders for this event.
     *
     * Event -> Attendees -> AttendeeMerchandise
     */
    public function attendeeMerchandiseOrders(): HasManyThrough
    {
        return $this->hasManyThrough(
            AttendeeMerchandise::class,
            Attendee::class,
            'event_id',
            'attendee_id',
            'id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Attendees
    |--------------------------------------------------------------------------
    */

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Check-in & Attendance
    |--------------------------------------------------------------------------
    */

    public function checkInPoints(): HasMany
    {
        return $this->hasMany(CheckInPoint::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Communication
    |--------------------------------------------------------------------------
    */

    public function communicationCampaigns(): HasMany
    {
        return $this->hasMany(CommunicationCampaign::class);
    }

    public function communicationLogs(): HasMany
    {
        return $this->hasMany(CommunicationLog::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Registration Helpers
    |--------------------------------------------------------------------------
    */

    public function acceptedAttendeesCount(): int
    {
        return $this->attendees()
            ->whereIn('status', [
                'pending_approval',
                'registered',
                'confirmed',
                'checked_in',
            ])
            ->count();
    }

    public function confirmedAttendeesCount(): int
    {
        return $this->attendees()
            ->whereIn('status', [
                'registered',
                'confirmed',
                'checked_in',
            ])
            ->count();
    }

    public function pendingApprovalAttendeesCount(): int
    {
        return $this->attendees()
            ->where('status', 'pending_approval')
            ->count();
    }

    public function waitlistedAttendeesCount(): int
    {
        return $this->attendees()
            ->where('status', 'waitlisted')
            ->count();
    }

    public function remainingCapacity(): ?int
    {
        if (
            blank($this->capacity)
            || (int) $this->capacity <= 0
        ) {
            return null;
        }

        return max(
            0,
            (int) $this->capacity - $this->acceptedAttendeesCount()
        );
    }

    public function isRegistrationFull(): bool
    {
        if (
            blank($this->capacity)
            || (int) $this->capacity <= 0
        ) {
            return false;
        }

        return $this->acceptedAttendeesCount() >= (int) $this->capacity;
    }

    public function hasMultipleDays(): bool
    {
        return $this->days()->count() > 1;
    }

    public function hasEventDays(): bool
    {
        return $this->days()->exists();
    }

    public function hasMerchandise(): bool
    {
        return $this->activeMerchandise()->exists();
    }

    public function shouldShowMerchandiseImages(): bool
    {
        return (bool) $this->show_merchandise_images;
    }
}
