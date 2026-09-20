<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicEventsApiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $events = Event::query()
            ->where('show_on_elive_website', true)
            ->whereNotIn('status', [
                Event::STATUS_DRAFT,
                Event::STATUS_CANCELLED,
            ])
            ->with('ticketSetting')
            ->withCount('publicTicketTypes')
            ->orderBy('starts_at')
            ->get()
            ->map(function (Event $event): array {
                $hasTickets = (int) $event->public_ticket_types_count > 0
                    && (bool) $event->ticketSetting?->ticket_sales_enabled;

                $hasRegistration = (bool) $event->registration_is_open;

                $kind = match (true) {
                    $hasTickets && $hasRegistration => 'registration_and_ticket',
                    $hasTickets => 'ticket',
                    default => 'registration',
                };

                $status = $this->publicStatus($event);

                return [
                    'id' => 'events-'.$event->getKey(),
                    'source' => 'events',
                    'kind' => $kind,
                    'name' => $event->name,
                    'event_type' => $event->custom_event_type ?: $event->event_type,

                    'summary' => filled($event->description)
                        ? Str::of(strip_tags((string) $event->description))
                            ->squish()
                            ->limit(180)
                            ->toString()
                        : null,

                    'starts_at' => $event->starts_at?->toIso8601String(),
                    'ends_at' => $event->ends_at?->toIso8601String(),

                    'venue' => $event->venue,
                    'venue_address' => $event->venue_address,

                    'image_url' => $this->imageUrl(
                        $event->registration_banner_image_path
                    ),

                    'status' => $status,

                    'url' => route(
                        'public.events.show',
                        ['event' => $event->slug]
                    ),

                    'registration_url' => $hasRegistration
                        ? route(
                            'public.events.register',
                            ['event' => $event->slug]
                        )
                        : null,

                    'ticket_url' => $hasTickets
                        ? route(
                            'public.tickets.buy',
                            ['event' => $event->slug]
                        )
                        : null,
                ];
            })
            ->values();

        return response()->json([
            'data' => $events,

            'meta' => [
                'source' => 'events.elive.co.tz',
                'count' => $events->count(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function publicStatus(Event $event): string
    {
        if ($event->status === Event::STATUS_COMPLETED) {
            return 'past';
        }

        if ($event->starts_at?->isFuture()) {
            return 'upcoming';
        }

        if (
            $event->starts_at !== null
            && $event->starts_at->isPast()
            && $event->ends_at?->isFuture()
        ) {
            return 'live';
        }

        if (
            $event->starts_at !== null
            && $event->ends_at === null
            && $event->starts_at->isToday()
        ) {
            return 'live';
        }

        return 'past';
    }

    private function imageUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $path = ltrim($path, '/');

        foreach (['storage/', 'public/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
            }
        }

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : null;
    }
}