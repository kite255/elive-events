<?php

namespace App\Services\Organizers;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizerManagementService
{
    public function createOrAssign(array $data): User
    {
        return DB::transaction(
            function () use ($data): User {
                $organizationId =
                    (int) $data['organization_id'];

                /*
                |--------------------------------------------------------------------------
                | Validate assigned events before changing anything
                |--------------------------------------------------------------------------
                */

                $assignedEventIds =
                    $this->validatedAssignedEventIds(
                        $data,
                        $organizationId
                    );

                $email = strtolower(
                    trim(
                        (string) $data['email']
                    )
                );

                $user = User::query()
                    ->whereRaw(
                        'LOWER(email) = ?',
                        [$email]
                    )
                    ->first();

                if (! $user) {
                    $user = User::query()->create([
                        'name' =>
                            trim(
                                (string) $data['name']
                            ),

                        'email' =>
                            $email,

                        'password' =>
                            $data['password'],

                        'is_super_admin' =>
                            false,
                    ]);
                } else {
                    $updates = [];

                    if (
                        filled(
                            $data['name'] ?? null
                        )
                    ) {
                        $updates['name'] =
                            trim(
                                (string) $data['name']
                            );
                    }

                    if (
                        filled(
                            $data['password'] ?? null
                        )
                    ) {
                        $updates['password'] =
                            $data['password'];
                    }

                    if ($updates !== []) {
                        $user->update(
                            $updates
                        );
                    }
                }

                $status =
                    $data['status']
                    ?? User::ORGANIZATION_STATUS_ACTIVE;

                $this->syncTicketOrganizerAssignment(
                    $user,
                    $organizationId,
                    $status
                );

                /*
                |--------------------------------------------------------------------------
                | Event assignments
                |--------------------------------------------------------------------------
                |
                | Only sync event assignments when the field is supplied.
                |
                | This preserves backwards compatibility for existing service
                | calls that do not yet send assigned_event_ids.
                |
                */

                if (
                    array_key_exists(
                        'assigned_event_ids',
                        $data
                    )
                ) {
                    $this->syncTicketOrganizerEvents(
                        $user,
                        $assignedEventIds,
                        $status
                    );
                }

                return $user->fresh();
            }
        );
    }

