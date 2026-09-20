<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\AttendeeQrToken;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class QrTokenService
{
    /**
     * Get an attendee's QR token.
     *
     * Reuses the existing token when available.
     * Creates a new one only when necessary.
     */
    public function generateForAttendee(Attendee $attendee): string
    {
        $qrToken = $attendee->qrToken()->first();

        if (
            $qrToken &&
            filled($qrToken->encrypted_token)
        ) {
            return Crypt::decryptString(
                $qrToken->encrypted_token
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Create a token
        |--------------------------------------------------------------------------
        |
        | Existing legacy records may only contain token_hash.
        | Since the original token cannot be recovered from SHA-256,
        | such records receive one new permanent token.
        |
        */

        $plainToken = Str::random(64);

        $attendee->qrToken()->updateOrCreate(
            [
                'attendee_id' => $attendee->id,
            ],
            [
                'token_hash' => hash(
                    'sha256',
                    $plainToken
                ),

                'encrypted_token' => Crypt::encryptString(
                    $plainToken
                ),

                'token_last4' => substr(
                    $plainToken,
                    -4
                ),

                'expires_at' => null,
                'used_at' => null,
            ]
        );

        return $plainToken;
    }

    /**
     * Get the existing attendee token.
     */
    public function getTokenForAttendee(Attendee $attendee): string
    {
        return $this->generateForAttendee(
            $attendee
        );
    }

    /**
     * Explicitly rotate an attendee's QR token.
     *
     * This invalidates the previous QR code.
     */
    public function rotateForAttendee(Attendee $attendee): string
    {
        $plainToken = Str::random(64);

        $attendee->qrToken()->updateOrCreate(
            [
                'attendee_id' => $attendee->id,
            ],
            [
                'token_hash' => hash(
                    'sha256',
                    $plainToken
                ),

                'encrypted_token' => Crypt::encryptString(
                    $plainToken
                ),

                'token_last4' => substr(
                    $plainToken,
                    -4
                ),

                'expires_at' => null,
                'used_at' => null,
            ]
        );

        return $plainToken;
    }

    /**
     * Find an attendee using a plain QR token.
     */
    public function findAttendeeByToken(string $plainToken): ?Attendee
    {
        $tokenHash = hash(
            'sha256',
            $plainToken
        );

        $qrToken = AttendeeQrToken::query()
            ->with(
                'attendee.event',
                'attendee.category',
                'attendee.badgeType'
            )
            ->where(
                'token_hash',
                $tokenHash
            )
            ->first();

        return $qrToken?->attendee;
    }

    /**
     * Check whether a QR token is valid.
     */
    public function isValidToken(string $plainToken): bool
    {
        $tokenHash = hash(
            'sha256',
            $plainToken
        );

        return AttendeeQrToken::query()
            ->where(
                'token_hash',
                $tokenHash
            )
            ->where(function ($query) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere(
                        'expires_at',
                        '>',
                        now()
                    );
            })
            ->exists();
    }
}