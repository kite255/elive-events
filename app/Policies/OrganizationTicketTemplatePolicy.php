<?php

namespace App\Policies;

use App\Models\OrganizationTicketTemplate;
use App\Models\User;

class OrganizationTicketTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->managedOrganizations()
            ->exists();
    }

    public function view(
        User $user,
        OrganizationTicketTemplate $template
    ): bool {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canManageOrganization(
            $template->organization_id
        );
    }

    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->managedOrganizations()
            ->exists();
    }

    public function update(
        User $user,
        OrganizationTicketTemplate $template
    ): bool {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->canManageOrganization(
            $template->organization_id
        );
    }

    public function delete(
        User $user,
        OrganizationTicketTemplate $template
    ): bool {
        return $this->update(
            $user,
            $template
        );
    }

    public function restore(
        User $user,
        OrganizationTicketTemplate $template
    ): bool {
        return $this->update(
            $user,
            $template
        );
    }

    public function forceDelete(
        User $user,
        OrganizationTicketTemplate $template
    ): bool {
        return $this->update(
            $user,
            $template
        );
    }
}
