<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventFinanceClearanceService
{
    /**
     * Records an administrator-attested settlement or reversal. This is not
     * provider verification and cannot initiate an outgoing transfer.
     */
    /**
     * Second-person review of documentary evidence. This is still a manual
     * attestation; no provider or bank API verification is performed here.
     */
    public function independentlyVerify(int $entryId, User $verifier, string $note): void
    {
        if (! $verifier->isSuperAdmin() || mb_strlen(trim($note)) < 12) {
            throw ValidationException::withMessages(['verification' => 'Authorized verifier and detailed evidence note required.']);
        }

        DB::transaction(function () use ($entryId, $verifier, $note): void {
            $entry = DB::table('event_finance_clearance_entries')->where('id', $entryId)->first();
            if (! $entry) {
                throw ValidationException::withMessages(['verification' => 'Clearance entry not found.']);
            }
            Event::query()->whereKey($entry->event_id)->lockForUpdate()->firstOrFail();
            $entry = DB::table('event_finance_clearance_entries')->where('id', $entryId)->lockForUpdate()->first();
            if ($entry->voided_at !== null || $entry->independently_verified_at !== null || $entry->self_attested_at !== null || (int) $entry->recorded_by === (int) $verifier->getKey()) {
                throw ValidationException::withMessages(['verification' => 'Entry already reviewed or verifier is the original recorder.']);
            }
            DB::table('event_finance_clearance_entries')->where('id', $entryId)->update([
                'independently_verified_by' => $verifier->getKey(),
                'independently_verified_at' => now(),
                'independent_verification_note' => trim($note),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Check the effective policy, including legacy/role and system-admin fallbacks.
     * Stop early when a second super administrator is discovered.
     */
    public function singleSuperAdminIs(User $admin): bool
    {
        if (! $admin->isSuperAdmin()) {
            return false;
        }

        $count = 0;
        foreach (User::query()->cursor() as $candidate) {
            if ($candidate->isSuperAdmin()) {
                $count++;
                if ($count > 1) {
                    return false;
                }
            }
        }

        return $count === 1;
    }

    /**
     * Explicit single-administrator exception: a self-attestation is NOT an
     * independent verification and must always remain labelled as such.
     */
    public function selfAttest(int $entryId, User $admin, string $note): void
    {
        if (! $admin->isSuperAdmin() || mb_strlen(trim($note)) < 40) {
            throw ValidationException::withMessages([
                'verification' => 'Super administrator and a detailed evidence note (40+ characters) required.',
            ]);
        }

        DB::transaction(function () use ($entryId, $admin, $note): void {
            $entry = DB::table('event_finance_clearance_entries')->where('id', $entryId)->first();
            if (! $entry) {
                throw ValidationException::withMessages(['verification' => 'Clearance entry not found.']);
            }
            Event::query()->whereKey($entry->event_id)->lockForUpdate()->firstOrFail();
            $entry = DB::table('event_finance_clearance_entries')->where('id', $entryId)->lockForUpdate()->first();

            if (! $this->singleSuperAdminIs($admin)
                || (int) $entry->recorded_by !== (int) $admin->getKey()) {
                throw ValidationException::withMessages([
                    'verification' => 'Self-attestation is permitted only when exactly one super administrator exists and recorded this entry.',
                ]);
            }

            if ($entry->voided_at !== null || $entry->independently_verified_at !== null || $entry->self_attested_at !== null) {
                throw ValidationException::withMessages(['verification' => 'This entry has already been reviewed.']);
            }

            // Duplicate amount/type across the same event requires separate
            // reconciliation rather than signing off multiple suspect receipts.
            $duplicate = DB::table('event_finance_clearance_entries')
                ->where('event_id', $entry->event_id)
                ->where('entry_type', $entry->entry_type)
                ->where('amount', $entry->amount)
                ->where('id', '!=', $entryId)
                ->whereNull('voided_at')
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'verification' => 'Another entry has the same amount and type. Reconcile duplicate-looking receipts before using the single-admin exception.',
                ]);
            }

            DB::table('event_finance_clearance_entries')->where('id', $entryId)->update([
                'self_attested_by' => $admin->getKey(),
                'self_attested_at' => now(),
                'self_attestation_note' => trim($note),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Preserve mistaken entries and their evidence references without treating
     * them as money received. Voiding is only possible before verification and
     * before any payout for this event has been recorded as paid.
     */
    public function voidEntry(int $entryId, User $admin, string $reason): void
    {
        $reason = trim($reason);
        if (! $admin->isSuperAdmin() || mb_strlen($reason) < 20 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages([
                'void_reason' => 'A super administrator must provide a correction reason of 20–2000 characters.',
            ]);
        }

        DB::transaction(function () use ($entryId, $admin, $reason): void {
            $entry = DB::table('event_finance_clearance_entries')->where('id', $entryId)->first();
            if (! $entry) {
                throw ValidationException::withMessages(['void_reason' => 'Settlement entry not found.']);
            }

            Event::query()->whereKey($entry->event_id)->lockForUpdate()->firstOrFail();
            $entry = DB::table('event_finance_clearance_entries')
                ->where('id', $entryId)->lockForUpdate()->first();

            if ($entry->voided_at !== null) {
                throw ValidationException::withMessages(['void_reason' => 'This entry was already voided.']);
            }
            if ($entry->independently_verified_at !== null || $entry->self_attested_at !== null) {
                throw ValidationException::withMessages([
                    'void_reason' => 'Previously reviewed entries require a separate financial reconciliation process.',
                ]);
            }
            if (DB::table('event_payout_requests')
                ->where('event_id', $entry->event_id)->where('status', 'paid')->exists()) {
                throw ValidationException::withMessages([
                    'void_reason' => 'A completed payout exists for this event. Reconcile the payout before correcting settlement records.',
                ]);
            }

            DB::table('event_finance_clearance_entries')->where('id', $entryId)->update([
                'voided_by' => $admin->getKey(),
                'voided_at' => now(),
                'void_reason' => $reason,
                'updated_at' => now(),
            ]);
        });
    }

    public function record(
        Event $event, User $admin, string $type, string $amount,
        string $externalReference, string $note
    ): void {
        if (! $admin->isSuperAdmin()) {
            throw ValidationException::withMessages(['finance' => 'Platform administration required.']);
        }

        if (! in_array($type, ['settlement', 'refund', 'chargeback'], true)) {
            throw ValidationException::withMessages(['type' => 'Unsupported finance entry type.']);
        }

        if (! preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/D', $amount) || (float) $amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive amount with at most two decimal places.']);
        }

        $reference = trim($externalReference);
        if ($reference === '' || mb_strlen($reference) > 255 || mb_strlen(trim($note)) < 12) {
            throw ValidationException::withMessages(['reference' => 'An external evidence reference and verification note (12+ characters) are required.']);
        }

        DB::transaction(function () use ($event, $admin, $type, $amount, $reference, $note): void {
            Event::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();

            if (DB::table('event_finance_clearance_entries')->where('external_reference', $reference)->exists()) {
                throw ValidationException::withMessages(['reference' => 'This evidence reference was already recorded.']);
            }

            DB::table('event_finance_clearance_entries')->insert([
                'event_id' => $event->getKey(),
                'recorded_by' => $admin->getKey(),
                'entry_type' => $type,
                'amount' => $amount,
                'currency' => 'TZS',
                'external_reference' => $reference,
                'verification_note' => trim($note),
                'verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
