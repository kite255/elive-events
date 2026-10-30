<?php

namespace App\Filament\Resources\Payouts;

use App\Filament\Resources\Payouts\Pages\ListPayouts;
use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\User;
use App\Services\EventPayoutReviewService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class PayoutResource extends Resource
{
    protected static ?string $model = EventPayoutRequest::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;
    protected static string|UnitEnum|null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Payout Requests';
    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (
            $user->isSuperAdmin()
            || $user->ticketOrganizerOrganizations()->exists()
            || $user->managedOrganizations()->exists()
        );
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery()->with(['event', 'requester']);

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereHas('event', function (Builder $events) use ($user): void {
            $events->where(function (Builder $scope) use ($user): void {
                $scope->whereIn('organization_id', $user->managedOrganizations()->select('organizations.id'))
                    ->orWhereIn('events.id', $user->assignedTicketingEvents()->select('events.id'));
            });
        });
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof EventPayoutRequest
            && static::getEloquentQuery()->whereKey($record->getKey())->exists();
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            TextColumn::make('reference')->searchable()->copyable(),
            TextColumn::make('event.name')->label('Event')->searchable(),
            TextColumn::make('requester.name')->label('Requested By'),
            TextColumn::make('amount')->formatStateUsing(
                fn ($state, EventPayoutRequest $record): string =>
                    $record->currency.' '.number_format((float) $state, 2)
            ),
            TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                EventPayoutRequest::PAID => 'success',
                EventPayoutRequest::APPROVED => 'info',
                EventPayoutRequest::REJECTED => 'danger',
                default => 'warning',
            }),
            TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
            TextColumn::make('transfer_method')->label('Method')->placeholder('—'),
            TextColumn::make('transfer_date')->label('Transfer Date')->date()->placeholder('—'),
            TextColumn::make('paid_at')->label('Transferred At')->dateTime('d M Y H:i')->placeholder('Not transferred'),
            TextColumn::make('received_at')->label('Received At')->dateTime('d M Y H:i')->placeholder('Awaiting confirmation'),
        ])->recordActions([
            Action::make('viewProof')->label('Download Proof')->icon('heroicon-o-document-arrow-down')
                ->visible(fn (EventPayoutRequest $record): bool =>
                    auth()->user()?->isSuperAdmin()
                    && is_string($record->transfer_proof_path)
                    && Storage::disk('local')->exists($record->transfer_proof_path)
                )
                ->url(fn (EventPayoutRequest $record): string =>
                    route('admin.payouts.transfer-proof', ['payout' => $record->getKey()])
                )
                ->openUrlInNewTab(),
            Action::make('history')->label('History')->icon('heroicon-o-clock')
                ->modalHeading('Payout audit history')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn (EventPayoutRequest $record) => view('filament.payouts.audit-history', [
                    'entries' => DB::table('event_payout_audits')
                        ->leftJoin('users', 'users.id', '=', 'event_payout_audits.actor_id')
                        ->where('event_payout_request_id', $record->getKey())
                        ->orderBy('event_payout_audits.id')
                        ->get(['event_payout_audits.action', 'event_payout_audits.from_status',
                            'event_payout_audits.to_status', 'event_payout_audits.note',
                            'event_payout_audits.created_at', 'users.name as actor_name']),
                ])),
            Action::make('approve')->label('Approve')->icon('heroicon-o-check')->color('success')
                ->visible(fn (EventPayoutRequest $record): bool => auth()->user()?->isSuperAdmin() && $record->status === EventPayoutRequest::REQUESTED)
                ->requiresConfirmation()
                ->action(function (EventPayoutRequest $record): void {
                    app(EventPayoutReviewService::class)->review($record, auth()->user(), true);
                    Notification::make()->title('Payout request approved')->body('The organizer has been notified of the approval.')->success()->duration(8000)->send();
                }),
            Action::make('reject')->label('Reject')->color('danger')
                ->visible(fn (EventPayoutRequest $record): bool => auth()->user()?->isSuperAdmin() && $record->status === EventPayoutRequest::REQUESTED)
                ->schema([Textarea::make('reason')->label('Reason')->required()->maxLength(500)])
                ->action(function (EventPayoutRequest $record, array $data): void {
                    app(EventPayoutReviewService::class)->review($record, auth()->user(), false, $data['reason']);
                    Notification::make()->title('Payout request rejected')->body('The organizer has been notified of the rejection.')->warning()->duration(8000)->send();
                }),
            Action::make('recordTransfer')->label('Record Transfer')->color('info')
                ->visible(fn (EventPayoutRequest $record): bool => auth()->user()?->isSuperAdmin() && $record->status === EventPayoutRequest::APPROVED)
                ->schema([
                    Select::make('method')->label('Payment method')->options(['mpesa' => 'M-Pesa', 'airtel' => 'Airtel Money', 'mixx' => 'Mixx by Yas', 'bank' => 'Bank transfer', 'other' => 'Other'])->required(),
                    TextInput::make('reference')->label('Verified transfer reference')->required()->maxLength(255),
                    TextInput::make('amount')->label('Amount transferred (TZS)')->numeric()->required()->default(fn (EventPayoutRequest $record) => $record->amount),
                    DatePicker::make('transfer_date')->label('Transfer date')->required()->maxDate(now())->default(now()),
                    FileUpload::make('proof')->label('Proof of successful transfer')->disk('local')->directory('payout-proofs')->visibility('private')->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->maxSize(5120)->required(),
                    Textarea::make('note')->label('Transfer notes')->maxLength(1000),
                    Checkbox::make('transfer_verified')
                        ->label('I have independently verified that this transfer was completed')
                        ->accepted()->required(),
                ])
                ->requiresConfirmation()
                ->action(function (EventPayoutRequest $record, array $data): void {
                    if (! filter_var($data['transfer_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        throw ValidationException::withMessages(['transfer_verified' => 'Confirm the transfer before recording it.']);
                    }
                    app(EventPayoutReviewService::class)->recordCompletedTransfer(
                        $record, auth()->user(), $data['reference'], $data['method'],
                        (string) $data['transfer_date'], (string) $data['amount'],
                        (string) $data['proof'], $data['note'] ?? null
                    );
                    Notification::make()->title('Confirmed transfer recorded')->body('The payout has been marked as paid and the organizer notified.')->success()->duration(8000)->send();
                }),
            Action::make('confirmReceipt')->label('Confirm Money Received')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn (EventPayoutRequest $record): bool => (int) $record->requested_by === (int) auth()->id() && $record->status === EventPayoutRequest::PAID && $record->received_at === null)
                ->requiresConfirmation()
                ->modalHeading('Confirm payout received')
                ->modalDescription('Check the transfer details against your bank or mobile money statement before confirming.')
                ->modalContent(fn (EventPayoutRequest $record) => view('filament.payouts.receipt-details', [
                    'payout' => $record,
                ]))
                ->schema([
                    Checkbox::make('full_amount_received')
                        ->label('I have checked my account and confirm that the full amount shown above has been received.')
                        ->accepted()
                        ->required(),
                ])
                ->action(function (EventPayoutRequest $record, array $data): void {
                    if (! filter_var($data['full_amount_received'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        throw ValidationException::withMessages([
                            'full_amount_received' => 'You must confirm receipt of the full amount.',
                        ]);
                    }

                    app(EventPayoutReviewService::class)->confirmReceipt($record, auth()->user());
                    Notification::make()->title('Receipt confirmed')->success()->duration(8000)->send();
                }),
        ])->filters([
            SelectFilter::make('status')->options([
                EventPayoutRequest::REQUESTED => 'Requested',
                EventPayoutRequest::APPROVED => 'Approved',
                EventPayoutRequest::REJECTED => 'Rejected',
                EventPayoutRequest::PAID => 'Paid',
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPayouts::route('/')];
    }
}
