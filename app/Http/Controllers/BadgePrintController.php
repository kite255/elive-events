<?php

namespace App\Http\Controllers;

use App\Models\Attendee;
use App\Models\User;
use App\Services\BadgeGenerationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BadgePrintController extends Controller
{
    public function __invoke(Request $request, BadgeGenerationService $badgeGenerationService): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && ! $user->is_gate_staff, 403);

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

        // Authorize the entire batch before generating any badge.
        abort_unless($attendees->count() === $ids->count() && $attendees->every(
            fn (Attendee $attendee): bool => $attendee->event?->canManageBadgesBy($user) === true
        ), 403);

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