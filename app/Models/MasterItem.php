<?php

namespace App\Models;

use App\Enums\MasterItemStatus;
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
 * @property MasterItemStatus $status
 * @property string|null $rejection_reason
 * @property int|null $consolidated_into_id
 */
#[Fillable([
    'category_id', 'created_by_user_id', 'name', 'unit_of_measure', 'description',
    'requires_cold_chain', 'reorder_point', 'status', 'rejection_reason', 'consolidated_into_id',
])]
class MasterItem extends Model
{
    /**
     * El nombre lo escribe una persona (un operador lo propone desde el Kardex,
     * un admin puede corregirlo al aprobarlo) y termina renderizándose en el
     * portal público anónimo. El escapado real vive en el front, pero se rechazan
     * `<` y `>` aquí también como defensa en profundidad: ningún insumo real los
     * necesita — ver docs/17-Auditoria-Frontend.md, hallazgo C-1.
     *
     * @return array<int, string>
     */
    public static function nameRules(): array
    {
        return ['required', 'string', 'max:150', 'regex:/^[^<>]+$/'];
    }

    protected function casts(): array
    {
        return [
            'requires_cold_chain' => 'boolean',
            'status' => MasterItemStatus::class,
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
     * @return BelongsTo<MasterItem, $this>
     */
    public function consolidatedInto(): BelongsTo
    {
        return $this->belongsTo(MasterItem::class, 'consolidated_into_id');
    }

    /**
     * @return HasMany<MasterItem, $this>
     */
    public function duplicates(): HasMany
    {
        return $this->hasMany(MasterItem::class, 'consolidated_into_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
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
            ->withAvailableQuantity()
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
