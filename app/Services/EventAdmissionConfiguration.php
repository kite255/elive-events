<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Validation\ValidationException;

class EventAdmissionConfiguration
{
    public static function flags(string $mode): array
    {
        return match ($mode) {
            'manual' => [false, false, false],
            'free_registration' => [true, false, false],
            'paid_registration' => [true, false, true],
            'tickets' => [false, true, false],
            'combined' => [true, true, false],
            'paid_combined' => [true, true, true],
            default => throw ValidationException::withMessages(['data.admission_mode' => 'Select a valid admission method.']),
        };
    }

    public function persist(Event $event, string $mode): void
    {
        [$registration, $tickets, $paid] = self::flags($mode);

        \Illuminate\Support\Facades\DB::transaction(function () use ($event, $registration, $tickets, $paid): void {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->getKey());
            $locked->registration_is_open = $registration;
            if (! $registration) {
                $locked->registration_access_code_hash = null;
            }
            $locked->save();

            $locked->ticketSetting()->updateOrCreate([], ['ticket_sales_enabled' => $tickets]);
            $locked->paymentSetting()->updateOrCreate([], ['payments_enabled' => $paid]);
        });
    }
}
