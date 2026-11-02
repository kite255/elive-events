<?php

namespace Tests\Feature;

use App\Models\EventPayoutRequest;
use App\Models\User;
use App\Services\EventPayoutReviewService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayoutReviewAuthorizationTest extends TestCase
{
    public function test_non_admin_cannot_approve_a_payout(): void
    {
        $reviewer = new User(['name' => 'Organizer', 'email' => 'organizer@example.test']);
        $reviewer->is_super_admin = false;
        $request = new EventPayoutRequest();

        $this->expectException(ValidationException::class);

        app(EventPayoutReviewService::class)->review($request, $reviewer, true);
    }

    public function test_non_admin_cannot_record_a_completed_transfer(): void
    {
        $reviewer = new User(['name' => 'Organizer', 'email' => 'organizer@example.test']);
        $reviewer->is_super_admin = false;
        $request = new EventPayoutRequest();

        $this->expectException(ValidationException::class);

        app(EventPayoutReviewService::class)->recordCompletedTransfer(
            $request,
            $reviewer,
            'BANK-TRANSFER-123', 'bank', now()->toDateString(), '100.00', 'payout-proofs/receipt.pdf'
        );
    }

    public function test_transfer_reference_is_required(): void
    {
        $reviewer = new User(['name' => 'Admin', 'email' => 'admin@example.test']);
        $reviewer->is_super_admin = true;

        $this->expectException(ValidationException::class);

        app(EventPayoutReviewService::class)->recordCompletedTransfer(
            new EventPayoutRequest(),
            $reviewer,
            ' ', 'bank', now()->toDateString(), '100.00', 'payout-proofs/receipt.pdf'
        );
    }
}
