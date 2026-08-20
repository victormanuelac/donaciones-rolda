<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $zone_id
 * @property string $department
 * @property string $municipality
 * @property string $zone_type
 * @property string|null $neighborhood
 * @property string $address
 * @property string|null $phone
 * @property string|null $route_code
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $gps_accuracy_meters
 * @property Carbon|null $gps_captured_at
 * @property string|null $facade_photo_path
 * @property string $head_full_name
 * @property string|null $head_document_type
 * @property string|null $head_document_number
 * @property string|null $head_sex
 * @property Carbon|null $head_birthdate
 * @property string|null $head_gender_identity
 * @property string $housing_damage_level
 * @property string|null $housing_inspection_mark
 * @property string|null $tenure_type
 * @property int|null $monthly_rent
 * @property string|null $water_access
 * @property string|null $water_source
 * @property string|null $electricity_access
 * @property string|null $sanitation_access
 * @property int|null $rooms_count
 * @property int $household_size
 * @property bool $overcrowding
 */
#[Fillable([
    'zone_id', 'department', 'municipality', 'zone_type', 'neighborhood', 'address', 'phone', 'route_code',
    'latitude', 'longitude', 'gps_accuracy_meters', 'gps_captured_at', 'facade_photo_path',
    'head_full_name', 'head_document_type', 'head_document_number', 'head_sex', 'head_birthdate', 'head_gender_identity',
    'housing_damage_level', 'housing_inspection_mark', 'tenure_type', 'monthly_rent',
    'water_access', 'water_source', 'electricity_access', 'sanitation_access', 'rooms_count',
    'household_size', 'overcrowding',
])]
class Family extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'gps_captured_at' => 'datetime',
            'head_document_number' => 'encrypted',
            'head_birthdate' => 'date',
            'phone' => 'encrypted',
            'monthly_rent' => 'integer',
            'household_size' => 'integer',
            'overcrowding' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<GeographicZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(GeographicZone::class, 'zone_id');
    }

    /**
     * @return HasMany<CensusEntry, $this>
     */
    public function censusEntries(): HasMany
    {
        return $this->hasMany(CensusEntry::class);
    }

    /**
     * @return HasMany<Beneficiary, $this>
     */
    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }
}
