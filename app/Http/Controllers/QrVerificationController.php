<?php

namespace App\Http\Controllers;

use App\Services\QrTokenService;
use Illuminate\View\View;

class QrVerificationController extends Controller
{
    public function show(string $token, QrTokenService $qrTokenService): View
    {
        $attendee = $qrTokenService->findAttendeeByToken($token);

        if (! $attendee || ! $qrTokenService->isValidToken($token)) {
            return view('qr.invalid');
        }

        return view('qr.verify', [
            'attendee' => $attendee,
            'token' => $token,
        ]);
    }
}