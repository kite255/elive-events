<?php

namespace App\Services\Tickets;

use App\Models\Event;
use App\Models\TicketCheckIn;
use App\Models\TicketType;

class TicketCapacityMetricsService
{
    public function __construct(
        private readonly TicketAvailabilityService $availabilityService
    ) {
    }

    public function forEvent(Event $event): array
    {
        $ticketTypes = $event->ticketTypes()->get();
        $rows = [];
        $soldTotal = 0;
        $reservedTotal = 0;
        $checkedInTotal = 0;
        $finiteCapacityTotal = 0;
        $finiteAvailableTotal = 0;
        $hasUnlimited = false;

        foreach ($ticketTypes as $ticketType) {
            $sold = $this->availabilityService->soldQuantity($ticketType);
            $reserved = $this->availabilityService->reservedQuantity($ticketType);
            $available = $this->availabilityService->availableQuantity($ticketType);
            $capacity = $this->normalizedCapacity($ticketType);
            $checkedIn = TicketCheckIn::query()
                ->where('event_id', $event->id)
                ->whereHas('ticket', fn ($query) => $query->where('ticket_type_id', $ticketType->id))
                ->count();

            $soldTotal += $sold;
            $reservedTotal += $reserved;
            $checkedInTotal += $checkedIn;

            if ($capacity === null) {
                $hasUnlimited = true;
            } else {
                $finiteCapacityTotal += $capacity;
                $finiteAvailableTotal += (int) ($available ?? 0);
            }

            $rows[] = [
                'id' => $ticketType->id,
                'name' => $ticketType->name,
                'capacity' => $capacity,
                'sold' => $sold,
                'reserved' => $reserved,
                'available' => $available,
                'remaining' => $available,
                'checked_in' => $checkedIn,
                'currency' => $ticketType->currency,
                'price' => (float) $ticketType->price,
            ];
        }

        return [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
            ],
            'summary' => [
                'capacity' => $hasUnlimited ? null : $finiteCapacityTotal,
                'sold' => $soldTotal,
                'reserved' => $reservedTotal,
                'available' => $hasUnlimited ? null : $finiteAvailableTotal,
                'checked_in' => $checkedInTotal,
            ],
            'ticket_types' => $rows,
        ];
    }

    private function normalizedCapacity(TicketType $ticketType): ?int
    {
        if ($ticketType->capacity === null || (int) $ticketType->capacity <= 0) {
            return null;
        }

        return (int) $ticketType->capacity;
    }
}
