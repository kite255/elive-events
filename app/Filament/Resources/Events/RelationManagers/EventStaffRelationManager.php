<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Models\Event;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventStaffRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Event Staff';

    protected static ?string $recordTitleAttribute = 'name';

    /*
    |--------------------------------------------------------------------------
    | Form
    |--------------------------------------------------------------------------
    */

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('role')
                    ->label('Event Role')
                    ->options(static::roleOptions())
                    ->required()
                    ->native(false),

                Select::make('status')
                    ->label('Assignment Status')
                    ->options(static::statusOptions())
                    ->default(Event::STAFF_STATUS_ACTIVE)
                    ->required()
                    ->native(false),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Staff Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email Address')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('pivot.role')
                    ->label('Event Role')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state, User $record): string =>
                            $record->is_gate_staff && $state === User::ORGANIZATION_ROLE_CHECK_IN_OFFICER
                                ? 'Gate Staff'
                                : (static::roleOptions()[$state]
                                ?? static::formatValue($state))
                    ),

                TextColumn::make('pivot.status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            static::statusOptions()[$state]
                                ?? static::formatValue($state)
                    ),

                IconColumn::make('organization_access')
                    ->label('Organization Access')
                    ->boolean()
                    ->getStateUsing(
                        function (User $record): bool {
                            $event = $this->event();

                            return $record
                                ->hasActiveOrganizationAccess(
                                    $event->organization_id
                                );
                        }
                    ),

                TextColumn::make('pivot.assigned_at')
                    ->label('Assigned At')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Not recorded'),

                TextColumn::make('pivot.last_accessed_at')
                    ->label('Last Access')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Never'),
            ])
            ->headerActions([
                Action::make('create_gate_staff')
                    ->label('Create Gate Staff')
                    ->modalHeading('Create Gate Staff Account')
                    ->modalDescription('This account can use the Ticket Scanner for the assigned event.')
                    ->modalSubmitActionLabel('Create Account')
                    ->authorize(fn (): bool => $this->canCreateGateStaff())
                    ->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (User::query()->whereRaw('LOWER(email) = ?', [Str::lower(trim($value))])->exists()) {
                                    $fail('An account already uses this email address.');
                                }
                            }),
                        TextInput::make('password')
                            ->password()
                            ->required()
                            ->minLength(12)
                            ->maxLength(72),
                        TextInput::make('password_confirmation')
                            ->label('Confirm password')
                            ->password()
                            ->required()
                            ->same('password')
                            ->dehydrated(false),
                    ])
                    ->action(function (array $data): void {
                        abort_unless($this->canCreateGateStaff(), 403);

                        $event = $this->event();
                        $data['email'] = Str::lower(trim($data['email']));

                        DB::transaction(function () use ($data, $event): void {
                            $user = new User($data);
                            $user->forceFill(['is_gate_staff' => true])->save();

                            $user->organizations()->attach($event->organization_id, [
                                'role' => User::ORGANIZATION_ROLE_MEMBER,
                                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                            ]);

                            $event->assignUser($user, User::ORGANIZATION_ROLE_CHECK_IN_OFFICER);
                        });

                        Notification::make()
                            ->title('Gate staff account created')
                            ->success()
                            ->actions([
                                Action::make('gate_sign_in')
                                    ->label('Gate Staff sign-in')
                                    ->url(route('filament.gate.auth.login'), shouldOpenInNewTab: true),
                            ])
                            ->send();
                    }),
                AttachAction::make()
                    ->label('Assign Staff')
                    ->modalHeading('Assign Staff to Event')
                    ->modalSubmitActionLabel('Assign Staff')
                    ->recordSelectSearchColumns([
                        'name',
                        'email',
                    ])
                    ->recordSelectOptionsQuery(
                        function (Builder $query): Builder {
                            $event = $this->event();

                            return $query
                                ->whereHas(
                                    'organizations',
                                    function (
                                        Builder $organizationQuery
                                    ) use ($event): void {
                                        $organizationQuery
                                            ->where(
                                                'organizations.id',
                                                $event->organization_id
                                            )
                                            ->where(
                                                'organization_user.status',
                                                User::ORGANIZATION_STATUS_ACTIVE
                                            );
                                    }
                                )
                                ->whereDoesntHave(
                                    'assignedEvents',
                                    function (
                                        Builder $eventQuery
                                    ) use ($event): void {
                                        $eventQuery->whereKey(
                                            $event->getKey()
                                        );
                                    }
                                )
                                ->orderBy('name');
                        }
                    )
                    ->schema(
                        fn (AttachAction $action): array => [
                            $action->getRecordSelect(),

                            Select::make('role')
                                ->label('Event Role')
                                ->options(static::roleOptions())
                                ->required()
                                ->native(false),

                            Select::make('status')
                                ->label('Assignment Status')
                                ->options(static::statusOptions())
                                ->default(
                                    Event::STAFF_STATUS_ACTIVE
                                )
                                ->required()
                                ->native(false),
                        ]
                    )
                    ->mutateDataUsing(
                        function (array $data): array {
                            $data['assigned_at'] = now();

                            return $data;
                        }
                    )
                    ->successNotificationTitle(
                        'Staff assigned successfully'
                    )
                    ->visible(
                        fn (): bool => $this->canManageStaff()
                    ),
            ])
            ->recordActions([
                Action::make('edit_assignment')
                    ->label('Edit Assignment')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('Edit Staff Assignment')
                    ->modalSubmitActionLabel('Save Changes')
                    ->schema([
                        Select::make('role')
                            ->label('Event Role')
                            ->options(static::roleOptions())
                            ->required()
                            ->native(false),

                        Select::make('status')
                            ->label('Assignment Status')
                            ->options(static::statusOptions())
                            ->required()
                            ->native(false),
                    ])
                    ->fillForm(
                        fn (User $record): array => [
                            'role' => $record->pivot?->role,
                            'status' => $record->pivot?->status,
                        ]
                    )
                    ->action(
                        function (
                            User $record,
                            array $data
                        ): void {
                            $this->event()
                                ->users()
                                ->updateExistingPivot(
                                    $record->getKey(),
                                    [
                                        'role' => $data['role'],
                                        'status' => $data['status'],
                                    ]
                                );

                            Notification::make()
                                ->title(
                                    'Staff assignment updated'
                                )
                                ->success()
                                ->send();
                        }
                    )
                    ->visible(
                        fn (): bool => $this->canManageStaff()
                    ),

                Action::make('suspend_assignment')
                    ->label('Suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Suspend Staff Assignment')
                    ->modalDescription(
                        'This user will no longer be able to access this event until the assignment is reactivated.'
                    )
                    ->action(
                        function (User $record): void {
                            $this->event()
                                ->suspendAssignedUser($record);

                            Notification::make()
                                ->title(
                                    'Staff assignment suspended'
                                )
                                ->success()
                                ->send();
                        }
                    )
                    ->visible(
                        fn (User $record): bool =>
                            $this->canManageStaff()
                            && $record->pivot?->status
                                === Event::STAFF_STATUS_ACTIVE
                    ),

                Action::make('activate_assignment')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(
                        function (User $record): void {
                            $this->event()
                                ->activateAssignedUser($record);

                            Notification::make()
                                ->title(
                                    'Staff assignment activated'
                                )
                                ->success()
                                ->send();
                        }
                    )
                    ->visible(
                        fn (User $record): bool =>
                            $this->canManageStaff()
                            && $record->pivot?->status
                                !== Event::STAFF_STATUS_ACTIVE
                    ),

                DetachAction::make()
                    ->label('Remove')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove Staff from Event')
                    ->modalDescription(
                        'This removes the user assignment from this event. It does not delete the user account.'
                    )
                    ->successNotificationTitle(
                        'Staff removed from event'
                    )
                    ->visible(
                        fn (): bool => $this->canManageStaff()
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label('Remove selected staff')
                        ->requiresConfirmation()
                        ->successNotificationTitle(
                            'Selected staff removed'
                        )
                        ->visible(
                            fn (): bool => $this->canManageStaff()
                        ),
                ]),
            ])
            ->defaultSort('name');
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public static function canViewForRecord(
        Model $ownerRecord,
        string $pageClass
    ): bool {
        if (! $ownerRecord instanceof Event) {
            return false;
        }

        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canManageOrganization(
            $ownerRecord->organization_id
        );
    }

    private function canCreateGateStaff(): bool
    {
        return $this->event()->organization_id !== null && $this->canManageStaff();
    }

    private function canManageStaff(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canManageOrganization(
            $this->event()->organization_id
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Internal helpers
    |--------------------------------------------------------------------------
    */

    private function event(): Event
    {
        /** @var Event $event */
        $event = $this->getOwnerRecord();

        return $event;
    }

    private static function roleOptions(): array
    {
        return [
            User::ORGANIZATION_ROLE_EVENT_MANAGER =>
                'Event Manager',

            User::ORGANIZATION_ROLE_REGISTRATION_OFFICER =>
                'Registration Officer',

            User::ORGANIZATION_ROLE_CHECK_IN_OFFICER =>
                'Check-in Officer',

            User::ORGANIZATION_ROLE_BADGE_OFFICER =>
                'Badge Officer',

            User::ORGANIZATION_ROLE_COMMUNICATION_OFFICER =>
                'Communication Officer',

            User::ORGANIZATION_ROLE_REPORT_VIEWER =>
                'Report Viewer',
        ];
    }

    private static function statusOptions(): array
    {
        return [
            Event::STAFF_STATUS_ACTIVE =>
                'Active',

            Event::STAFF_STATUS_INACTIVE =>
                'Inactive',

            Event::STAFF_STATUS_SUSPENDED =>
                'Suspended',
        ];
    }

    private static function formatValue(
        ?string $value
    ): string {
        if (blank($value)) {
            return 'Not Set';
        }

        return ucwords(
            str_replace('_', ' ', $value)
        );
    }
}