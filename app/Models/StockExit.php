<?php

namespace App\Models;

use App\Enums\StockExitReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $stock_entry_id
 * @property int $warehouse_id
 * @property int|null $family_id
 * @property int $released_by_user_id
 * @property string|null $received_by_name
 * @property int|null $destination_zone_id
 * @property string|null $destination_description
 * @property StockExitReason $exit_reason
 * @property int $quantity_released
 * @property Carbon $release_date
 * @property bool $signed_by_receiver
 * @property string|null $signature_path
 * @property string|null $notes
 * @property string|null $client_uuid
 */
#[Fillable([
    'stock_entry_id', 'warehouse_id', 'family_id', 'released_by_user_id', 'received_by_name',
    'destination_zone_id', 'destination_description', 'exit_reason', 'quantity_released',
    'release_date', 'signed_by_receiver', 'signature_path', 'notes', 'client_uuid',
])]
class StockExit extends Model
{
    protected function casts(): array
    {
        return [
            'received_by_name' => 'encrypted',
            'exit_reason' => StockExitReason::class,
            'release_date' => 'datetime',
            'signed_by_receiver' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<StockEntry, $this>
     */
    public function stockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<GeographicZone, $this>
     */
    public function destinationZone(): BelongsTo
    {
        return $this->belongsTo(GeographicZone::class, 'destination_zone_id');
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }
}
