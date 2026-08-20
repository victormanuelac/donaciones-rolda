<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $icon_class
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['name', 'icon_class', 'description', 'sort_order', 'is_active'])]
class Category extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<MasterItem, $this>
     */
    public function masterItems(): HasMany
    {
        return $this->hasMany(MasterItem::class);
    }
}
