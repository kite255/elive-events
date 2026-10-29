<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Services\EventPayoutService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayoutRequestSettlementGuardTest extends TestCase
{
    public function test_payout_request_is_rejected_without_reconciled_funds(): void
    {
        $admin = new User();
        $admin->is_super_admin = true;
        $event = new Event();

        $this->expectException(ValidationException::class);

        app(EventPayoutService::class)->request(
            $event, $admin, 100.00, 'bank', 'Test bank destination'
        );
    }
}
