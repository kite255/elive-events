<?php

namespace App\Filament\Resources\Events\Widgets;

use App\Models\Event;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class EventOverviewStats extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected function getStats(): array
    {
        /** @var Event|null $event */
        $event = $this->record;

        if (! $event) {
            return [];
        }

        /*
        |--------------------------------------------------------------------------
        | Registration
        |--------------------------------------------------------------------------
        */

        $totalRegistrations = $event->attendees()
            ->count();

        $eligibleAttendees = $event->attendees()
            ->whereNotIn('status', [
                'pending_approval',
                'waitlisted',
                'cancelled',
                'rejected',
            ])
            ->count();

        $pendingApproval = $event->attendees()
            ->where('status', 'pending_approval')
            ->count();

        $waitlisted = $event->attendees()
            ->where('status', 'waitlisted')
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Attendance
        |--------------------------------------------------------------------------
        |
        | Use the check_ins table instead of attendees.checked_in_at so
        | multi-day events remain accurate. One attendee may have several
        | check-in records, therefore attendee_id is counted distinctly.
        |
        */

        $checkedIn = $event->checkIns()
            ->whereNull('event_session_id')
            ->distinct('attendee_id')
            ->count('attendee_id');

        $notCheckedIn = max(
            0,
            $eligibleAttendees - $checkedIn
        );

        $attendanceRate = $eligibleAttendees > 0
            ? round(
                ($checkedIn / $eligibleAttendees) * 100,
                1
            )
            : 0;

        $todayCheckIns = $event->checkIns()
            ->whereNull('event_session_id')
            ->whereDate('checked_in_at', today())
            ->distinct('attendee_id')
            ->count('attendee_id');

        $qrCheckIns = $event->checkIns()
            ->whereIn('method', [
                'qr',
                'badge_number',
                'gate_scanner',
            ])
            ->count();

        $manualCheckIns = $event->checkIns()
            ->where('method', 'manual')
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Badges
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasColumn(
                'attendees',
                'badge_status'
            )
        ) {
            $badgeGenerated = $event->attendees()
                ->where('badge_status', 'generated')
                ->count();

            $badgePrinted = $event->attendees()
                ->where('badge_status', 'printed')
                ->count();

            $badgePending = $event->attendees()
                ->whereNotIn('status', [
                    'pending_approval',
                    'waitlisted',
                    'cancelled',
                    'rejected',
                ])
                ->where(function ($query): void {
                    $query
                        ->whereNull('badge_status')
                        ->orWhereIn(
                            'badge_status',
                            [
                                'pending',
                                'generating',
                                'failed',
                            ]
                        );
                })
                ->count();
        } else {
            $badgeGenerated = $event->attendees()
                ->whereNotNull('badge_path')
                ->where('badge_path', '!=', '')
                ->count();

            $badgePrinted = 0;

            $badgePending = max(
                0,
                $eligibleAttendees - $badgeGenerated
            );
        }

        $badgeCompleted =
            $badgeGenerated + $badgePrinted;

        $badgeRate = $eligibleAttendees > 0
            ? round(
                ($badgeCompleted / $eligibleAttendees) * 100,
                1
            )
            : 0;

        return [
            Stat::make(
                'Total Registrations',
                number_format($totalRegistrations)
            )
                ->description(
                    "Eligible: "
                    . number_format($eligibleAttendees)
                )
                ->icon('heroicon-o-users')
                ->color('primary'),

            Stat::make(
                'Checked In',
                number_format($checkedIn)
            )
                ->description(
                    $attendanceRate
                    . '% attendance · '
                    . number_format($todayCheckIns)
                    . ' today'
                )
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make(
                'Not Checked In',
                number_format($notCheckedIn)
            )
                ->description(
                    'Eligible attendees remaining'
                )
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make(
                'Pending Approval',
                number_format($pendingApproval)
            )
                ->description('Awaiting organizer action')
                ->icon(
                    'heroicon-o-document-check'
                )
                ->color(
                    $pendingApproval > 0
                        ? 'warning'
                        : 'gray'
                ),

            Stat::make(
                'Waitlist',
                number_format($waitlisted)
            )
                ->description('Waiting for capacity')
                ->icon('heroicon-o-user-group')
                ->color(
                    $waitlisted > 0
                        ? 'warning'
                        : 'gray'
                ),

            Stat::make(
                'Badges Ready',
                number_format($badgeCompleted)
            )
                ->description(
                    $badgeRate
                    . '% completion · '
                    . number_format($badgePending)
                    . ' pending'
                )
                ->icon('heroicon-o-identification')
                ->color('success'),

            Stat::make(
                'Badges Printed',
                number_format($badgePrinted)
            )
                ->description('Recorded as printed')
                ->icon('heroicon-o-printer')
                ->color('info'),

            Stat::make(
                'Check-in Methods',
                number_format(
                    $qrCheckIns + $manualCheckIns
                )
            )
                ->description(
                    number_format($qrCheckIns)
                    . ' QR · '
                    . number_format($manualCheckIns)
                    . ' manual'
                )
                ->icon('heroicon-o-qr-code')
                ->color('gray'),
        ];
    }
}
