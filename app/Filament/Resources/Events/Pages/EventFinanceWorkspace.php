<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Services\EventPayoutService;
use App\Services\EventPayoutReviewService;
use Filament\Actions\ActionGroup;
use App\Models\User;
use App\Services\EventFinanceWorkspaceSummary;
use App\Services\EventFinanceClearanceService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Locked;

class EventFinanceWorkspace extends Page
{
    protected static string $resource = EventResource::class;

    protected string $view = 'filament.resources.events.pages.event-finance-workspace';

    #[Locked]
    public int $eventId;

    public static function canViewFinance(Event $event, ?User $user): bool
    {
        return $user instanceof User && ! $user->is_gate_staff
            && ($user->isSuperAdmin() || $user->canManageOrganization($event->organization_id))
            && EventWorkspace::authorizedEvents($user)->whereKey($event->id)->exists();
    }

    public function mount(int|string $record): void
    {
        abort_unless(ctype_digit((string) $record), 404);
        $this->eventId = (int) $record;
        $this->event();
    }

    public function event(): Event
    {
        $event = Event::query()->findOrFail($this->eventId);
        abort_unless(static::canViewFinance($event, auth()->user()), 403);

        return $event;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Finance — '.$this->event()->name;
    }

    public function summary(): array
    {
        return app(EventFinanceWorkspaceSummary::class)->summarize($this->event()->id);
    }

    private function clearanceAdmin(): User
    {
        $this->event();
        $user = auth()->user();
        abort_unless($user instanceof User && ! $user->is_gate_staff && $user->isSuperAdmin(), 403);

        return $user;
    }

    private function pendingEntryOptions(string $operation): array
    {
        $user = $this->clearanceAdmin();
        $query = DB::table('event_finance_clearance_entries')
            ->where('event_id', $this->eventId)
            ->where('currency', 'TZS')
            ->whereNull('voided_at')
            ->whereNull('independently_verified_at')
            ->whereNull('self_attested_at');

        if ($operation === 'verify') {
            $query->where('recorded_by', '!=', $user->id);
        } elseif ($operation === 'self') {
            $query->where('recorded_by', $user->id);
        } elseif ($operation === 'void') {
            $query->where('entry_type', 'settlement');
        }

        return $query->orderByDesc('id')->get(['id', 'entry_type', 'amount', 'external_reference'])
            ->mapWithKeys(fn ($entry) => [
                $entry->id => '#'.$entry->id.' | '.$entry->entry_type.' | TZS '.$entry->amount.' | '.$entry->external_reference,
            ])->all();
    }

    private function scopedClearanceId(array $data): int
    {
        $this->clearanceAdmin();
        $id = $data['entry_id'] ?? null;
        abort_unless(is_scalar($id) && ctype_digit((string) $id), 404);
        abort_unless(DB::table('event_finance_clearance_entries')
            ->where('event_id', $this->eventId)->where('currency', 'TZS')->where('id', $id)->exists(), 404);

        return (int) $id;
    }

    protected function getHeaderActions(): array
    {
        $isAdmin = fn (): bool => auth()->user() instanceof User
            && ! auth()->user()->is_gate_staff && auth()->user()->isSuperAdmin();

        $actions = [
            Action::make('record_clearance')
                ->label('Record clearance')
                ->icon('heroicon-o-plus')
                ->authorize($isAdmin)
                ->visible($isAdmin)
                ->modalDescription('Record actual external settlement or reversal evidence in TZS. This does not verify funds or initiate any transfer.')
                ->schema([
                    Select::make('entry_type')->options(['settlement' => 'Settlement', 'refund' => 'Refund', 'chargeback' => 'Chargeback'])->required(),
                    TextInput::make('amount')->label('Amount (TZS)')->numeric()->minValue(0.01)->required(),
                    TextInput::make('external_reference')->label('External evidence reference')->required()->maxLength(255),
                    Textarea::make('note')->label('Evidence note')->required()->minLength(12)->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    $admin = $this->clearanceAdmin();
                    app(EventFinanceClearanceService::class)->record($this->event(), $admin, $data['entry_type'], (string) $data['amount'], $data['external_reference'], $data['note']);
                    Notification::make()->title('Clearance evidence recorded')->success()->send();
                }),
        ];

        foreach ([
            ['verify', 'Independently verify', 12, 'Review documentary evidence recorded by another administrator.'],
            ['self', 'Self-attest', 40, 'Only permitted for the original recorder when exactly one super administrator exists. This is not independent verification.'],
            ['void', 'Void incorrect entry', 20, 'Preserves the original record. Reviewed settlements and reversals cannot be voided here.'],
        ] as [$operation, $label, $min, $description]) {
            $actions[] = Action::make('clearance_'.$operation)
                ->label($label)
                ->authorize($isAdmin)
                ->visible(fn (): bool => $isAdmin() && ($operation !== 'self' || app(EventFinanceClearanceService::class)->singleSuperAdminIs(auth()->user())))
                ->modalDescription($description)
                ->schema([
                    Select::make('entry_id')->label('Clearance entry')->options(fn (): array => $this->pendingEntryOptions($operation))->searchable()->required(),
                    Textarea::make('note')->label($operation === 'void' ? 'Correction reason' : 'Evidence review note')->required()->minLength($min)->maxLength(2000),
                ])
                ->action(function (array $data) use ($operation): void {
                    $admin = $this->clearanceAdmin();
                    $id = $this->scopedClearanceId($data);
                    $service = app(EventFinanceClearanceService::class);
                    match ($operation) {
                        'verify' => $service->independentlyVerify($id, $admin, $data['note']),
                        'self' => $service->selfAttest($id, $admin, $data['note']),
                        'void' => $service->voidEntry($id, $admin, $data['note']),
                    };
                    Notification::make()->title('Clearance record updated')->success()->send();
                });
        }

        $actions[] = ActionGroup::make($this->payoutActions($isAdmin))->label('Payout management')->icon('heroicon-o-banknotes')->button();

        return $actions;
    }

