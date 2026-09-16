<?php

namespace App\Policies;

use App\Models\OrganizationTicketTemplate;
use App\Models\User;

class OrganizationTicketTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(
        User $user,
        OrganizationTicketTemplate $template
    ): bool {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(
        User $user,
        OrganizationTicketTemplate $template
    ): bool {
        return $user->isSuperAdmin();
    }

    public function delete(
        User $user,
        OrganizationTicketTemplate $template
    ): bool {
        return $user->isSuperAdmin();
    }

    public function restore(
        User $user,
        OrganizationTicketTemplate $template
    ): bool {
        return $user->isSuperAdmin();
    }

    public function forceDelete(
        User $user,
        OrganizationTicketTemplate $template
    ): bool {
        return $user->isSuperAdmin();
    }
}
