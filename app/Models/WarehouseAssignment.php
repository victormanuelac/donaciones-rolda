<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $warehouse_id
 * @property Carbon $assigned_at
 * @property Carbon|null $unassigned_at
 * @property bool $is_active
 * @property string|null $notes
 */
#[Fillable(['user_id', 'warehouse_id', 'assigned_at', 'unassigned_at', 'is_active', 'notes'])]
class WarehouseAssignment extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
