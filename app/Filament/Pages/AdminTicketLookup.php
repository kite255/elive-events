<?php

namespace App\Filament\Pages;

use App\Models\TicketType;
use App\Services\Payments\TicketUpgradePaymentService;
use App\Services\Tickets\AdminTicketLookupService;
use App\Services\Tickets\ManualTicketResendService;
use App\Services\Tickets\TicketUpgradeService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use UnitEnum;

class AdminTicketLookup extends Page
{
    protected static ?string $navigationLabel = 'Manual Lookup';

    protected static string | UnitEnum | null $navigationGroup = 'Ticketing';

    protected static ?string $title = 'Admin Manual Lookup';

    protected static ?string $slug = 'admin-ticket-lookup';

    protected string $view = 'filament.pages.admin-ticket-lookup';

    public string $search = '';

    /** @var array<int, int|string|null> */
    public array $upgradeTargets = [];

    /** @var array<int, string> */
    public array $upgradeLinks = [];

    /** @var array<int, array<int, string>> */
    public array $resendChannels = [];

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

        return app(AdminTicketLookupService::class)->search($user, $this->search);
    }

    public function resendTicket(int $ticketId): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        try {
            $ticket = app(AdminTicketLookupService::class)
                ->findAuthorizedTicket($user, $ticketId);

            if (! $ticket->order) {
                throw new RuntimeException('Ticket order is unavailable.');
            }

            $channels = $this->resendChannels[$ticketId] ?? [];

            $result = app(ManualTicketResendService::class)->queue(
                $ticket->order,
                $channels,
                $user
            );

            Notification::make()
                ->title('Ticket access queued')
                ->body('Queued via: ' . implode(', ', $result['queued']))
                ->success()
                ->send();
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Ticket could not be resent')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function createUpgrade(int $ticketId): void
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        $targetId = (int) ($this->upgradeTargets[$ticketId] ?? 0);

        if ($targetId <= 0) {
            Notification::make()
                ->title('Select an upgrade ticket type')
                ->warning()
                ->send();

            return;
        }

        try {
            $lookupService = app(AdminTicketLookupService::class);
            $ticket = $lookupService->findAuthorizedTicket($user, $ticketId);

            $target = TicketType::query()->findOrFail($targetId);

            $upgrade = app(TicketUpgradeService::class)->create(
                $ticket,
                $target,
                $user
            );

            app(TicketUpgradePaymentService::class)
                ->createForTicketUpgrade($upgrade);

            $this->upgradeLinks[$ticketId] = route(
                'tickets.upgrades.show',
                ['token' => $upgrade->public_token]
            );

            Notification::make()
                ->title('Ticket upgrade created')
                ->body('Send the secure upgrade payment link to the customer.')
                ->success()
                ->send();
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('Ticket upgrade could not be created')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }
}
