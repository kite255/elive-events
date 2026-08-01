<?php

namespace App\Exports;

use App\Models\Attendee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendeesExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        protected ?string $status = null,
        protected ?int $eventId = null,
    ) {}

    public function query(): Builder
    {
        return Attendee::query()
            ->with(['event', 'category', 'badgeType'])
            ->when($this->eventId, function (Builder $query) {
                $query->where('event_id', $this->eventId);
            })
            ->when($this->status === 'checked_in', function (Builder $query) {
                $query->whereNotNull('checked_in_at');
            })
            ->when($this->status === 'not_checked_in', function (Builder $query) {
                $query->whereNull('checked_in_at');
            })
            ->when($this->status === 'badge_generated', function (Builder $query) {
                if (Schema::hasColumn('attendees', 'badge_status')) {
                    $query->whereIn('badge_status', ['generated', 'printed']);
                } else {
                    $query->whereNotNull('badge_path');
                }
            })
            ->when($this->status === 'badge_pending', function (Builder $query) {
                if (Schema::hasColumn('attendees', 'badge_status')) {
                    $query->where(function (Builder $query) {
                        $query->whereNull('badge_status')
                            ->orWhereIn('badge_status', ['pending', 'generating', 'failed']);
                    });
                } else {
                    $query->whereNull('badge_path');
                }
            })
            ->when($this->status === 'badge_printed', function (Builder $query) {
                if (Schema::hasColumn('attendees', 'badge_status')) {
                    $query->where('badge_status', 'printed');
                }
            })
            ->latest();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Full Name',
            'Phone',
            'Email',
            'Organization',
            'Position',
            'Event',
            'Category',
            'Badge Type',
            'Badge Number',
            'Badge Status',
            'Attendance Status',
            'Registration Source',
            'Registered At',
            'Checked In At',
            'Badge Generated At',
            'Badge Printed At',
            'Created At',
        ];
    }

    public function map($attendee): array
    {
        return [
            $attendee->id,
            $attendee->full_name,
            $attendee->phone,
            $attendee->email,
            $attendee->organization_name,
            $attendee->position,
            $attendee->event?->name,
            $attendee->category?->name,
            $attendee->badgeType?->name,
            $attendee->badge_number,
            $attendee->badge_status ?? null,
            $attendee->status,
            $attendee->registration_source,
            $attendee->registered_at?->format('Y-m-d H:i:s'),
            $attendee->checked_in_at?->format('Y-m-d H:i:s'),
            $attendee->badge_generated_at?->format('Y-m-d H:i:s'),
            $attendee->badge_printed_at?->format('Y-m-d H:i:s'),
            $attendee->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}