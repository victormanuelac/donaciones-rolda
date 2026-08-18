<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Models\User;

class RejectUserAction
{
    /**
     * Rechaza el registro de un usuario pendiente.
     */
    public function handle(User $target): User
    {
        $target->update([
            'status' => UserStatus::Rejected,
        ]);

        return $target;
    }
}
