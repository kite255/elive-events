<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Models\Event;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
                        fn (?string $state): string =>
                            static::roleOptions()[$state]
                                ?? static::formatValue($state)
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