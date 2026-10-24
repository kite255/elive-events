<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventPayoutReviewService
{
    /**
     * Administrative review only. No payment is transmitted by this service.
     */
    public function review(EventPayoutRequest $request, User $reviewer, bool $approve, ?string $note = null): EventPayoutRequest
    {
        if (! $reviewer->isSuperAdmin()) {
            throw ValidationException::withMessages(['review' => 'Only a platform administrator can review payouts.']);
        }

        return DB::transaction(function () use ($request, $reviewer, $approve, $note): EventPayoutRequest {
            Event::query()->whereKey($request->event_id)->lockForUpdate()->firstOrFail();
            $locked = EventPayoutRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== EventPayoutRequest::REQUESTED) {
                throw ValidationException::withMessages(['review' => 'This request has already been reviewed.']);
            }

            $locked->status = $approve ? EventPayoutRequest::APPROVED : EventPayoutRequest::REJECTED;
            $locked->reviewed_by = $reviewer->getKey();
            $locked->review_note = $note;
            $locked->reviewed_at = now();
            $locked->save();

            return $locked;
        });
    }

    /**
     * Records an independently verified transfer; never initiates a transfer.
     */
    public function recordCompletedTransfer(EventPayoutRequest $request, User $reviewer, string $transferReference): EventPayoutRequest
    {
        if (! $reviewer->isSuperAdmin() || trim($transferReference) === '') {
            throw ValidationException::withMessages(['transfer' => 'Administrator and transfer reference required.']);
        }

        return DB::transaction(function () use ($request, $transferReference): EventPayoutRequest {
            Event::query()->whereKey($request->event_id)->lockForUpdate()->firstOrFail();
            $locked = EventPayoutRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== EventPayoutRequest::APPROVED) {
                throw ValidationException::withMessages(['transfer' => 'Payout must be approved before recording a transfer.']);
            }

            if (EventPayoutRequest::query()->where('transfer_reference', trim($transferReference))->exists()) {
                throw ValidationException::withMessages(['transfer' => 'Transfer reference already recorded.']);
            }

            $locked->status = EventPayoutRequest::PAID;
            $locked->paid_at = now();
            $locked->transfer_reference = trim($transferReference);
            $locked->save();

            return $locked;
        });
    }
}
