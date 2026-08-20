<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $family_id
 * @property int|null $census_entry_id
 * @property string $full_name
 * @property string|null $document_type
 * @property string|null $document_number
 * @property string|null $relationship_to_head
 * @property string|null $sex
 * @property Carbon|null $birthdate
 * @property bool $is_household_head
 */
#[Fillable([
    'family_id', 'census_entry_id', 'full_name', 'document_type', 'document_number',
    'relationship_to_head', 'sex', 'birthdate', 'is_household_head',
])]
class Beneficiary extends Model
{
    protected function casts(): array
    {
        return [
            'document_number' => 'encrypted',
            'birthdate' => 'date',
            'is_household_head' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * @return BelongsTo<CensusEntry, $this>
     */
    public function censusEntry(): BelongsTo
    {
        return $this->belongsTo(CensusEntry::class);
    }
}
