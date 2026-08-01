<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Filament\Resources\Attendees\AttendeeResource;
use App\Services\BadgeGenerationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Support\Js;
use Throwable;

class AttendeesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendees';

    protected static ?string $title = 'Event Attendees';

    protected static ?string $modelLabel = 'Attendee';

    protected static ?string $pluralModelLabel = 'Attendees';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('full_name')
                    ->label('Full Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('phone')
                    ->label('Phone')
                    ->tel()
                    ->maxLength(50),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(255),

                TextInput::make('organization_name')
                    ->label('Organization')
                    ->maxLength(255),

                TextInput::make('position')
                    ->label('Position / Title')
                    ->maxLength(255),

                Select::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                Select::make('badge_type_id')
                    ->label('Badge Type')
                    ->relationship('badgeType', 'name')
                    ->searchable()
                    ->preload(),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending_approval' => 'Pending Approval',
                        'waitlisted' => 'Waitlisted',
                        'registered' => 'Registered',
                        'confirmed' => 'Confirmed',
                        'checked_in' => 'Checked In',
                        'cancelled' => 'Cancelled',
                        'rejected' => 'Rejected',
                    ])
                    ->default('registered')
                    ->required(),

                Select::make('registration_source')
                    ->label('Source')
                    ->options([
                        'manual' => 'Manual',
                        'public' => 'Public Form',
                        'import' => 'Excel Import',
                        'onsite' => 'Onsite',
                    ])
                    ->default('manual')
                    ->required(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Attendee')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('badgeType.name')
                    ->label('Badge Type')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('badge_number')
                    ->label('Badge No.')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('selected_days')
                    ->label('Days')
                    ->getStateUsing(
                        fn ($record): string => $record
                            ->eventDays
                            ->pluck('name')
                            ->filter()
                            ->implode(', ')
                    )
                    ->placeholder('No days selected')
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('merchandise_total')
                    ->label('Order Total')
                    ->getStateUsing(function ($record): string {
                        $selections = $record->merchandiseSelections;

                        if ($selections->isEmpty()) {
                            return 'No order';
                        }

                        $currency = $selections
                            ->pluck('currency')
                            ->filter()
                            ->first() ?: 'TZS';

                        $total = (float) $selections
                            ->sum(
                                fn ($selection) =>
                                    (float) ($selection->total_price ?? 0)
                            );

                        return $total > 0
                            ? $currency . ' ' . number_format($total, 2)
                            : 'Free';
                    })
                    ->badge()
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pending_approval' => 'warning',
                        'waitlisted' => 'warning',
                        'registered' => 'gray',
                        'confirmed' => 'success',
                        'checked_in' => 'info',
                        'cancelled', 'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending_approval' => 'Pending Approval',
                        'waitlisted' => 'Waitlisted',
                        'registered' => 'Registered',
                        'confirmed' => 'Confirmed',
                        'checked_in' => 'Checked In',
                        'cancelled' => 'Cancelled',
                        'rejected' => 'Rejected',
                        default => ucfirst((string) $state),
                    })
                    ->sortable(),

                TextColumn::make('registration_source')
                    ->label('Source')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'public' => 'success',
                        'import' => 'primary',
                        'onsite' => 'warning',
                        'manual' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'manual' => 'Manual',
                        'public' => 'Public Form',
                        'import' => 'Excel Import',
                        'onsite' => 'Onsite',
                        default => ucfirst((string) $state),
                    })
                    ->sortable(),

                TextColumn::make('badge_status')
                    ->label('Badge Status')
                    ->badge()
                    ->default('pending')
                    ->color(fn (?string $state): string => match ($state) {
                        'generating' => 'warning',
                        'generated' => 'success',
                        'failed' => 'danger',
                        'printed' => 'info',
                        default => 'gray',
                    })
                    ->visible(fn (): bool => DbSchema::hasColumn('attendees', 'badge_status'))
                    ->sortable(),

                IconColumn::make('badge_path')
                    ->label('Badge')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->badge_path)),

                IconColumn::make('public_token')
                    ->label('Public Link')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->public_token))
                    ->visible(fn (): bool => DbSchema::hasColumn('attendees', 'public_token')),

                IconColumn::make('checked_in_at')
                    ->label('Checked In')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->checked_in_at)),

                TextColumn::make('organization_name')
                    ->label('Organization')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('position')
                    ->label('Position')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('registered_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('checked_in_at')
                    ->label('Checked In At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending_approval' => 'Pending Approval',
                        'waitlisted' => 'Waitlisted',
                        'registered' => 'Registered',
                        'confirmed' => 'Confirmed',
                        'checked_in' => 'Checked In',
                        'cancelled' => 'Cancelled',
                        'rejected' => 'Rejected',
                    ]),

                SelectFilter::make('registration_source')
                    ->label('Registration Source')
                    ->options([
                        'manual' => 'Manual',
                        'public' => 'Public Form',
                        'import' => 'Excel Import',
                        'onsite' => 'Onsite',
                    ]),

                SelectFilter::make('badge_status')
                    ->label('Badge Status')
                    ->visible(fn (): bool => DbSchema::hasColumn('attendees', 'badge_status'))
                    ->options([
                        'pending' => 'Pending',
                        'generating' => 'Generating',
                        'generated' => 'Generated',
                        'failed' => 'Failed',
                        'printed' => 'Printed',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Attendee')
                    ->mutateDataUsing(function (array $data): array {
                        $data['event_id'] = $this->getOwnerRecord()->id;

                        return $data;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('view_registration_details')
                        ->label('View Registration Details')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->modalHeading(
                            fn ($record): string =>
                                'Registration Details — ' . $record->full_name
                        )
                        ->modalWidth('7xl')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close')
                        ->modalContent(function ($record) {
                            $record->loadMissing([
                                'eventDays',
                                'category',
                                'badgeType',
                                'registrationAnswers.registrationField',
                                'merchandiseSelections.merchandise',
                                'merchandiseSelections.variant',
                            ]);

                            return view(
                                'filament.resources.events.relation-managers.attendee-registration-details',
                                [
                                    'attendee' => $record,
                                ]
                            );
                        }),

                    Action::make('open_public_page')
                        ->label('Open Public Page')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('success')
                        ->visible(fn ($record): bool => filled($record->public_token))
                        ->url(fn ($record): string => $record->publicUrl())
                        ->openUrlInNewTab(),

                    Action::make('copy_public_link')
                        ->label('Copy Public Link')
                        ->icon('heroicon-o-clipboard-document')
                        ->color('gray')
                        ->visible(fn ($record): bool => filled($record->public_token))
                        ->extraAttributes(fn ($record) => [
                            'x-on:click' => 'navigator.clipboard.writeText(' . Js::from($record->publicUrl()) . ')',
                        ])
                        ->action(function ($record): void {
                            Notification::make()
                                ->title('Public link copied')
                                ->body($record->publicUrl())
                                ->success()
                                ->send();
                        }),

                    Action::make('copy_badge_link')
                        ->label('Copy Badge Link')
                        ->icon('heroicon-o-link')
                        ->color('primary')
                        ->visible(fn ($record): bool => filled($record->public_token))
                        ->extraAttributes(fn ($record) => [
                            'x-on:click' => 'navigator.clipboard.writeText(' . Js::from($record->publicUrl()) . ')',
                        ])
                        ->action(function ($record): void {
                            Notification::make()
                                ->title('Badge/status link copied')
                                ->body($record->publicUrl())
                                ->success()
                                ->send();
                        }),

                    Action::make('approve_registration')
                        ->label('Approve Registration')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn ($record): bool => $record->status === 'pending_approval')
                        ->requiresConfirmation()
                        ->modalHeading('Approve registration?')
                        ->modalDescription('This attendee will be approved and their badge will be generated.')
                        ->action(function ($record): void {
                            $record->forceFill([
                                'status' => 'registered',
                            ])->save();

                            try {
                                app(BadgeGenerationService::class)->generateForAttendee(
                                    $record->fresh(['event', 'category', 'badgeType', 'qrToken'])
                                );

                                Notification::make()
                                    ->title('Registration approved')
                                    ->body('The attendee was approved and the badge was generated.')
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                Notification::make()
                                    ->title('Registration approved, but badge generation failed')
                                    ->body($e->getMessage())
                                    ->warning()
                                    ->send();
                            }
                        }),

                    Action::make('move_to_registered')
                        ->label('Move to Registered')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('success')
                        ->visible(fn ($record): bool => $record->status === 'waitlisted')
                        ->requiresConfirmation()
                        ->modalHeading('Move attendee from waitlist?')
                        ->modalDescription('This attendee will be moved from waitlist to registered. A badge will be generated.')
                        ->action(function ($record): void {
                            $record->forceFill([
                                'status' => 'registered',
                            ])->save();

                            try {
                                app(BadgeGenerationService::class)->generateForAttendee(
                                    $record->fresh(['event', 'category', 'badgeType', 'qrToken'])
                                );

                                Notification::make()
                                    ->title('Attendee moved to registered')
                                    ->body('The attendee was registered and the badge was generated.')
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                Notification::make()
                                    ->title('Attendee registered, but badge generation failed')
                                    ->body($e->getMessage())
                                    ->warning()
                                    ->send();
                            }
                        }),

                    Action::make('reject_registration')
                        ->label('Reject Registration')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn ($record): bool => in_array($record->status, [
                            'pending_approval',
                            'registered',
                            'waitlisted',
                        ], true))
                        ->requiresConfirmation()
                        ->modalHeading('Reject registration?')
                        ->modalDescription('This attendee will be marked as rejected.')
                        ->action(function ($record): void {
                            $record->forceFill([
                                'status' => 'rejected',
                            ])->save();

                            Notification::make()
                                ->title('Registration rejected')
                                ->body($record->full_name . ' has been marked as rejected.')
                                ->success()
                                ->send();
                        }),

                    Action::make('generate_badge')
                        ->label(fn ($record): string => filled($record->badge_path) ? 'Regenerate Badge' : 'Generate Badge')
                        ->icon('heroicon-o-identification')
                        ->color(fn ($record): string => filled($record->badge_path) ? 'warning' : 'success')
                        ->visible(fn ($record): bool => in_array($record->status, [
                            'registered',
                            'confirmed',
                            'checked_in',
                            'approved',
                        ], true))
                        ->requiresConfirmation(fn ($record): bool => filled($record->badge_path))
                        ->modalHeading('Generate Badge')
                        ->modalDescription('This will generate a badge for this attendee. If a badge already exists, it will be replaced.')
                        ->action(function ($record): void {
                            try {
                                app(BadgeGenerationService::class)->generateForAttendee(
                                    $record->fresh(['event', 'category', 'badgeType', 'qrToken'])
                                );

                                Notification::make()
                                    ->title('Badge generated successfully')
                                    ->body('Badge has been generated for ' . $record->full_name . '.')
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                Notification::make()
                                    ->title('Badge generation failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('view_badge')
                        ->label('View Badge')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->visible(fn ($record): bool => filled($record->badge_path))
                        ->url(fn ($record): string => asset('storage/' . $record->badge_path))
                        ->openUrlInNewTab(),

                    Action::make('download_badge')
                        ->label('Download Badge')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->visible(fn ($record): bool => filled($record->badge_path))
                        ->url(fn ($record): string => asset('storage/' . $record->badge_path))
                        ->openUrlInNewTab(),

                    Action::make('mark_badge_printed')
                        ->label('Mark Printed')
                        ->icon('heroicon-o-printer')
                        ->color('gray')
                        ->visible(fn ($record): bool => filled($record->badge_path))
                        ->action(function ($record): void {
                            self::markBadgePrinted($record);

                            Notification::make()
                                ->title('Badge marked as printed')
                                ->success()
                                ->send();
                        }),

                    Action::make('view_qr_code')
                        ->label('View QR')
                        ->icon('heroicon-o-qr-code')
                        ->color('primary')
                        ->url(fn ($record): string => AttendeeResource::getUrl('qr-code', [
                            'record' => $record,
                        ])),

                    EditAction::make(),

                    DeleteAction::make(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button()
                    ->color('gray'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_registrations')
                        ->label('Approve Registrations')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Approve selected registrations?')
                        ->modalDescription('Selected pending attendees will be approved. Badges will be generated where possible.')
                        ->action(function (Collection $records): void {
                            $approved = 0;
                            $badgesGenerated = 0;
                            $badgeFailed = 0;

                            foreach ($records as $record) {
                                if ($record->status !== 'pending_approval') {
                                    continue;
                                }

                                $record->forceFill([
                                    'status' => 'registered',
                                ])->save();

                                $approved++;

                                try {
                                    app(BadgeGenerationService::class)->generateForAttendee(
                                        $record->fresh(['event', 'category', 'badgeType', 'qrToken'])
                                    );

                                    $badgesGenerated++;
                                } catch (Throwable $e) {
                                    $badgeFailed++;
                                    self::markBadgeFailed($record);
                                }
                            }

                            self::sendBulkResultNotification(
                                title: 'Registrations approved',
                                body: "Approved: {$approved}. Badges generated: {$badgesGenerated}. Badge failed: {$badgeFailed}.",
                                failed: $badgeFailed
                            );
                        }),

                    BulkAction::make('move_waitlist_to_registered')
                        ->label('Move Waitlist to Registered')
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Move selected waitlisted attendees?')
                        ->modalDescription('Selected waitlisted attendees will be moved to registered. Badges will be generated where possible.')
                        ->action(function (Collection $records): void {
                            $moved = 0;
                            $badgesGenerated = 0;
                            $badgeFailed = 0;

                            foreach ($records as $record) {
                                if ($record->status !== 'waitlisted') {
                                    continue;
                                }

                                $record->forceFill([
                                    'status' => 'registered',
                                ])->save();

                                $moved++;

                                try {
                                    app(BadgeGenerationService::class)->generateForAttendee(
                                        $record->fresh(['event', 'category', 'badgeType', 'qrToken'])
                                    );

                                    $badgesGenerated++;
                                } catch (Throwable $e) {
                                    $badgeFailed++;
                                    self::markBadgeFailed($record);
                                }
                            }

                            self::sendBulkResultNotification(
                                title: 'Waitlisted attendees moved',
                                body: "Moved: {$moved}. Badges generated: {$badgesGenerated}. Badge failed: {$badgeFailed}.",
                                failed: $badgeFailed
                            );
                        }),

                    BulkAction::make('reject_registrations')
                        ->label('Reject Registrations')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Reject selected registrations?')
                        ->modalDescription('Selected attendees will be marked as rejected.')
                        ->action(function (Collection $records): void {
                            $rejected = 0;

                            foreach ($records as $record) {
                                if (! in_array($record->status, [
                                    'pending_approval',
                                    'registered',
                                    'waitlisted',
                                ], true)) {
                                    continue;
                                }

                                $record->forceFill([
                                    'status' => 'rejected',
                                ])->save();

                                $rejected++;
                            }

                            Notification::make()
                                ->title('Registrations rejected')
                                ->body($rejected . ' attendee(s) rejected.')
                                ->success()
                                ->send();
                        }),

                    BulkAction::make('generate_badges')
                        ->label('Generate / Regenerate Badges')
                        ->icon('heroicon-o-identification')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Generate Badges')
                        ->modalDescription('This will generate or regenerate badges for all selected registered/confirmed attendees.')
                        ->action(function (Collection $records): void {
                            $generated = 0;
                            $failed = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                if (! in_array($record->status, [
                                    'registered',
                                    'confirmed',
                                    'checked_in',
                                    'approved',
                                ], true)) {
                                    $skipped++;
                                    continue;
                                }

                                try {
                                    app(BadgeGenerationService::class)->generateForAttendee(
                                        $record->fresh(['event', 'category', 'badgeType', 'qrToken'])
                                    );

                                    $generated++;
                                } catch (Throwable $e) {
                                    $failed++;
                                    self::markBadgeFailed($record);
                                }
                            }

                            self::sendBulkResultNotification(
                                title: 'Badge generation completed',
                                body: "Generated: {$generated}. Failed: {$failed}. Skipped: {$skipped}.",
                                failed: $failed
                            );
                        }),

                    BulkAction::make('mark_badges_printed')
                        ->label('Mark Badges Printed')
                        ->icon('heroicon-o-printer')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $updated = 0;

                            foreach ($records as $record) {
                                if (self::markBadgePrinted($record)) {
                                    $updated++;
                                }
                            }

                            Notification::make()
                                ->title('Badges marked as printed')
                                ->body($updated . ' attendee badge(s) updated.')
                                ->success()
                                ->send();
                        }),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(
                fn ($query) => $query->with([
                    'eventDays',
                    'merchandiseSelections',
                ])
            )
            ->defaultSort('created_at', 'desc');
    }

    protected static function markBadgePrinted($record): bool
    {
        $data = [];

        if (DbSchema::hasColumn('attendees', 'badge_status')) {
            $data['badge_status'] = 'printed';
        }

        if (DbSchema::hasColumn('attendees', 'badge_printed_at')) {
            $data['badge_printed_at'] = now();
        }

        if ($data === []) {
            return false;
        }

        $record->forceFill($data)->save();

        return true;
    }

    protected static function markBadgeFailed($record): void
    {
        if (! DbSchema::hasColumn('attendees', 'badge_status')) {
            return;
        }

        $record->fresh()->forceFill([
            'badge_status' => 'failed',
        ])->save();
    }

    protected static function sendBulkResultNotification(string $title, string $body, int $failed = 0): void
    {
        $notification = Notification::make()
            ->title($title)
            ->body($body);

        if ($failed > 0) {
            $notification->warning();
        } else {
            $notification->success();
        }

        $notification->send();
    }
}