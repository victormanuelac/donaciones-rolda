<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $municipality
 * @property string $zone_type
 * @property string $name
 * @property string|null $code
 * @property int|null $parent_zone_id
 * @property float|null $latitude
 * @property float|null $longitude
 * @property bool $is_active
 */
#[Fillable(['municipality', 'zone_type', 'name', 'code', 'parent_zone_id', 'latitude', 'longitude', 'is_active'])]
class GeographicZone extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Family, $this>
     */
    public function families(): HasMany
    {
        return $this->hasMany(Family::class, 'zone_id');
    }
}
