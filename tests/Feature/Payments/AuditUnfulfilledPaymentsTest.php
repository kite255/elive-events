<?php

namespace Tests\Feature\Payments;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuditUnfulfilledPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_reports_only_old_completed_unfulfilled_payments_and_does_not_modify_them(): void
    {
        Queue::fake();
        $organization = Organization::create(['name' => 'Fulfillment audit organization']);
        $event = Event::create([
            'organization_id' => $organization->id,
            'name' => 'Fulfillment audit event',
            'status' => Event::STATUS_ACTIVE,
        ]);
        $otherEvent = Event::create([
            'organization_id' => $organization->id,
            'name' => 'Other fulfillment audit event',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $create = function (array $overrides = []) use ($organization, $event): Payment {
            $payment = Payment::create(array_merge([
                'organization_id' => $organization->id,
                'event_id' => $event->id,
                'reference' => 'AUDIT-'.uniqid(),
                'amount' => 50000,
                'currency' => 'TZS',
                'status' => Payment::STATUS_COMPLETED,
                'paid_at' => now()->subHour(),
            ], $overrides));
            $payment->forceFill(['updated_at' => now()->subHour()])->save();

            return $payment;
        };

        $candidate = $create();
        $create(['status' => Payment::STATUS_PENDING]);
        $create(['fulfilled_at' => now()->subHour()]);
        $create(['event_id' => $otherEvent->id]);
        $recent = $create();
        $recent->forceFill(['updated_at' => now()])->save();

        $before = Payment::query()->orderBy('id')->get()->map->getAttributes()->all();

        $this->artisan('payments:audit-unfulfilled', ['--event' => $event->id])
            ->expectsOutput('Completed unfulfilled payments: 1')
            ->expectsOutput('Read-only audit. No payments modified, no gateway queried, no jobs dispatched.')
            ->assertExitCode(0);

        $this->assertSame($before, Payment::query()->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertNull($candidate->fresh()->fulfilled_at);
        Queue::assertNothingPushed();
    }

    public function test_invalid_audit_options_are_rejected(): void
    {
        foreach ([['--minutes' => '0'], ['--limit' => '1001'], ['--event' => '-1']] as $options) {
            $this->artisan('payments:audit-unfulfilled', $options)->assertExitCode(2);
        }
    }
}
