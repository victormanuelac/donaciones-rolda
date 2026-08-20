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
 * @property string $status
 * @property string|null $rejection_reason
 */
#[Fillable([
    'category_id', 'created_by_user_id', 'name', 'unit_of_measure', 'description',
    'requires_cold_chain', 'status', 'rejection_reason',
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
}
