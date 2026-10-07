<?php

namespace App\Filament\Pages;

use App\Models\DonationCampaign;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentReconciliationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

class DonationPaymentReconciliation extends Page
{
    protected static string|UnitEnum|null $navigationGroup =
        'Donations';

    protected static ?string $navigationLabel =
        'Donation Payments';

    protected static ?string $title =
        'Donation Payment Reconciliation';

    protected static ?string $slug =
        'donations/payments';

    protected static ?int $navigationSort = 60;

    protected string $view =
        'filament.pages.donation-payment-reconciliation';

    public ?int $selectedCampaignId = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && (
                $user->isSuperAdmin()
                || $user->managedOrganizations()->exists()
                || $user->eventManagerEvents()->exists()
            );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function campaignOptions(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        return DonationCampaign::query()
            ->accessibleBy($user)
            ->whereIn('payment_mode', [
                DonationCampaign::PAYMENT_MODE_PLATFORM,
                DonationCampaign::PAYMENT_MODE_HYBRID,
            ])
            ->orderBy('title')
            ->pluck('title', 'id')
            ->mapWithKeys(
                fn ($title, $id) => [(int) $id => $title]
            )
            ->toArray();
    }

    public function reconciliationRows(): array
    {
        $campaign = $this->selectedCampaign();

        if (! $campaign) {
            return [];
        }

        return app(PaymentReconciliationService::class)
            ->issuesForDonationCampaign($campaign)
            ->all();
    }

    public function resyncPayment(int $paymentId): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        try {
            $payment = $this->authorizedPayment($paymentId);

            $updated = app(PaymentReconciliationService::class)
                ->resync($payment, $user);

            Notification::make()
                ->title('Donation payment synchronized')
                ->body("{$updated->reference}: {$updated->status}")
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Synchronization failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function retryFulfillment(int $paymentId): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        try {
            $payment = $this->authorizedPayment($paymentId);

            app(PaymentReconciliationService::class)
                ->retryFulfillment($payment, $user);

            Notification::make()
                ->title('Donation fulfillment completed')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Fulfillment retry failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    private function selectedCampaign(): ?DonationCampaign
    {
        $user = Auth::user();

        if (
            ! $user instanceof User
            || ! $this->selectedCampaignId
        ) {
            return null;
        }

        return DonationCampaign::query()
            ->accessibleBy($user)
            ->find($this->selectedCampaignId);
    }

    private function authorizedPayment(
        int $paymentId
    ): Payment {
        $campaign = $this->selectedCampaign();

        abort_unless($campaign, 403);

        return Payment::query()
            ->whereKey($paymentId)
            ->whereHas(
                'donation',
                fn ($query) => $query
                    ->where(
                        'donation_campaign_id',
                        $campaign->id
                    )
            )
            ->firstOrFail();
    }
}
