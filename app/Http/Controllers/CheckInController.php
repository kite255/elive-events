<?php

namespace App\Http\Controllers;

use App\Services\CheckInService;
use App\Services\QrTokenService;
use Illuminate\View\View;

class CheckInController extends Controller
{
    public function show(
        string $token,
        QrTokenService $qrTokenService,
        CheckInService $checkInService
    ): View {
        $attendee = $qrTokenService->findAttendeeByToken($token);

        if (! $attendee || ! $qrTokenService->isValidToken($token)) {
            return view('check-in.invalid');
        }

        $result = $checkInService->checkIn(
            attendee: $attendee,
            checkInPointId: null,
            method: 'qr',
            note: 'Checked in using QR code.'
        );

        if (! $result['success']) {
            return view('check-in.duplicate', [
                'attendee' => $result['attendee']->fresh(['event', 'category', 'badgeType']),
                'checkIn' => $result['check_in'] ?? null,
                'message' => $result['message'],
            ]);
        }

        return view('check-in.success', [
            'attendee' => $result['attendee']->fresh(['event', 'category', 'badgeType']),
            'checkIn' => $result['check_in'] ?? null,
            'message' => $result['message'],
        ]);
    }
}