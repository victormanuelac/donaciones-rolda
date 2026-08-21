<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MasterItem;
use App\Models\User;

class MasterItemPolicy
{
    /**
     * Determine whether the user can request a new catalog item while
     * registering stock (Módulo 3 → deriva al Módulo 4).
     */
    public function request(User $user): bool
    {
        return $user->canManageStock();
    }

    /**
     * Determine whether the user can view the pending-review queue.
     */
    public function viewPendingQueue(User $user): bool
    {
        return $user->isAdmin();
    }

    public function approve(User $user, MasterItem $item): bool
    {
        return $user->isAdmin();
    }

    public function reject(User $user, MasterItem $item): bool
    {
        return $user->isAdmin();
    }

    public function consolidate(User $user, MasterItem $item): bool
    {
        return $user->isAdmin();
    }
}
