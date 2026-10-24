<?php

namespace App\Filament\Resources\Payouts\Pages;

use App\Filament\Resources\Payouts\PayoutResource;
use App\Models\EventPayoutRequest;
use App\Models\User;
use App\Services\EventPayoutReviewService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;

    public function getSubheading(): ?string
    {
        return 'Internal payout requests. A paid status must be backed by an independently confirmed transfer.';
    }

    protected function getTableRecordActions(): array
    {
        return [];
    }
}
