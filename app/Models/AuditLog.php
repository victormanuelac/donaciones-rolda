<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $table_name
 * @property int|null $record_id
 * @property array<string, mixed>|null $old_value
 * @property array<string, mixed>|null $new_value
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $context
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'action', 'table_name', 'record_id', 'old_value', 'new_value', 'ip_address', 'user_agent', 'context'])]
class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
