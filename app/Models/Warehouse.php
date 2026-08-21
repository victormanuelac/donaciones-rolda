<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $geographic_zone_id
 * @property string $name
 * @property string $address
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string $contact_person_name
 * @property string $contact_phone
 * @property string|null $contact_email
 * @property int|null $max_capacity_units
 * @property bool $is_active
 * @property string|null $notes
 */
#[Fillable([
    'geographic_zone_id', 'name', 'address', 'latitude', 'longitude',
    'contact_person_name', 'contact_phone', 'contact_email', 'max_capacity_units', 'is_active', 'notes',
])]
class Warehouse extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'contact_phone' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<GeographicZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(GeographicZone::class, 'geographic_zone_id');
    }

    /**
     * @return HasMany<StockEntry, $this>
     */
    public function stockEntries(): HasMany
    {
        return $this->hasMany(StockEntry::class);
    }

    /**
     * @return HasMany<WarehouseAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(WarehouseAssignment::class);
    }

    /**
     * Unidades que ocupan espacio físico ahora mismo: existencias disponibles
     * y también vencidas (siguen ocupando la bodega hasta que alguien las dé
     * de baja), sin contar lotes ya retirados.
     */
    public function occupiedUnits(): int
    {
        return (int) $this->stockEntries()
            ->whereIn('status', ['available', 'expired'])
            ->get()
            ->sum(fn (StockEntry $entry) => $entry->availableQuantity());
    }

    public function isOverCapacity(): bool
    {
        if ($this->max_capacity_units === null) {
            return false;
        }

        return $this->occupiedUnits() > $this->max_capacity_units;
    }
}
