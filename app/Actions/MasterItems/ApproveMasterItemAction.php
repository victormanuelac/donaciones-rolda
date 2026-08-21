<?php

declare(strict_types=1);

namespace App\Actions\MasterItems;

use App\Enums\MasterItemStatus;
use App\Models\AuditLog;
use App\Models\MasterItem;
use App\Models\User;

/**
 * Aprueba un ítem en revisión, permitiendo corregir nombre/categoría/unidad
 * de medida antes de que quede disponible en el catálogo (evita tener que
 * rechazar y pedir que lo vuelvan a solicitar por un detalle menor).
 */
class ApproveMasterItemAction
{
    /**
     * @param  array{name?: string, category_id?: int, unit_of_measure?: string}  $edits
     */
    public function handle(MasterItem $item, User $admin, array $edits = []): MasterItem
    {
        $oldValue = $item->only(['name', 'category_id', 'unit_of_measure', 'status']);

        $item->update([
            ...$edits,
            'status' => MasterItemStatus::Approved,
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'master_item_approved',
            'table_name' => 'master_items',
            'record_id' => $item->id,
            'old_value' => $oldValue,
            'new_value' => $item->only(['name', 'category_id', 'unit_of_measure', 'status']),
        ]);

        return $item;
    }
}
