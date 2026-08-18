<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view the pending-approval queue.
     */
    public function viewPendingQueue(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can approve the given pending user.
     */
    public function approve(User $user, User $target): bool
    {
        return $user->isAdmin() && $target->status === UserStatus::Pending;
    }

    /**
     * Determine whether the user can reject the given pending user.
     */
    public function reject(User $user, User $target): bool
    {
        return $user->isAdmin() && $target->status === UserStatus::Pending;
    }
}
