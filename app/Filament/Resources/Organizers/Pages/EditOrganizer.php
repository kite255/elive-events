<?php

namespace App\Filament\Resources\Organizers\Pages;

use App\Filament\Resources\Organizers\OrganizerResource;
use App\Models\User;
use App\Services\Organizers\OrganizerManagementService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOrganizer extends EditRecord
{
    protected static string $resource =
        OrganizerResource::class;

    /*
    |--------------------------------------------------------------------------
    | Form hydration
    |--------------------------------------------------------------------------
    */

    protected function mutateFormDataBeforeFill(
        array $data
    ): array {
        /** @var User $organizer */
        $organizer = $this->getRecord();

        $membership = $organizer
            ->organizations()
            ->wherePivot(
                'role',
                User::ORGANIZATION_ROLE_TICKET_ORGANIZER
            )
            ->first();

        $data['organization_id'] =
            $membership?->getKey();

        $data['status'] =
            $membership?->pivot?->status
            ?? User::ORGANIZATION_STATUS_ACTIVE;

        $data['assigned_event_ids'] =
            $organizer
                ->assignedTicketingEventIds()
                ->values()
                ->all();

        /*
         * Never send the existing password hash
         * back into the edit form.
         */
        $data['password'] = null;

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    protected function handleRecordUpdate(
        Model $record,
        array $data
    ): Model {
        return app(
            OrganizerManagementService::class
        )->updateAssignment(
            $record,
            $data
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl(
            'index'
        );
    }
}