    private function payoutActor(): User
    {
        $this->event();
        $user = auth()->user();
        abort_unless($user instanceof User && ! $user->is_gate_staff, 403);

        return $user;
    }

    private function payoutOptions(string $operation): array
    {
        $user = $operation === 'receipt' ? $this->payoutActor() : $this->clearanceAdmin();
        $query = EventPayoutRequest::query()->where('event_id', $this->eventId);
        if ($operation === 'receipt') {
            $query->where('requested_by', $user->id)->where('status', EventPayoutRequest::PAID)->whereNull('received_at');
        } else {
            $query->where('status', EventPayoutRequest::REQUESTED);
            if ($operation === 'approve') {
                $query->where('currency', 'TZS')->where('requested_by', '!=', $user->id);
            }
        }

        return $query->orderByDesc('id')->get(['id', 'reference', 'currency', 'amount'])
            ->mapWithKeys(fn ($request) => [$request->id => $request->reference.' | '.$request->currency.' '.$request->amount])->all();
    }

    private function scopedPayout(array $data): EventPayoutRequest
    {
        $this->payoutActor();
        $id = $data['payout_id'] ?? null;
        abort_unless(is_scalar($id) && ctype_digit((string) $id), 404);

        return EventPayoutRequest::query()->where('event_id', $this->eventId)->whereKey((int) $id)->firstOrFail();
    }

    private function payoutActions(callable $isAdmin): array
    {
        $enabled = fn (): bool => (bool) config('finance.payout_requests_enabled', false);
        $actions = [
            Action::make('request_payout')
                ->label('Request payout')
                ->visible($enabled)
                ->authorize($enabled)
                ->modalDescription('Submit a TZS payout request against recorded ticket earnings. This does not authorize or initiate a transfer.')
                ->schema([
                    TextInput::make('amount')->label('Amount (TZS)')->numeric()->minValue(0.01)->required(),
                    Select::make('destination_type')->options(['bank' => 'Bank', 'mobile_money' => 'Mobile money'])->required(),
                    Textarea::make('destination_details')->label('Payment destination details')->required()->maxLength(500),
                ])
                ->action(function (array $data): void {
                    $user = $this->payoutActor();
                    app(EventPayoutService::class)->request($this->event(), $user, (float) $data['amount'], $data['destination_type'], $data['destination_details']);
                    Notification::make()->title('Payout request submitted')->success()->send();
                }),
        ];

        foreach ([true, false] as $approve) {
            $operation = $approve ? 'approve' : 'reject';
            $allowed = fn (): bool => $isAdmin() && (! $approve || $enabled());
            $actions[] = Action::make($operation.'_payout')
                ->label($approve ? 'Conditionally approve payout' : 'Reject payout')
                ->visible($allowed)->authorize($allowed)
                ->modalDescription($approve ? 'Conditional approval only. Confirm settlement and all transfer requirements separately.' : 'Reject the pending request and release its reservation.')
                ->schema([
                    Select::make('payout_id')->label('Payout request')->options(fn (): array => $this->payoutOptions($operation))->searchable()->required(),
                    Textarea::make('note')->label($approve ? 'Review note' : 'Rejection reason')->required(! $approve)->minLength($approve ? 0 : 10)->maxLength(500),
                ])
                ->action(function (array $data) use ($approve): void {
                    $admin = $this->clearanceAdmin();
                    app(EventPayoutReviewService::class)->review($this->scopedPayout($data), $admin, $approve, $data['note'] ?? null);
                    Notification::make()->title($approve ? 'Payout conditionally approved' : 'Payout rejected')->success()->send();
                });
        }

        $actions[] = Action::make('confirm_payout_receipt')
            ->label('Confirm receipt')
            ->modalDescription('Confirm only funds you have actually received.')
            ->schema([
                Select::make('payout_id')->label('Paid payout request')->options(fn (): array => $this->payoutOptions('receipt'))->searchable()->required(),
            ])
            ->action(function (array $data): void {
                $user = $this->payoutActor();
                app(EventPayoutReviewService::class)->confirmReceipt($this->scopedPayout($data), $user);
                Notification::make()->title('Receipt confirmed')->success()->send();
            });

        return $actions;
    }
}
