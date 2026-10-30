<?php

namespace App\Http\Controllers;

use App\Models\EventPayoutRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayoutTransferProofController
{
    public function __invoke(Request $request, EventPayoutRequest $payout): BinaryFileResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);

        $path = $payout->transfer_proof_path;
        abort_unless(
            is_string($path)
                && str_starts_with($path, 'payout-proofs/')
                && ! str_contains($path, '..')
                && Storage::disk('local')->exists($path),
            404
        );

        // Record every authorized access without exposing private storage URLs.
        DB::table('event_payout_audits')->insert([
            'event_payout_request_id' => $payout->getKey(),
            'actor_id' => $user->getKey(),
            'action' => 'proof_viewed',
            'from_status' => $payout->status,
            'to_status' => $payout->status,
            'note' => 'Authorized administrator downloaded transfer proof.',
            'created_at' => now(),
        ]);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };

        return response()->download(
            Storage::disk('local')->path($path),
            'payout-proof-'.$payout->getKey().'.'.$extension,
            [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
