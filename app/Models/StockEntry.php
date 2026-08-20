<?php

namespace App\Models;

use App\Enums\StockEntryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 */
#[Fillable([
    'master_item_id', 'warehouse_id', 'registered_by_user_id', 'confirmed_by_user_id',
    'quantity', 'lot_number', 'expiry_date', 'received_date', 'status', 'notes', 'photo_path', 'client_uuid',
    'transferred_from_stock_entry_id',
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
     * Cantidad restante en este lote: lo ingresado menos lo ya despachado.
     */
    public function availableQuantity(): int
    {
        return $this->quantity - (int) $this->exits()->sum('quantity_released');
    }
}
