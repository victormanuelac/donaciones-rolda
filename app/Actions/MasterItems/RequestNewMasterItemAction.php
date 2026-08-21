<?php

declare(strict_types=1);

namespace App\Actions\MasterItems;

use App\Enums\MasterItemStatus;
use App\Models\AuditLog;
use App\Models\MasterItem;
use App\Models\User;

/**
 * Un operador que no encuentra el ítem que necesita al registrar una entrada
 * (Módulo 3) puede solicitar uno nuevo — queda en revisión hasta que un admin
 * lo apruebe (Módulo 4), así que todavía no aparece en el selector de ítems.
 */
class RequestNewMasterItemAction
{
    /**
     * @param  array{category_id: int, name: string, unit_of_measure: string, description?: string|null}  $payload
     */
    public function handle(array $payload, User $requester): MasterItem
    {
        $item = MasterItem::create([
            ...$payload,
            'created_by_user_id' => $requester->id,
            'status' => MasterItemStatus::UnderReview,
        ]);

        AuditLog::create([
            'user_id' => $requester->id,
            'action' => 'master_item_requested',
            'table_name' => 'master_items',
            'record_id' => $item->id,
            'new_value' => $payload,
        ]);

        return $item;
    }
}
