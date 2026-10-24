<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\User;
use App\Notifications\EventPayoutStatusNotification;
use Illuminate\Support\Facades\Log;
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

            $previousStatus = $locked->status;
            $locked->status = $approve ? EventPayoutRequest::APPROVED : EventPayoutRequest::REJECTED;
            $locked->reviewed_by = $reviewer->getKey();
            $locked->review_note = $note;
            $locked->reviewed_at = now();
            $locked->save();
            DB::table('event_payout_audits')->insert([
                'event_payout_request_id' => $locked->getKey(),
                'actor_id' => $reviewer->getKey(),
                'action' => $approve ? 'approved' : 'rejected',
                'from_status' => $previousStatus,
                'to_status' => $locked->status,
                'note' => $note,
                'created_at' => now(),
            ]);

            $this->notifyRequesterAfterCommit($locked);

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

        return DB::transaction(function () use ($request, $reviewer, $transferReference): EventPayoutRequest {
            Event::query()->whereKey($request->event_id)->lockForUpdate()->firstOrFail();
            $locked = EventPayoutRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== EventPayoutRequest::APPROVED) {
                throw ValidationException::withMessages(['transfer' => 'Payout must be approved before recording a transfer.']);
            }

            if (EventPayoutRequest::query()->where('transfer_reference', trim($transferReference))->exists()) {
                throw ValidationException::withMessages(['transfer' => 'Transfer reference already recorded.']);
            }

            $previousStatus = $locked->status;
            $locked->status = EventPayoutRequest::PAID;
            $locked->paid_at = now();
            $locked->transfer_reference = trim($transferReference);
            $locked->save();
            DB::table('event_payout_audits')->insert([
                'event_payout_request_id' => $locked->getKey(),
                'actor_id' => $reviewer->getKey(),
                'action' => 'paid',
                'from_status' => $previousStatus,
                'to_status' => $locked->status,
                'note' => 'Transfer reference: '.trim($transferReference),
                'created_at' => now(),
            ]);
            $this->notifyRequesterAfterCommit($locked);

            return $locked;
        });
    }
    private function notifyRequesterAfterCommit(EventPayoutRequest $request): void
    {
        $requestId = $request->getKey();

        DB::afterCommit(function () use ($requestId): void {
            try {
                $record = EventPayoutRequest::query()
                    ->with(['requester', 'event'])
                    ->find($requestId);

                if ($record?->requester && $record->event) {
                    $record->requester->notify(new EventPayoutStatusNotification(
                        $record->reference,
                        $record->event->name,
                        $record->status,
                    ));
                }
            } catch (\Throwable $exception) {
                Log::warning('Payout status notification failed', [
                    'payout_request_id' => $requestId,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }

}
