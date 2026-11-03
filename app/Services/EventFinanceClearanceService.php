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
            if ($entry->independently_verified_at !== null || $entry->self_attested_at !== null || (int) $entry->recorded_by === (int) $verifier->getKey()) {
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

            if (User::query()->where('is_super_admin', true)->count() !== 1
                || (int) $entry->recorded_by !== (int) $admin->getKey()) {
                throw ValidationException::withMessages([
                    'verification' => 'Self-attestation is permitted only when exactly one super administrator exists and recorded this entry.',
                ]);
            }

            if ($entry->independently_verified_at !== null || $entry->self_attested_at !== null) {
                throw ValidationException::withMessages(['verification' => 'This entry has already been reviewed.']);
            }

            // Duplicate amount/type across the same event requires separate
            // reconciliation rather than signing off multiple suspect receipts.
            $duplicate = DB::table('event_finance_clearance_entries')
                ->where('event_id', $entry->event_id)
                ->where('entry_type', $entry->entry_type)
                ->where('amount', $entry->amount)
                ->where('id', '!=', $entryId)
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
