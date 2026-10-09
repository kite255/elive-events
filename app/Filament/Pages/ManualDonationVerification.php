<?php

namespace App\Filament\Pages;

use App\Models\Donation;
use App\Models\User;
use App\Services\Donations\ManualDonationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ManualDonationVerification extends Page
{
    protected static string|UnitEnum|null $navigationGroup =
        'Donations';

    protected static ?string $navigationLabel =
        'Manual Verification';

    protected static ?string $title =
        'Manual Donation Verification';

    protected static ?string $slug =
        'donations/manual-verification';

    protected static ?int $navigationSort = 40;

    protected string $view =
        'filament.pages.manual-donation-verification';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && (
                $user->isSuperAdmin()
                || $user->managedOrganizations()->exists()
            );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function pendingDonations(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        return Donation::query()
            ->accessibleBy($user)
            ->where('status', Donation::STATUS_AWAITING_VERIFICATION)
            ->with([
                'campaign',
                'manualSubmission.paymentMethod',
            ])
            ->latest('id')
            ->get()
            ->all();
    }

    public function approveDonation(int $donationId): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $donation = Donation::query()
            ->accessibleBy($user)
            ->findOrFail($donationId);

        app(ManualDonationService::class)
            ->approve($donation, $user);

        Notification::make()
            ->title('Donation approved')
            ->success()
            ->send();
    }

    public function rejectDonation(
        int $donationId,
        string $reason
    ): void {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $donation = Donation::query()
            ->accessibleBy($user)
            ->findOrFail($donationId);

        app(ManualDonationService::class)
            ->reject($donation, $user, $reason);

        Notification::make()
            ->title('Donation rejected')
            ->success()
            ->send();
    }
}
