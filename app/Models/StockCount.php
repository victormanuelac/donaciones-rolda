<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conteo físico de un lote (Módulo 3, Kardex): reconcilia lo que dice el
 * sistema contra lo que hay realmente en la bodega. Si hay diferencia, el
 * registro va acompañado de un StockExit (si sobra menos, motivo
 * `inventory_adjustment`) o un StockEntry nuevo con `adjustment_stock_count_id`
 * apuntando a este conteo (si sobra más) — ver RegisterStockCountAction.
 *
 * @property int $id
 * @property int $stock_entry_id
 * @property int $warehouse_id
 * @property int $counted_by_user_id
 * @property int $system_quantity
 * @property int $counted_quantity
 * @property int $difference
 * @property string|null $notes
 */
#[Fillable([
    'stock_entry_id', 'warehouse_id', 'counted_by_user_id', 'system_quantity',
    'counted_quantity', 'difference', 'notes',
])]
class StockCount extends Model
{
    /**
     * @return BelongsTo<StockEntry, $this>
     */
    public function stockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by_user_id');
    }
}
