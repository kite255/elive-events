<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payments\SelcomService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcileExpiredSelcomPayments extends Command
{
    protected $signature = 'payments:reconcile-expired-selcom
        {--lookback-hours=72 : Include payments expired or updated in this many hours (1-720)}
        {--limit=50 : Maximum expired payments to check (1-100)}';

    protected $description = 'Check recently expired Selcom payments and flag late completions for manual review; never fulfill automatically';

    public function handle(SelcomService $selcom): int
    {
        $hours = filter_var($this->option('lookback-hours'), FILTER_VALIDATE_INT);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if ($hours === false || $hours < 1 || $hours > 720
            || $limit === false || $limit < 1 || $limit > 100) {
            $this->error('Valid options: --lookback-hours=1..720 and --limit=1..100.');
            return self::INVALID;
        }

        $summary = ['checked' => 0, 'pending' => 0, 'review' => 0, 'already_flagged' => 0, 'other' => 0, 'failed' => 0];

        // No automatic retries, no scheduler, no payment status changes, no fulfillment.
        $payments = Payment::query()
            ->where('status', Payment::STATUS_EXPIRED)
            ->where('updated_at', '>=', now()->subHours($hours))
            ->whereNotNull('provider_tracking_id')
            ->where('provider_tracking_id', '<>', '')
            ->whereHas('gateway', fn ($query) => $query->where('code', 'selcom'))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($payments as $payment) {
            try {
                $response = $selcom->getPaymentStatus((string) $payment->provider_tracking_id);
                $status = strtoupper(trim((string) ($response['payment_status_description'] ?? '')));
                $reference = (string) ($response['merchant_reference'] ?? '');
                $amount = $response['amount'] ?? null;

                // The provider's order ID, amount and currency must match
                // before any record can be accepted as evidence for review.
                if ($reference !== (string) $payment->reference
                    || ! is_scalar($amount)
                    || ! is_numeric((string) $amount)
                    || bccomp((string) $amount, (string) $payment->amount, 2) !== 0
                    || ($response['currency'] ?? null) !== 'TZS'
                    || strtoupper((string) $payment->currency) !== 'TZS') {
                    throw new \RuntimeException('Selcom expired-payment verification mismatch.');
                }

                $summary['checked']++;

                if ($status === 'PENDING' || $status === 'INPROGRESS') {
                    $summary['pending']++;
                    continue;
                }

                if ($status !== 'COMPLETED') {
                    $summary['other']++;
                    continue;
                }

                if (trim((string) ($response['confirmation_code'] ?? '')) === '') {
                    throw new \RuntimeException('Completed Selcom payment has no confirmation code.');
                }

                // Create a deduplicated, sanitized manual-review audit.
                // Leave the payment expired until a controlled recovery or
                // refund workflow verifies capacity and financial settlement.
                $created = DB::transaction(function () use ($payment, $status, $amount): bool {
                    $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

                    if ($locked->status !== Payment::STATUS_EXPIRED) {
                        return false;
                    }

                    $existing = $locked->transactions()
                        ->where('type', 'expired_selcom_review')
                        ->exists();
                    if ($existing) {
                        return false;
                    }

                    $locked->transactions()->create([
                        'type' => 'expired_selcom_review',
                        'status' => 'requires_review',
                        'response_payload' => [
                            'provider_status' => $status,
                            'verified_amount' => (string) $amount,
                            'checked_at' => now()->toIso8601String(),
                        ],
                    ]);

                    return true;
                });

                $summary[$created ? 'review' : 'already_flagged']++;
                if ($created) {
                    Log::warning('Expired Selcom payment requires manual financial review.', [
                        'payment_id' => $payment->id,
                        'reason' => 'late_provider_completion',
                    ]);
                }
            } catch (Throwable $exception) {
                $summary['failed']++;
                Log::warning('Expired Selcom status verification failed.', [
                    'payment_id' => $payment->id,
                    'exception_class' => $exception::class,
                ]);
            }
        }

        $this->info(sprintf(
            'Expired Selcom check complete. Checked: %d, Pending: %d, New reviews: %d, Already flagged: %d, Other: %d, Failed: %d.',
            $summary['checked'],
            $summary['pending'],
            $summary['review'],
            $summary['already_flagged'],
            $summary['other'],
            $summary['failed']
        ));

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
