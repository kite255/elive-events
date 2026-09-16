<?php

namespace App\Policies;

use App\Models\EventTicketTemplate;
use App\Models\User;

class EventTicketTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->managedOrganizations()->exists()
            || $user->eventManagerEvents()->exists();
    }

    public function view(
        User $user,
        EventTicketTemplate $template
    ): bool {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $event = $template->event;

        if (! $event) {
            return false;
        }

        if (
            $user->canManageOrganization(
                $event->organization_id
            )
        ) {
            return true;
        }

        return $user->hasEventAssignmentRole(
            $event,
            User::ORGANIZATION_ROLE_EVENT_MANAGER
        );
    }

    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->managedOrganizations()->exists()
            || $user->eventManagerEvents()->exists();
    }

    public function update(
        User $user,
        EventTicketTemplate $template
    ): bool {
        return $this->view(
            $user,
            $template
        );
    }

    public function delete(
        User $user,
        EventTicketTemplate $template
    ): bool {
        return $this->update(
            $user,
            $template
        );
    }

    public function restore(
        User $user,
        EventTicketTemplate $template
    ): bool {
        return $this->update(
            $user,
            $template
        );
    }

    public function forceDelete(
        User $user,
        EventTicketTemplate $template
    ): bool {
        return $this->update(
            $user,
            $template
        );
    }
}
