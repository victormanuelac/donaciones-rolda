<?php

declare(strict_types=1);

namespace App\Actions\MasterItems;

use App\Enums\MasterItemStatus;
use App\Models\AuditLog;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Marca un ítem como duplicado de otro ya aprobado. Reasigna cualquier
 * existencia ya registrada contra el duplicado hacia el ítem destino, para
 * no perder stock que un operador ya haya cargado antes de que se detectara
 * el duplicado.
 */
class ConsolidateMasterItemAction
{
    public function handle(MasterItem $duplicate, MasterItem $target, User $admin): MasterItem
    {
        if ($duplicate->is($target)) {
            throw new InvalidArgumentException('Un ítem no puede consolidarse consigo mismo.');
        }

        return DB::transaction(function () use ($duplicate, $target, $admin) {
            StockEntry::where('master_item_id', $duplicate->id)->update(['master_item_id' => $target->id]);

            $duplicate->update([
                'status' => MasterItemStatus::Consolidated,
                'consolidated_into_id' => $target->id,
            ]);

            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'master_item_consolidated',
                'table_name' => 'master_items',
                'record_id' => $duplicate->id,
                'new_value' => ['consolidated_into_id' => $target->id],
            ]);

            return $duplicate;
        });
    }
}
