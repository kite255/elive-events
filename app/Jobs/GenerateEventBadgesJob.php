<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\BadgeGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GenerateEventBadgesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $eventId,
        public bool $onlyMissing = false,
    ) {}

    public function handle(BadgeGenerationService $badgeGenerationService): void
    {
        $event = Event::query()->find($this->eventId);

        if (! $event) {
            return;
        }

        $query = $event->attendees()
            ->with(['event', 'category', 'badgeType', 'qrToken'])
            ->orderBy('id');

        if ($this->onlyMissing) {
            $query->where(function ($query) {
                $query
                    ->whereNull('badge_path')
                    ->orWhere('badge_path', '')
                    ->orWhere('badge_status', '!=', 'generated')
                    ->orWhereNull('badge_status');
            });
        }

        $query->chunkById(50, function ($attendees) use ($badgeGenerationService) {
            foreach ($attendees as $attendee) {
                try {
                    $badgeGenerationService->generateForAttendee($attendee);
                } catch (Throwable $e) {
                    report($e);

                    $failedData = [];

                    if (Schema::hasColumn('attendees', 'badge_status')) {
                        $failedData['badge_status'] = 'failed';
                    }

                    if ($failedData !== []) {
                        $attendee->forceFill($failedData)->save();
                    }
                }
            }
        });
    }
}