    public function updateAssignment(
        User $user,
        array $data
    ): User {
        return DB::transaction(
            function () use (
                $user,
                $data
            ): User {
                $organizationId =
                    (int) $data['organization_id'];

                /*
                |--------------------------------------------------------------------------
                | Validate events before updating user or membership
                |--------------------------------------------------------------------------
                */

                $assignedEventIds =
                    $this->validatedAssignedEventIds(
                        $data,
                        $organizationId
                    );

                $updates = [];

                if (
                    filled(
                        $data['name'] ?? null
                    )
                ) {
                    $updates['name'] =
                        trim(
                            (string) $data['name']
                        );
                }

                if (
                    filled(
                        $data['email'] ?? null
                    )
                ) {
                    $updates['email'] =
                        strtolower(
                            trim(
                                (string) $data['email']
                            )
                        );
                }

                /*
                 * Blank password means:
                 * keep the current password unchanged.
                 */
                if (
                    filled(
                        $data['password'] ?? null
                    )
                ) {
                    $updates['password'] =
                        $data['password'];
                }

                if ($updates !== []) {
                    $user->update(
                        $updates
                    );
                }

                $status =
                    $data['status']
                    ?? User::ORGANIZATION_STATUS_ACTIVE;

                $this->syncTicketOrganizerAssignment(
                    $user,
                    $organizationId,
                    $status
                );

                if (
                    array_key_exists(
                        'assigned_event_ids',
                        $data
                    )
                ) {
                    $this->syncTicketOrganizerEvents(
                        $user,
                        $assignedEventIds,
                        $status
                    );
                }

                return $user->fresh();
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Organization assignment
    |--------------------------------------------------------------------------
    */

    private function syncTicketOrganizerAssignment(
        User $user,
        int $organizationId,
        string $status
    ): void {
        /*
         * One Ticketing Manager may belong to only one organization
         * as ticket_organizer.
         *
         * Other memberships are preserved.
         */
        DB::table('organization_user')
            ->where(
                'user_id',
                $user->id
            )
            ->where(
                'role',
                User::ORGANIZATION_ROLE_TICKET_ORGANIZER
            )
            ->where(
                'organization_id',
                '!=',
                $organizationId
            )
            ->delete();

        $existingMembership =
            $user->organizations()
                ->where(
                    'organizations.id',
                    $organizationId
                )
                ->first();

        if ($existingMembership) {
            $user->organizations()
                ->updateExistingPivot(
                    $organizationId,
                    [
                        'role' =>
                            User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                        'status' =>
                            $status,

                        'is_owner' =>
                            false,

                        'joined_at' =>
                            $existingMembership
                                ->pivot
                                ->joined_at
                            ?? now(),
                    ]
                );

            return;
        }

        $user->organizations()
            ->attach(
                $organizationId,
                [
                    'role' =>
                        User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                    'status' =>
                        $status,

                    'is_owner' =>
                        false,

                    'joined_at' =>
                        now(),
                ]
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Ticketing Manager event assignments
    |--------------------------------------------------------------------------
    */

    private function syncTicketOrganizerEvents(
        User $user,
        array $eventIds,
        string $organizationStatus
    ): void {
        $eventIds = collect($eventIds)
            ->map(
                fn ($eventId): int =>
                    (int) $eventId
            )
            ->filter()
            ->unique()
            ->values();

        /*
         * Only remove event assignments that belong specifically
         * to the Ticketing Manager role.
         *
         * This avoids deleting unrelated event assignments.
         */
        $existingTicketingEventIds =
            $user
                ->assignedEvents()
                ->wherePivot(
                    'role',
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER
                )
                ->pluck(
                    'events.id'
                );

        $eventIdsToRemove =
            $existingTicketingEventIds
                ->diff($eventIds);

        if ($eventIdsToRemove->isNotEmpty()) {
            $user->assignedEvents()
                ->detach(
                    $eventIdsToRemove->all()
                );
        }

        /*
         * A suspended/inactive Ticketing Manager should not receive
         * an active event assignment.
         */
        $eventAssignmentStatus =
            match ($organizationStatus) {
                User::ORGANIZATION_STATUS_SUSPENDED =>
                    Event::STAFF_STATUS_SUSPENDED,

                User::ORGANIZATION_STATUS_INACTIVE =>
                    Event::STAFF_STATUS_INACTIVE,

                default =>
                    Event::STAFF_STATUS_ACTIVE,
            };

        foreach ($eventIds as $eventId) {
            $user->assignedEvents()
                ->syncWithoutDetaching([
                    $eventId => [
                        'role' =>
                            User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                        'status' =>
                            $eventAssignmentStatus,

                        'assigned_at' =>
                            now(),
                    ],
                ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Assigned event validation
    |--------------------------------------------------------------------------
    */

    private function validatedAssignedEventIds(
        array $data,
        int $organizationId
    ): array {
        /*
         * Older calls that do not yet include the event field
         * remain valid.
         */
        if (
            ! array_key_exists(
                'assigned_event_ids',
                $data
            )
        ) {
            return [];
        }

        $eventIds = collect(
            $data['assigned_event_ids'] ?? []
        )
            ->map(
                fn ($eventId): int =>
                    (int) $eventId
            )
            ->filter()
            ->unique()
            ->values();

        if ($eventIds->isEmpty()) {
            return [];
        }

        /*
         * Every selected event must belong to the organization
         * selected for this Ticketing Manager.
         */
        $validEventIds =
            Event::query()
                ->where(
                    'organization_id',
                    $organizationId
                )
                ->whereIn(
                    'id',
                    $eventIds
                )
                ->pluck('id')
                ->map(
                    fn ($eventId): int =>
                        (int) $eventId
                );

        $invalidEventIds =
            $eventIds->diff(
                $validEventIds
            );

        if ($invalidEventIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'assigned_event_ids' => [
                    'All assigned events must belong to the selected organization.',
                ],
            ]);
        }

        return $eventIds->all();
    }
}