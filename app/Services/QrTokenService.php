<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\AttendeeQrToken;
use Illuminate\Support\Str;

class QrTokenService
{
    /**
     * Generate a secure QR token for an attendee.
     *
     * The raw token is returned only once so it can be used in QR links.
     * The database stores only the hashed token for security.
     */
    public function generateForAttendee(Attendee $attendee): string
    {
        $plainToken = Str::random(64);

        $tokenHash = hash('sha256', $plainToken);

        $attendee->qrToken()->updateOrCreate(
            [
                'attendee_id' => $attendee->id,
            ],
            [
                'token_hash' => $tokenHash,
                'token_last4' => substr($plainToken, -4),
                'expires_at' => null,
                'used_at' => null,
            ]
        );

        return $plainToken;
    }

    /**
     * Find attendee by plain QR token.
     */
    public function findAttendeeByToken(string $plainToken): ?Attendee
    {
        $tokenHash = hash('sha256', $plainToken);

        $qrToken = AttendeeQrToken::query()
            ->with('attendee.event', 'attendee.category', 'attendee.badgeType')
            ->where('token_hash', $tokenHash)
            ->first();

        return $qrToken?->attendee;
    }

    /**
     * Check whether a token exists and is still usable.
     */
    public function isValidToken(string $plainToken): bool
    {
        $tokenHash = hash('sha256', $plainToken);

        return AttendeeQrToken::query()
            ->where('token_hash', $tokenHash)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}