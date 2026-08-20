<?php

namespace App\Models;

use App\Enums\ExpiryAlertType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $stock_entry_id
 * @property ExpiryAlertType $alert_type
 * @property Carbon $detected_at
 * @property Carbon|null $resolved_at
 * @property string|null $resolution_action
 * @property string|null $resolution_notes
 */
#[Fillable(['stock_entry_id', 'alert_type', 'detected_at', 'resolved_at', 'resolution_action', 'resolution_notes'])]
class ExpiryAlert extends Model
{
    protected function casts(): array
    {
        return [
            'alert_type' => ExpiryAlertType::class,
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StockEntry, $this>
     */
    public function stockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class);
    }
}
