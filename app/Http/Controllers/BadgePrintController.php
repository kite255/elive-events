<?php

namespace App\Http\Controllers;

use App\Models\Attendee;
use App\Services\BadgeGenerationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BadgePrintController extends Controller
{
    public function __invoke(Request $request, BadgeGenerationService $badgeGenerationService): View
    {
        $ids = collect(explode(',', (string) $request->query('ids')))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $attendees = Attendee::query()
            ->with(['event', 'category', 'badgeType'])
            ->whereIn('id', $ids)
            ->orderBy('full_name')
            ->get();

        foreach ($attendees as $attendee) {
            if (blank($attendee->badge_path)) {
                $badgeGenerationService->generateForAttendee($attendee);
                $attendee->refresh();
            }
        }

        return view('badges.print', [
            'attendees' => $attendees,
        ]);
    }
}