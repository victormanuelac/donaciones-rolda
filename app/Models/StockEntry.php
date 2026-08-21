<?php

namespace App\Models;

use App\Enums\StockEntryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $master_item_id
 * @property int $warehouse_id
 * @property int $registered_by_user_id
 * @property int|null $confirmed_by_user_id
 * @property int $quantity
 * @property string|null $lot_number
 * @property Carbon|null $expiry_date
 * @property Carbon|null $received_date
 * @property StockEntryStatus $status
 * @property string|null $notes
 * @property string|null $photo_path
 * @property string|null $client_uuid
 * @property int|null $transferred_from_stock_entry_id
 * @property int|null $adjustment_stock_count_id
 * @property int|null $released_total Solo presente si se consultó con el scope `withAvailableQuantity()`.
 */
#[Fillable([
    'master_item_id', 'warehouse_id', 'registered_by_user_id', 'confirmed_by_user_id',
    'quantity', 'lot_number', 'expiry_date', 'received_date', 'status', 'notes', 'photo_path', 'client_uuid',
    'transferred_from_stock_entry_id', 'adjustment_stock_count_id',
])]
class StockEntry extends Model
{
    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'received_date' => 'date',
            'status' => StockEntryStatus::class,
        ];
    }

    /**
     * @return BelongsTo<MasterItem, $this>
     */
    public function masterItem(): BelongsTo
    {
        return $this->belongsTo(MasterItem::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<StockExit, $this>
     */
    public function exits(): HasMany
    {
        return $this->hasMany(StockExit::class);
    }

    /**
     * @return HasMany<ExpiryAlert, $this>
     */
    public function expiryAlerts(): HasMany
    {
        return $this->hasMany(ExpiryAlert::class);
    }

    /**
     * @return BelongsTo<StockEntry, $this>
     */
    public function transferredFrom(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class, 'transferred_from_stock_entry_id');
    }

    /**
     * @return HasMany<StockCount, $this>
     */
    public function counts(): HasMany
    {
        return $this->hasMany(StockCount::class);
    }

    /**
     * @return BelongsTo<StockCount, $this>
     */
    public function adjustmentStockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'adjustment_stock_count_id');
    }

    /**
     * Precarga lo despachado de cada lote en la misma consulta que los trae.
     * Sin esto, `availableQuantity()` dispara una consulta por fila — el N+1
     * que documenta docs/17-Auditoria-Frontend.md (hallazgo A-2).
     *
     * Es deliberadamente opt-in: las acciones de escritura (salida, traslado,
     * conteo) **no** deben usarlo, porque necesitan leer el saldo fresco dentro
     * de su transacción, no una foto tomada antes.
     *
     * @param  Builder<StockEntry>  $query
     */
    #[Scope]
    protected function withAvailableQuantity(Builder $query): void
    {
        $query->withSum('exits as released_total', 'quantity_released');
    }

    /**
     * Cantidad restante en este lote: lo ingresado menos lo ya despachado.
     *
     * Usa el total precargado por `withAvailableQuantity()` si está disponible;
     * si no, consulta — así el resultado es siempre correcto y las rutas de
     * escritura siguen viendo el saldo real sin cambiar nada.
     */
    public function availableQuantity(): int
    {
        // Se comprueba que el atributo **exista**, no que tenga valor: `withSum`
        // devuelve NULL para los lotes sin salidas, así que un `??` volvería a
        // consultar precisamente en el caso más común.
        $released = array_key_exists('released_total', $this->attributes)
            ? (int) $this->attributes['released_total']
            : (int) $this->exits()->sum('quantity_released');

        return $this->quantity - $released;
    }

    /**
     * ¿Este lote vence dentro de los próximos :days días?
     *
     * Vive aquí y no en la vista porque `diffInDays()` devuelve un valor **con
     * signo** en Carbon 3: una fecha futura da negativo, así que la comparación
     * ingenua `diffInDays(now()) <= 30` daba verdadero para *cualquier*
     * vencimiento futuro y pintaba todo el Kardex en rojo
     * (docs/17-Auditoria-Frontend.md, hallazgo A-3).
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }

        // Misma forma con signo explícito que ya usa UpdateStockEntryStatuses.
        $daysRemaining = Carbon::now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false);

        return $daysRemaining <= $days;
    }
}
