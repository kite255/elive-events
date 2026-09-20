<?php

namespace App\Filament\Pages;

use App\Services\Finance\AdminFinanceMetricsService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AdminFinanceOverview extends Page
{
    protected static ?string $navigationLabel = 'Finance Overview';

    protected static string | UnitEnum | null $navigationGroup = 'Finance';

    protected static ?string $title = 'Finance Overview';

    protected static ?string $slug = 'finance-overview';

    protected string $view = 'filament.pages.admin-finance-overview';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return method_exists($user, 'isSuperAdmin')
            && $user->isSuperAdmin();
    }

    public function financeMetrics(): array
    {
        return app(
            AdminFinanceMetricsService::class
        )->platformTotals();
    }

    public function financeMetricDefinitions(): array
    {
        return [
            'gross_sales' => [
                'label' => 'Gross Sales',
            ],

            'platform_commission_amount' => [
                'label' => 'eLive Commission',
            ],

            'platform_commission_rate' => [
                'label' => 'eLive Commission Rate',
            ],

            'gateway_fee_amount' => [
                'label' => 'Gateway Fee',
            ],

            'gateway_fee_rate' => [
                'label' => 'Gateway Fee Rate',
            ],

            'gateway_fee_bearer' => [
                'label' => 'Gateway Fee Bearer',
            ],

            'total_charges' => [
                'label' => 'Total Charges',
            ],

            'organizer_net_amount' => [
                'label' => 'Organizer Net Payable',
            ],
        ];
    }
}