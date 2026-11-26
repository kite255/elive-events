<?php

namespace App\Jobs;

use App\Models\Attendee;
use App\Models\Payment;
use App\Services\BadgeGenerationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GeneratePublicRegistrationBadge implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 75;
    public int $uniqueFor = 86400;

    public function __construct(public int $attendeeId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return (string) $this->attendeeId;
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(BadgeGenerationService $badges): void
    {
        $attendee = Attendee::with('event.paymentSetting')->find($this->attendeeId);

        if (! $attendee || ! $attendee->isApproved()
            || ! $attendee->event?->registration_auto_generate_badge) {
            return;
        }

        $settings = $attendee->event->paymentSetting;

        if ($settings?->payments_enabled && (float) $settings->registration_fee > 0
            && ! Payment::query()
                ->where('event_id', $attendee->event_id)
                ->where('attendee_id', $attendee->id)
                ->whereNull('ticket_order_id')
                ->whereNull('ticket_upgrade_id')
                ->whereNull('donation_id')
                ->whereNull('merchandise_order_id')
                ->where('status', Payment::STATUS_COMPLETED)
                ->exists()) {
            return;
        }

        if (filled($attendee->badge_path)
            && Storage::disk('public')->exists($attendee->badge_path)) {
            return;
        }

        $badges->generateForAttendee($attendee);
    }
}
