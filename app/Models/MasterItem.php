<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $category_id
 * @property int|null $created_by_user_id
 * @property string $name
 * @property string $unit_of_measure
 * @property string|null $description
 * @property bool $requires_cold_chain
 * @property int|null $reorder_point
 * @property string $status
 * @property string|null $rejection_reason
 */
#[Fillable([
    'category_id', 'created_by_user_id', 'name', 'unit_of_measure', 'description',
    'requires_cold_chain', 'reorder_point', 'status', 'rejection_reason',
])]
class MasterItem extends Model
{
    protected function casts(): array
    {
        return [
            'requires_cold_chain' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<StockEntry, $this>
     */
    public function stockEntries(): HasMany
    {
        return $this->hasMany(StockEntry::class);
    }

    /**
     * Suma disponible de todos los lotes vigentes de este ítem, limitada a las
     * bodegas indicadas. Usada para comparar contra reorder_point.
     *
     * @param  array<int, int>|int|null  $warehouseIds  Un id, varios, o null para todas las bodegas.
     */
    public function totalAvailableQuantity(array|int|null $warehouseIds = null): int
    {
        return $this->stockEntries()
            ->where('status', 'available')
            ->when($warehouseIds, fn ($query) => $query->whereIn('warehouse_id', (array) $warehouseIds))
            ->get()
            ->sum(fn (StockEntry $entry) => $entry->availableQuantity());
    }

    /**
     * @param  array<int, int>|int|null  $warehouseIds
     */
    public function isBelowReorderPoint(array|int|null $warehouseIds = null): bool
    {
        if ($this->reorder_point === null) {
            return false;
        }

        return $this->totalAvailableQuantity($warehouseIds) <= $this->reorder_point;
    }
}
