<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

class ApproveUserAction
{
    /**
     * Aprueba a un usuario pendiente, asignándole el rol con el que va a operar.
     */
    public function handle(User $target, UserRole $role): User
    {
        $target->update([
            'status' => UserStatus::Active,
            'role' => $role,
        ]);

        return $target;
    }
}
