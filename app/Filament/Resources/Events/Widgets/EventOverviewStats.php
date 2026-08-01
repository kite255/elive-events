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

        $totalAttendees = $event->attendees()->count();

        $checkedIn = $event->attendees()
            ->whereNotNull('checked_in_at')
            ->count();

        $notCheckedIn = max(0, $totalAttendees - $checkedIn);

        $qrCheckIns = $event->checkIns()
            ->where('method', 'qr')
            ->count();

        $manualCheckIns = $event->checkIns()
            ->where('method', 'manual')
            ->count();

        if (Schema::hasColumn('attendees', 'badge_status')) {
            $badgeGenerated = $event->attendees()
                ->where('badge_status', 'generated')
                ->count();

            $badgePrinted = $event->attendees()
                ->where('badge_status', 'printed')
                ->count();

            $badgePending = $event->attendees()
                ->where(function ($query) {
                    $query->whereNull('badge_status')
                        ->orWhereIn('badge_status', ['pending', 'generating', 'failed']);
                })
                ->count();
        } else {
            $badgeGenerated = $event->attendees()
                ->whereNotNull('badge_path')
                ->count();

            $badgePrinted = 0;
            $badgePending = max(0, $totalAttendees - $badgeGenerated);
        }

        $attendanceRate = $totalAttendees > 0
            ? round(($checkedIn / $totalAttendees) * 100, 1)
            : 0;

        $badgeRate = $totalAttendees > 0
            ? round((($badgeGenerated + $badgePrinted) / $totalAttendees) * 100, 1)
            : 0;

        return [
            Stat::make('Total Attendees', number_format($totalAttendees))
                ->description('Registered for this event')
                ->icon('heroicon-o-users')
                ->color('primary'),

            Stat::make('Checked In', number_format($checkedIn))
                ->description($attendanceRate . '% attendance rate')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Not Checked In', number_format($notCheckedIn))
                ->description('Remaining attendees')
                ->icon('heroicon-o-x-circle')
                ->color('warning'),

            Stat::make('Badges Generated', number_format($badgeGenerated))
                ->description($badgeRate . '% badge completion')
                ->icon('heroicon-o-identification')
                ->color('success'),

            Stat::make('Badges Pending', number_format($badgePending))
                ->description('Need generation or retry')
                ->icon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('Badges Printed', number_format($badgePrinted))
                ->description('Marked as printed')
                ->icon('heroicon-o-printer')
                ->color('info'),

            Stat::make('QR Check-ins', number_format($qrCheckIns))
                ->description('Scanned check-ins')
                ->icon('heroicon-o-qr-code')
                ->color('success'),

            Stat::make('Manual Check-ins', number_format($manualCheckIns))
                ->description('Manual entries')
                ->icon('heroicon-o-pencil-square')
                ->color('gray'),
        ];
    }
}