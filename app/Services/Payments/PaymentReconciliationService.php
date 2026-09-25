<?php

namespace App\Services\Payments;

use App\Models\Event;
use App\Models\Payment;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Collection;
use RuntimeException;

class PaymentReconciliationService
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentFulfillmentService $fulfillmentService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function issuesForEvent(Event $event): Collection
    {
        $rows = collect();

        $payments = Payment::query()
            ->where('event_id', $event->id)
            ->where(function ($query): void {
                $query->whereNotNull('ticket_order_id')
                    ->orWhereNotNull('ticket_upgrade_id');
            })
            ->with([
                'event',
                'attendee',
                'ticketOrder.tickets',
                'ticketUpgrade.order',
                'ticketUpgrade.ticket',
                'gateway',
            ])
            ->latest('id')
            ->get();

        foreach ($payments as $payment) {
            $issues = $this->issuesForPayment($payment);

            if ($issues === []) {
                continue;
            }

            $rows->push($this->paymentRow($payment, $issues));
        }

        $ordersWithoutCompletedPayments = TicketOrder::query()
            ->where('event_id', $event->id)
            ->where('status', TicketOrder::STATUS_PAID)
            ->where('total', '>', 0)
            ->whereHas(
                'event.paymentSetting',
                fn ($query) => $query
                    ->where('payments_enabled', true)
                    ->where('allow_manual_payment', false)
            )
            ->whereDoesntHave('payments', fn ($query) => $query->where('status', Payment::STATUS_COMPLETED))
            ->withCount('tickets')
            ->latest('id')
            ->get();

        foreach ($ordersWithoutCompletedPayments as $order) {
            $rows->push([
                'payment_id' => null,
                'reference' => '—',
                'provider_tracking_id' => null,
                'event_id' => $event->id,
                'event_name' => $event->name,
                'customer' => $order->buyer_name ?: 'Customer',
                'local_status' => 'missing',
                'provider_status' => null,
                'order_id' => $order->id,
                'order_reference' => $order->order_number,
                'order_public_token' => $order->public_token,
                'order_status' => $order->status,
                'expected_amount' => (float) $order->total,
                'actual_amount' => null,
                'expected_currency' => strtoupper((string) $order->currency),
                'actual_currency' => null,
                'tickets_issued' => (int) $order->tickets_count,
                'expected_tickets' => (int) $order->quantity,
                'fulfilled_at' => null,
                'can_resync' => false,
                'can_retry_fulfillment' => false,
                'issues' => [[
                    'code' => 'paid_order_without_completed_payment',
                    'message' => 'Order is marked paid but no completed payment is linked to it.',
                ]],
            ]);
        }

        return $rows->values();
    }

    public function resync(Payment $payment, User $actor): Payment
    {
        $this->authorize($payment, $actor);

        if (blank($payment->provider_tracking_id)) {
            throw new RuntimeException('Provider tracking ID is missing.');
        }

        $beforeStatus = $payment->status;
        $updated = $this->paymentService->syncFromGateway($payment);

        $this->auditLogService->record(
            'payment.resync',
            $updated,
            $actor,
            [
                'payment_reference' => $updated->reference,
                'previous_status' => $beforeStatus,
                'current_status' => $updated->status,
            ]
        );

        return $updated;
    }

    public function retryFulfillment(Payment $payment, User $actor): Payment
    {
        $this->authorize($payment, $actor);

        if (! $payment->isCompleted()) {
            throw new RuntimeException('Only verified completed payments can be fulfilled.');
        }

        if ($payment->isFulfilled()) {
            throw new RuntimeException('This payment has already been fulfilled.');
        }

        $updated = $this->fulfillmentService->fulfill($payment);

        $this->auditLogService->record(
            'payment.fulfillment_retry',
            $updated,
            $actor,
            [
                'payment_reference' => $updated->reference,
                'fulfilled_at' => $updated->fulfilled_at?->toIso8601String(),
            ]
        );

        return $updated;
    }

    private function issuesForPayment(Payment $payment): array
    {
        $issues = [];
        [$expectedAmount, $expectedCurrency] = $this->expectedFinancials($payment);

        if ($expectedAmount !== null && abs((float) $payment->amount - $expectedAmount) > 0.009) {
            $issues[] = [
                'code' => 'amount_mismatch',
                'message' => 'Payment amount does not match the expected order or upgrade amount.',
            ];
        }

        if (
            $expectedCurrency !== null
            && strtoupper((string) $payment->currency) !== $expectedCurrency
        ) {
            $issues[] = [
                'code' => 'currency_mismatch',
                'message' => 'Payment currency does not match the expected order or upgrade currency.',
            ];
        }

        if ($payment->isCompleted() && ! $payment->isFulfilled()) {
            $issues[] = [
                'code' => 'completed_unfulfilled',
                'message' => 'Payment is completed but fulfillment has not finished.',
            ];
        }

        if ($payment->isCompleted() && $payment->ticket_order_id && $payment->ticketOrder) {
            $issued = $payment->ticketOrder->tickets->count();
            $expected = (int) $payment->ticketOrder->quantity;

            if ($issued < $expected) {
                $issues[] = [
                    'code' => 'tickets_missing',
                    'message' => "Payment is completed but only {$issued} of {$expected} expected ticket(s) exist.",
                ];
            }

            if (! $payment->ticketOrder->isPaid()) {
                $issues[] = [
                    'code' => 'completed_payment_order_not_paid',
                    'message' => 'Payment is completed but the linked order is not marked paid.',
                ];
            }
        }

        if (
            in_array($payment->status, [Payment::STATUS_PROCESSING, Payment::STATUS_COMPLETED], true)
            && blank($payment->provider_tracking_id)
            && $payment->payment_method !== 'free'
        ) {
            $issues[] = [
                'code' => 'missing_provider_tracking_id',
                'message' => 'Provider tracking ID is missing for an online payment.',
            ];
        }

        if (
            in_array($payment->status, [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING], true)
            && filled($payment->provider_tracking_id)
        ) {
            $issues[] = [
                'code' => 'pending_provider_verification',
                'message' => 'Payment is still pending locally and can be re-synchronized with Pesapal.',
            ];
        }

        return $issues;
    }

    private function expectedFinancials(Payment $payment): array
    {
        if ($payment->ticket_upgrade_id && $payment->ticketUpgrade) {
            return [
                (float) $payment->ticketUpgrade->upgrade_amount,
                strtoupper((string) $payment->ticketUpgrade->currency),
            ];
        }

        if ($payment->ticket_order_id && $payment->ticketOrder) {
            return [
                (float) $payment->ticketOrder->total,
                strtoupper((string) $payment->ticketOrder->currency),
            ];
        }

        return [null, null];
    }

    private function paymentRow(Payment $payment, array $issues): array
    {
        [$expectedAmount, $expectedCurrency] = $this->expectedFinancials($payment);
        $order = $payment->ticketOrder ?: $payment->ticketUpgrade?->order;
        $ticketsIssued = $payment->ticketOrder?->tickets?->count()
            ?? ($payment->ticketUpgrade?->ticket ? 1 : 0);
        $expectedTickets = $payment->ticketOrder
            ? (int) $payment->ticketOrder->quantity
            : ($payment->ticket_upgrade_id ? 1 : null);

        return [
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'provider_tracking_id' => $payment->provider_tracking_id,
            'event_id' => $payment->event_id,
            'event_name' => $payment->event?->name ?? '—',
            'customer' => $order?->buyer_name ?? $payment->attendee?->full_name ?? 'Customer',
            'local_status' => $payment->status,
            'provider_status' => data_get($payment->metadata, 'pesapal_status'),
            'order_id' => $order?->id,
            'order_reference' => $payment->ticketUpgrade?->reference ?? $order?->order_number,
            'order_public_token' => $order?->public_token,
            'order_status' => $payment->ticketUpgrade?->status ?? $order?->status,
            'expected_amount' => $expectedAmount,
            'actual_amount' => (float) $payment->amount,
            'expected_currency' => $expectedCurrency,
            'actual_currency' => strtoupper((string) $payment->currency),
            'tickets_issued' => $ticketsIssued,
            'expected_tickets' => $expectedTickets,
            'fulfilled_at' => $payment->fulfilled_at,
            'can_resync' => filled($payment->provider_tracking_id) && $payment->payment_method !== 'free',
            'can_retry_fulfillment' => $payment->isCompleted() && ! $payment->isFulfilled(),
            'issues' => $issues,
        ];
    }

    private function authorize(Payment $payment, User $actor): void
    {
        $payment->loadMissing('event');
        $event = $payment->event;

        if (! $event) {
            throw new RuntimeException('Payment event is missing.');
        }

        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($actor->isTicketOrganizer()) {
            if ($actor->assignedTicketingEvents()->where('events.id', $event->id)->exists()) {
                return;
            }

            throw new RuntimeException('You are not allowed to manage this payment.');
        }

        if (! $event->canBeManagedBy($actor)) {
            throw new RuntimeException('You are not allowed to manage this payment.');
        }
    }
}
