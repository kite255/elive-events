<?php

namespace App\Filament\Resources\Events\Pages;

use App\Exports\AttendeesExport;
use App\Filament\Pages\AttendanceDashboard;
use App\Filament\Pages\AttendanceReport;
use App\Filament\Pages\BadgePrintStation;
use App\Filament\Pages\CheckInStation;
use App\Filament\Pages\CommunicationCenter;
use App\Filament\Resources\Attendees\AttendeeResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Widgets\EventOverviewStats;
use App\Filament\Resources\Events\Widgets\EventSummaryWidget;
use App\Jobs\GenerateEventBadgesJob;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Js;
use Maatwebsite\Excel\Facades\Excel;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('registration_status')
                    ->label(
                        fn (): string =>
                            $this->getRecord()->registration_is_open
                                ? 'Registration Open'
                                : 'Registration Closed'
                    )
                    ->icon(
                        fn (): string =>
                            $this->getRecord()->registration_is_open
                                ? 'heroicon-o-check-circle'
                                : 'heroicon-o-x-circle'
                    )
                    ->color(
                        fn (): string =>
                            $this->getRecord()->registration_is_open
                                ? 'success'
                                : 'danger'
                    )
                    ->disabled(),

                Action::make('open_registration_page')
                    ->label('Open Registration Page')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('success')
                    ->visible(
                        fn (): bool =>
                            EventResource::canOpenPublicRegistration(
                                $this->getRecord()
                            )
                    )
                    ->url(
                        fn (): string =>
                            EventResource::getPublicRegistrationUrl(
                                $this->getRecord()
                            )
                    )
                    ->openUrlInNewTab(),

                Action::make('preview_registration_page')
                    ->label('Preview Registration Page')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->visible(
                        fn (): bool =>
                            filled($this->getRecord()->slug)
                    )
                    ->url(
                        fn (): string =>
                            EventResource::getPublicRegistrationUrl(
                                $this->getRecord()
                            )
                    )
                    ->openUrlInNewTab(),

                Action::make('copy_registration_link')
                    ->label('Copy Registration Link')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray')
                    ->visible(
                        fn (): bool =>
                            filled($this->getRecord()->slug)
                    )
                    ->extraAttributes(
                        fn (): array => [
                            'x-on:click' =>
                                'navigator.clipboard.writeText('
                                . Js::from(
                                    EventResource::getPublicRegistrationUrl(
                                        $this->getRecord()
                                    )
                                )
                                . ')',
                        ]
                    )
                    ->action(function (): void {
                        $url = EventResource::getPublicRegistrationUrl(
                            $this->getRecord()
                        );

                        Notification::make()
                            ->title('Registration link copied')
                            ->body($url)
                            ->success()
                            ->send();
                    }),

                Action::make('show_registration_link')
                    ->label('Show Registration Link')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->visible(
                        fn (): bool =>
                            filled($this->getRecord()->slug)
                    )
                    ->modalHeading('Public Registration Link')
                    ->modalDescription(
                        fn (): string =>
                            EventResource::getPublicRegistrationUrl(
                                $this->getRecord()
                            )
                    )
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('view_attendees')
                    ->label('View Attendees')
                    ->icon('heroicon-o-users')
                    ->color('primary')
                    ->url(
                        fn (): string =>
                            AttendeeResource::getUrl('index', [
                                'tableFilters' => [
                                    'event' => [
                                        'value' =>
                                            $this->getRecord()->id,
                                    ],
                                ],
                            ])
                    ),

                Action::make('add_attendee')
                    ->label('Add Attendee')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->visible(
                        fn (): bool =>
                            AttendeeResource::canCreate()
                    )
                    ->url(
                        fn (): string =>
                            AttendeeResource::getUrl('create', [
                                'event_id' =>
                                    $this->getRecord()->id,
                            ])
                    ),

                Action::make('import_attendees')
                    ->label('Import Attendees')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->visible(
                        fn (): bool =>
                            AttendeeResource::canImport()
                    )
                    ->url(
                        fn (): string =>
                            AttendeeResource::getUrl('import', [
                                'event_id' =>
                                    $this->getRecord()->id,
                            ])
                    ),

                Action::make('badge_print_station')
                    ->label('Badge Print Station')
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->url(
                        fn (): string =>
                            BadgePrintStation::getUrl([
                                'event_id' =>
                                    $this->getRecord()->id,
                            ])
                    ),

                Action::make('generate_all_badges')
                    ->label('Generate All Badges')
                    ->icon('heroicon-o-identification')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generate all badges?')
                    ->modalDescription(
                        'This will regenerate badges for every eligible attendee in this event using the current badge template design.'
                    )
                    ->modalSubmitActionLabel('Generate All')
                    ->action(function (): void {
                        $event = $this->getRecord();

                        $attendeesCount = $event->attendees()
                            ->whereNotIn('status', [
                                'rejected',
                                'cancelled',
                                'waitlisted',
                                'pending_approval',
                            ])
                            ->count();

                        if ($attendeesCount === 0) {
                            Notification::make()
                                ->title('No eligible attendees found')
                                ->body(
                                    'This event does not currently have attendees eligible for badge generation.'
                                )
                                ->warning()
                                ->send();

                            return;
                        }

                        GenerateEventBadgesJob::dispatch(
                            $event->id,
                            false
                        );

                        Notification::make()
                            ->title('Badge generation started')
                            ->body(
                                "Generating badges for {$attendeesCount} attendee(s) in the background."
                            )
                            ->success()
                            ->send();
                    }),

                Action::make('generate_missing_badges')
                    ->label('Generate Missing Badges')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Generate missing badges only?')
                    ->modalDescription(
                        'This will generate badges for eligible attendees whose badge is missing, pending, generating, or failed.'
                    )
                    ->modalSubmitActionLabel('Generate Missing')
                    ->action(function (): void {
                        $event = $this->getRecord();

                        $query = $event->attendees()
                            ->whereNotIn('status', [
                                'rejected',
                                'cancelled',
                                'waitlisted',
                                'pending_approval',
                            ])
                            ->where(function ($query): void {
                                $query
                                    ->whereNull('badge_path')
                                    ->orWhere('badge_path', '');

                                if (
                                    Schema::hasColumn(
                                        'attendees',
                                        'badge_status'
                                    )
                                ) {
                                    $query
                                        ->orWhereNull('badge_status')
                                        ->orWhereIn(
                                            'badge_status',
                                            [
                                                'pending',
                                                'generating',
                                                'failed',
                                            ]
                                        );
                                }
                            });

                        $missingBadgesCount = $query->count();

                        if ($missingBadgesCount === 0) {
                            Notification::make()
                                ->title('No missing badges')
                                ->body(
                                    'All eligible attendees already have badges.'
                                )
                                ->success()
                                ->send();

                            return;
                        }

                        GenerateEventBadgesJob::dispatch(
                            $event->id,
                            true
                        );

                        Notification::make()
                            ->title('Missing badge generation started')
                            ->body(
                                "Generating {$missingBadgesCount} missing or failed badge(s) in the background."
                            )
                            ->success()
                            ->send();
                    }),

                Action::make('check_in_station')
                    ->label('Check-in Station')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->url(
                        fn (): string =>
                            CheckInStation::getUrl([
                                'event_id' =>
                                    $this->getRecord()->id,
                            ])
                    ),

                Action::make('attendance_dashboard')
                    ->label('Attendance Dashboard')
                    ->icon('heroicon-o-chart-bar-square')
                    ->color('primary')
                    ->url(
                        fn (): string =>
                            AttendanceDashboard::getUrl([
                                'event_id' =>
                                    $this->getRecord()->id,
                            ])
                    ),

                Action::make('attendance_report')
                    ->label('Attendance Report')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('gray')
                    ->url(
                        fn (): string =>
                            AttendanceReport::getUrl([
                                'event_id' =>
                                    $this->getRecord()->id,
                            ])
                    ),

                Action::make('communication_center')
                    ->label('Communication Center')
                    ->icon('heroicon-o-megaphone')
                    ->color('warning')
                    ->visible(
                        fn (): bool =>
                            CommunicationCenter::canAccess()
                    )
                    ->url(
                        fn (): string =>
                            CommunicationCenter::getUrl([
                                'event_id' =>
                                    $this->getRecord()->id,
                            ])
                    ),
            ])
                ->label('Quick Actions')
                ->icon('heroicon-o-bolt')
                ->button()
                ->color('success'),

            ActionGroup::make([
                Action::make('export_all_attendees')
                    ->label('All Attendees')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        return Excel::download(
                            new AttendeesExport(
                                null,
                                $this->getRecord()->id
                            ),
                            $this->reportFileName(
                                'all-attendees'
                            )
                        );
                    }),

                Action::make('export_checked_in')
                    ->label('Checked-In')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function () {
                        return Excel::download(
                            new AttendeesExport(
                                'checked_in',
                                $this->getRecord()->id
                            ),
                            $this->reportFileName(
                                'checked-in-attendees'
                            )
                        );
                    }),

                Action::make('export_not_checked_in')
                    ->label('Not Checked-In')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->action(function () {
                        return Excel::download(
                            new AttendeesExport(
                                'not_checked_in',
                                $this->getRecord()->id
                            ),
                            $this->reportFileName(
                                'not-checked-in-attendees'
                            )
                        );
                    }),

                Action::make('export_badge_generated')
                    ->label('Badges Generated')
                    ->icon('heroicon-o-identification')
                    ->color('success')
                    ->action(function () {
                        return Excel::download(
                            new AttendeesExport(
                                'badge_generated',
                                $this->getRecord()->id
                            ),
                            $this->reportFileName(
                                'badge-generated-attendees'
                            )
                        );
                    }),

                Action::make('export_badge_pending')
                    ->label('Badges Pending')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->action(function () {
                        return Excel::download(
                            new AttendeesExport(
                                'badge_pending',
                                $this->getRecord()->id
                            ),
                            $this->reportFileName(
                                'badge-pending-attendees'
                            )
                        );
                    }),

                Action::make('export_badge_printed')
                    ->label('Badges Printed')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->action(function () {
                        return Excel::download(
                            new AttendeesExport(
                                'badge_printed',
                                $this->getRecord()->id
                            ),
                            $this->reportFileName(
                                'badge-printed-attendees'
                            )
                        );
                    }),
            ])
                ->label('Export Reports')
                ->icon('heroicon-o-document-arrow-down')
                ->button()
                ->color('primary'),

            DeleteAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            EventSummaryWidget::class,
            EventOverviewStats::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'record' => $this->getRecord(),
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 4;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Event Settings';
    }

    protected function reportFileName(
        string $type
    ): string {
        $eventName = str(
            $this->getRecord()->name
        )
            ->slug()
            ->toString();

        return $eventName
            . '-'
            . $type
            . '-'
            . now()->format('Y-m-d-His')
            . '.xlsx';
    }
}
