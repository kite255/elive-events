<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentFulfillmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecoverRegistrationPayment extends Command
{
    protected $signature = 'payments:recover-registration
        {payment : Exact local payment ID}
        {--execute : Explicitly perform fulfillment after independent provider verification}';

    protected $description = 'Preview or recover one verified completed registration payment';

    public function handle(PaymentFulfillmentService $fulfillment): int
    {
        $id = filter_var($this->argument('payment'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            $this->error('Invalid payment ID.');

            return self::INVALID;
        }

        $payment = Payment::with('attendee.event')->find($id);
        if (! $payment || ! $payment->isCompleted() || $payment->isFulfilled()
            || ! $payment->attendee_id || $payment->ticket_order_id || $payment->ticket_upgrade_id
            || $payment->donation_id || $payment->merchandise_order_id
            || ! $payment->paid_at || blank($payment->provider_tracking_id)
            || ! $payment->attendee?->isApproved()
            || ! $payment->attendee->event
            || (int) $payment->attendee->event_id !== (int) $payment->event_id
            || (int) $payment->attendee->event->organization_id !== (int) $payment->organization_id) {
            $this->error('Payment is not eligible for registration recovery. Review manually.');

            return self::FAILURE;
        }

        // Require recorded provider status evidence matching the original
        // payment, not the current event fee. Do not infer payment from a
        // local completed flag alone.
        $evidence = $payment->transactions()
            ->where('type', PaymentTransaction::TYPE_PAYMENT_STATUS)
            ->latest('id')->first();

        $response = $evidence?->response_payload ?? [];
        $amount = data_get($response, 'amount');
        if (! $evidence || $evidence->status !== Payment::STATUS_COMPLETED
            || strtoupper(trim((string) data_get($response, 'payment_status_description'))) !== 'COMPLETED'
            || (string) data_get($response, 'merchant_reference') !== $payment->reference
            || (string) data_get($response, 'order_tracking_id') !== $payment->provider_tracking_id
            || strtoupper(trim((string) data_get($response, 'currency'))) !== strtoupper($payment->currency)
            || ! is_numeric($amount)
            || bccomp(number_format((float) $amount, 2, '.', ''), (string) $payment->amount, 2) !== 0) {
            $this->error('Matching completed provider evidence is missing. Review manually.');

            return self::FAILURE;
        }

        $conflict = Payment::query()
            ->where('attendee_id', $payment->attendee_id)
            ->where('id', '!=', $payment->id)
            ->whereNull('ticket_order_id')->whereNull('ticket_upgrade_id')
            ->whereNull('donation_id')->whereNull('merchandise_order_id')
            ->where(function ($query): void {
                $query->whereNotNull('fulfilled_at')->orWhere('status', Payment::STATUS_COMPLETED);
            })->exists();

        if ($conflict) {
            $this->error('Conflicting registration payment exists. Manual reconciliation required.');

            return self::FAILURE;
        }

        $this->table(['Payment', 'Event', 'Amount', 'Currency'], [
            [$payment->id, $payment->event_id, $payment->amount, $payment->currency],
        ]);

        if (! $this->option('execute')) {
            $this->info('Preview only. Independently verify current provider status before using --execute.');

            return self::SUCCESS;
        }

        // The operator must verify the current provider status independently.
        // Historical evidence is not proof that no reversal occurred later.
        if (! $this->confirm('Have you independently verified this exact payment is still completed and not reversed at the provider?', false)) {
            $this->warn('Recovery cancelled.');

            return self::FAILURE;
        }

        Log::notice('Manual registration fulfillment recovery requested.', ['payment_id' => $payment->id]);
        try {
            $result = $fulfillment->fulfill($payment);
            if (! $result->isFulfilled()) {
                throw new \RuntimeException('Fulfillment marker is missing.');
            }
            Log::notice('Manual registration fulfillment recovery completed.', ['payment_id' => $payment->id]);
            $this->info('Registration fulfillment completed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Recovery failed. Review payment and logs before retrying.');

            return self::FAILURE;
        }
    }
}
