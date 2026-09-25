<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentReconciliationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Throwable;
use UnitEnum;

class PaymentReconciliation extends Page
{
    protected static ?string $navigationLabel = 'Payment Reconciliation';

    protected static string|UnitEnum|null $navigationGroup = 'Ticketing';

    protected static ?string $title = 'Payment Reconciliation';

    protected static ?string $slug = 'payment-reconciliation';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.payment-reconciliation';

    public ?int $selectedEventId = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->isSuperAdmin()
            || $user->isTicketOrganizer()
            || $user->managedOrganizations()->exists()
            || $user->eventManagerEvents()->exists();
    }

    public function mount(): void
    {
        $events = $this->eventOptions();

        if ($this->selectedEventId === null && count($events) > 0) {
            $this->selectedEventId = (int) array_key_first($events);
        }
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function eventOptions(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            return Event::query()
                ->orderByDesc('starts_at')
                ->pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])
                ->toArray();
        }

        if ($user->isTicketOrganizer()) {
            $ids = $user->assignedTicketingEventIds();

            if ($ids->isEmpty()) {
                return [];
            }

            return Event::query()
                ->whereIn('id', $ids)
                ->orderByDesc('starts_at')
                ->pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])
                ->toArray();
        }

        return Event::query()
            ->accessibleBy($user)
            ->orderByDesc('starts_at')
            ->get()
            ->filter(fn (Event $event): bool => $event->canBeManagedBy($user))
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])
            ->toArray();
    }

    public function reconciliationRows(): array
    {
        $event = $this->authorizedSelectedEvent();

        if (! $event) {
            return [];
        }

        return app(PaymentReconciliationService::class)
            ->issuesForEvent($event)
            ->all();
    }

    public function summary(): array
    {
        $rows = collect($this->reconciliationRows());
        $event = $this->authorizedSelectedEvent();

        $hasCode = fn (array $row, array $codes): bool => collect($row['issues'] ?? [])
            ->contains(fn (array $issue): bool => in_array($issue['code'] ?? '', $codes, true));

        return [
            'needs_attention' => $rows->count(),
            'unfulfilled_completed' => $rows->filter(
                fn (array $row): bool => $hasCode($row, ['completed_unfulfilled', 'tickets_missing', 'completed_payment_order_not_paid'])
            )->count(),
            'pending_verification' => $rows->filter(
                fn (array $row): bool => $hasCode($row, ['pending_provider_verification', 'missing_provider_tracking_id'])
            )->count(),
            'amount_currency_mismatches' => $rows->filter(
                fn (array $row): bool => $hasCode($row, ['amount_mismatch', 'currency_mismatch'])
            )->count(),
            'resolved_today' => $event
                ? AuditLog::query()
                    ->where('event_id', $event->id)
                    ->whereIn('action', ['payment.resync', 'payment.fulfillment_retry'])
                    ->whereDate('created_at', today())
                    ->count()
                : 0,
        ];
    }

    public function resyncPayment(int $paymentId): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        try {
            $payment = Payment::query()->findOrFail($paymentId);
            $updated = app(PaymentReconciliationService::class)->resync($payment, $user);

            Notification::make()
                ->title('Payment synchronized')
                ->body("{$updated->reference}: {$updated->status}")
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Payment synchronization failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function retryFulfillment(int $paymentId): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        try {
            $payment = Payment::query()->findOrFail($paymentId);
            $updated = app(PaymentReconciliationService::class)->retryFulfillment($payment, $user);

            Notification::make()
                ->title('Fulfillment completed')
                ->body("{$updated->reference} was processed through the verified fulfillment pipeline.")
                ->success()
                ->send();
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Fulfillment retry blocked')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Fulfillment retry failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    private function authorizedSelectedEvent(): ?Event
    {
        if (! $this->selectedEventId) {
            return null;
        }

        $event = Event::query()->find($this->selectedEventId);
        $user = Auth::user();

        if (! $event || ! $user instanceof User) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            return $event;
        }

        if ($user->isTicketOrganizer()) {
            return $user->assignedTicketingEvents()
                ->where('events.id', $event->id)
                ->exists()
                ? $event
                : null;
        }

        return $event->canBeManagedBy($user) ? $event : null;
    }
}
