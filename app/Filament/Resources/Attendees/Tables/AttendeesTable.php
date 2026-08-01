<?php

namespace App\Filament\Resources\Attendees\Tables;

use App\Exports\AttendeesExport;
use App\Filament\Resources\Attendees\AttendeeResource;
use App\Services\BadgeGenerationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class AttendeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Attendee')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('badge_number')
                    ->label('Badge No.')
                    ->searchable()
                    ->copyable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('badge_status')
                    ->label('Badge')
                    ->badge()
                    ->default('pending')
                    ->color(fn (?string $state): string => match ($state) {
                        'generating' => 'warning',
                        'generated' => 'success',
                        'failed' => 'danger',
                        'printed' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'generating' => 'Generating',
                        'generated' => 'Generated',
                        'failed' => 'Failed',
                        'printed' => 'Printed',
                        default => 'Pending',
                    })
                    ->visible(fn (): bool => Schema::hasColumn('attendees', 'badge_status'))
                    ->sortable(),

                IconColumn::make('badge_path')
                    ->label('Badge File')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->badge_path)),

                TextColumn::make('event_sequence')
                    ->label('Seq.')
                    ->sortable()
                    ->visible(fn (): bool => Schema::hasColumn('attendees', 'event_sequence'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('event.event_code')
                    ->label('Event Code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->limit(28),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('badgeType.name')
                    ->label('Badge Type')
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

                IconColumn::make('public_token')
                    ->label('Public Link')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->public_token))
                    ->visible(fn (): bool => Schema::hasColumn('attendees', 'public_token')),

                IconColumn::make('checked_in_at')
                    ->label('Checked In')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->checked_in_at)),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('organization_name')
                    ->label('Organization')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('position')
                    ->label('Position')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('badge_generated_at')
                    ->label('Badge Generated')
                    ->dateTime()
                    ->sortable()
                    ->visible(fn (): bool => Schema::hasColumn('attendees', 'badge_generated_at'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('badge_printed_at')
                    ->label('Badge Printed')
                    ->dateTime()
                    ->sortable()
                    ->visible(fn (): bool => Schema::hasColumn('attendees', 'badge_printed_at'))
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
                SelectFilter::make('event')
                    ->relationship('event', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('badge_type')
                    ->relationship('badgeType', 'name')
                    ->searchable()
                    ->preload(),

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
                    ->visible(fn (): bool => Schema::hasColumn('attendees', 'badge_status'))
                    ->options([
                        'pending' => 'Pending',
                        'generating' => 'Generating',
                        'generated' => 'Generated',
                        'failed' => 'Failed',
                        'printed' => 'Printed',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
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

                    Action::make('view_badge')
                        ->label('View Badge')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->visible(fn ($record): bool => self::badgeExists($record))
                        ->url(fn ($record): string => self::badgeUrl($record))
                        ->openUrlInNewTab(),

                    Action::make('copy_badge_link')
                        ->label('Copy Badge Link')
                        ->icon('heroicon-o-link')
                        ->color('primary')
                        ->visible(fn ($record): bool => self::badgeExists($record))
                        ->extraAttributes(fn ($record) => [
                            'x-on:click' => 'navigator.clipboard.writeText(' . Js::from(self::badgeUrl($record)) . ')',
                        ])
                        ->action(function ($record): void {
                            Notification::make()
                                ->title('Badge link copied')
                                ->body(self::badgeUrl($record))
                                ->success()
                                ->send();
                        }),

                    Action::make('download_badge')
                        ->label('Download Badge')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->visible(fn ($record): bool => self::badgeExists($record))
                        ->action(function ($record) {
                            return response()->download(
                                Storage::disk('public')->path($record->badge_path),
                                str($record->full_name)->slug() . '-badge.svg'
                            );
                        }),

                    Action::make('generate_badge')
                        ->label(fn ($record): string => self::badgeExists($record) ? 'Regenerate Badge' : 'Generate Badge')
                        ->icon('heroicon-o-identification')
                        ->color(fn ($record): string => self::badgeExists($record) ? 'warning' : 'success')
                        ->visible(fn ($record): bool => in_array($record->status, [
                            'registered',
                            'confirmed',
                            'checked_in',
                            'approved',
                        ], true))
                        ->requiresConfirmation(fn ($record): bool => self::badgeExists($record))
                        ->modalHeading(fn ($record): string => self::badgeExists($record) ? 'Regenerate badge?' : 'Generate badge?')
                        ->modalDescription('This will generate a badge using the latest saved badge template design.')
                        ->modalSubmitActionLabel('Generate')
                        ->action(function ($record): void {
                            try {
                                self::generateBadgeForRecord($record);

                                Notification::make()
                                    ->title('Badge generated')
                                    ->body('Badge has been generated for ' . $record->full_name . '.')
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                self::markBadgeFailed($record);

                                Notification::make()
                                    ->title('Badge generation failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('mark_badge_printed')
                        ->label('Mark Printed')
                        ->icon('heroicon-o-printer')
                        ->color('gray')
                        ->visible(fn ($record): bool => self::badgeExists($record))
                        ->requiresConfirmation()
                        ->modalHeading('Mark badge as printed?')
                        ->action(function ($record): void {
                            self::markBadgePrinted($record);

                            Notification::make()
                                ->title('Badge marked as printed')
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
                        ->modalDescription('This attendee will be approved and a badge will be generated.')
                        ->action(function ($record): void {
                            $record->forceFill([
                                'status' => 'registered',
                            ])->save();

                            try {
                                self::generateBadgeForRecord($record);

                                Notification::make()
                                    ->title('Registration approved')
                                    ->body('The attendee was approved and the badge was generated.')
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                self::markBadgeFailed($record);

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
                                self::generateBadgeForRecord($record);

                                Notification::make()
                                    ->title('Attendee moved to registered')
                                    ->body('The attendee was registered and the badge was generated.')
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                self::markBadgeFailed($record);

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
                        ->modalDescription('This attendee will be marked as rejected. Their badge will not be released.')
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

                    Action::make('view_qr_code')
                        ->label('View QR')
                        ->icon('heroicon-o-qr-code')
                        ->color('primary')
                        ->url(fn ($record): string => AttendeeResource::getUrl('qr-code', [
                            'record' => $record,
                        ])),

                    EditAction::make(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->toolbarActions([
                Action::make('export_all_attendees')
                    ->label('Export All')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->action(function () {
                        return Excel::download(
                            new AttendeesExport(),
                            'all-attendees-' . now()->format('Y-m-d-His') . '.xlsx'
                        );
                    }),

                Action::make('export_checked_in_attendees')
                    ->label('Export Checked-In')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function () {
                        return Excel::download(
                            new AttendeesExport('checked_in'),
                            'checked-in-attendees-' . now()->format('Y-m-d-His') . '.xlsx'
                        );
                    }),

                Action::make('export_not_checked_in_attendees')
                    ->label('Export Not Checked-In')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->action(function () {
                        return Excel::download(
                            new AttendeesExport('not_checked_in'),
                            'not-checked-in-attendees-' . now()->format('Y-m-d-His') . '.xlsx'
                        );
                    }),

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
                                    self::generateBadgeForRecord($record);
                                    $badgesGenerated++;
                                } catch (Throwable $e) {
                                    report($e);
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
                                    self::generateBadgeForRecord($record);
                                    $badgesGenerated++;
                                } catch (Throwable $e) {
                                    report($e);
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
                        ->modalHeading('Generate badges?')
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
                                    self::generateBadgeForRecord($record);
                                    $generated++;
                                } catch (Throwable $e) {
                                    report($e);
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
                        ->modalHeading('Mark selected badges as printed?')
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
            ->defaultSort('created_at', 'desc');
    }

    protected static function badgeExists($record): bool
    {
        return filled($record->badge_path)
            && Storage::disk('public')->exists($record->badge_path);
    }

    protected static function badgeUrl($record): string
    {
        return asset('storage/' . ltrim((string) $record->badge_path, '/'));
    }

    protected static function generateBadgeForRecord($record): void
    {
        if (Schema::hasColumn('attendees', 'badge_status')) {
            $record->forceFill([
                'badge_status' => 'generating',
            ])->save();
        }

        app(BadgeGenerationService::class)->generateForAttendee(
            $record->fresh(['event', 'category', 'badgeType', 'qrToken'])
        );

        $data = [];

        if (Schema::hasColumn('attendees', 'badge_status')) {
            $data['badge_status'] = 'generated';
        }

        if (Schema::hasColumn('attendees', 'badge_generated_at')) {
            $data['badge_generated_at'] = now();
        }

        if ($data !== []) {
            $record->fresh()->forceFill($data)->save();
        }
    }

    protected static function markBadgeFailed($record): void
    {
        if (! Schema::hasColumn('attendees', 'badge_status')) {
            return;
        }

        $record->fresh()->forceFill([
            'badge_status' => 'failed',
        ])->save();
    }

    protected static function markBadgePrinted($record): bool
    {
        if (! self::badgeExists($record)) {
            return false;
        }

        $data = [];

        if (Schema::hasColumn('attendees', 'badge_status')) {
            $data['badge_status'] = 'printed';
        }

        if (Schema::hasColumn('attendees', 'badge_printed_at')) {
            $data['badge_printed_at'] = now();
        }

        if ($data === []) {
            return false;
        }

        $record->forceFill($data)->save();

        return true;
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