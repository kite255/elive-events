<?php

namespace App\Services\Organizers;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrganizerManagementService
{
    public function createOrAssign(array $data): User
    {
        return DB::transaction(
            function () use ($data): User {
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

                $this->syncTicketOrganizerAssignment(
                    $user,
                    (int) $data['organization_id'],
                    $data['status']
                        ?? User::ORGANIZATION_STATUS_ACTIVE
                );

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

                $this->syncTicketOrganizerAssignment(
                    $user,
                    (int) $data['organization_id'],
                    $data['status']
                        ?? User::ORGANIZATION_STATUS_ACTIVE
                );

                return $user->fresh();
            }
        );
    }

    private function syncTicketOrganizerAssignment(
        User $user,
        int $organizationId,
        string $status
    ): void {
        /*
         * V1 rule:
         *
         * One Ticket Organizer may manage only one organization.
         *
         * Remove Ticket Organizer assignments from every other
         * organization before applying the selected organization.
         *
         * Other organization memberships such as member,
         * event_manager, organization_admin, etc. are preserved.
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
}