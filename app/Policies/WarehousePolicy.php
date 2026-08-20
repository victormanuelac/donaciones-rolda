<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    /**
     * Determine whether the user can manage the bodega/centro de acopio catalog
     * (crear, editar, activar/desactivar). Es administración de datos maestros,
     * separado de poder registrar movimientos de stock (ver manageStock()).
     */
    public function manageCatalog(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can register stock entries/exits for the given warehouse.
     */
    public function manageStock(User $user, Warehouse $warehouse): bool
    {
        if (! $user->canManageStock()) {
            return false;
        }

        if (in_array($user->role, [UserRole::Admin, UserRole::Coordinator], true)) {
            return $warehouse->is_active;
        }

        return $warehouse->is_active
            && $user->warehouseAssignments()->where('warehouse_id', $warehouse->id)->where('is_active', true)->exists();
    }
}
