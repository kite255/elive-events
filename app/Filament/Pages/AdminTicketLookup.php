<?php

namespace App\Filament\Pages;

use App\Services\Tickets\AdminTicketLookupService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AdminTicketLookup extends Page
{
    protected static ?string $navigationLabel = 'Manual Lookup';

    protected static string | UnitEnum | null $navigationGroup =
        'Ticketing';

    protected static ?string $title = 'Admin Manual Lookup';

    protected static ?string $slug = 'admin-ticket-lookup';

    protected string $view =
        'filament.pages.admin-ticket-lookup';

    public string $search = '';

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }

    public function getResultsProperty(): array
    {
        $user = Auth::user();

        if (! $user || trim($this->search) === '') {
            return [];
        }

        return app(
            AdminTicketLookupService::class
        )->search(
            $user,
            $this->search
        );
    }
}
