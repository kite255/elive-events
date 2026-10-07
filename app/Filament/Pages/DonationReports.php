<?php

namespace App\Filament\Pages;

use App\Models\DonationCampaign;
use App\Models\User;
use App\Services\Donations\DonationReportService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class DonationReports extends Page
{
    protected static string|UnitEnum|null $navigationGroup =
        'Donations';

    protected static ?string $navigationLabel =
        'Reports';

    protected static ?string $title =
        'Donation Reports';

    protected static ?string $slug =
        'donations/reports';

    protected static ?int $navigationSort = 50;

    protected string $view =
        'filament.pages.donation-reports';

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
            ->orderBy('title')
            ->pluck('title', 'id')
            ->mapWithKeys(
                fn ($title, $id) => [(int) $id => $title]
            )
            ->toArray();
    }

    public function summary(): array
    {
        $user = Auth::user();

        if (
            ! $user instanceof User
            || ! $this->selectedCampaignId
        ) {
            return [];
        }

        $campaign = DonationCampaign::query()
            ->accessibleBy($user)
            ->find($this->selectedCampaignId);

        if (! $campaign) {
            return [];
        }

        return app(DonationReportService::class)
            ->summaryForCampaign($campaign);
    }
}
