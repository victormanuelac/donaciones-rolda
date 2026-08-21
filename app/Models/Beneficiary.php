<?php

namespace App\Models;

use App\Enums\BeneficiaryPriorityLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property array<int, string>|null $chronic_conditions
 * @property array<int, string>|null $current_symptoms
 * @property Carbon|null $last_medical_review
 * @property string|null $medical_notes
 * @property bool $is_pregnant
 * @property int|null $pregnancy_trimester
 * @property bool $has_disability
 * @property string|null $disability_type
 * @property bool $is_single_parent
 * @property bool $has_no_home
 * @property string|null $employment_status
 * @property string|null $educational_level
 * @property int|null $vulnerability_score
 * @property BeneficiaryPriorityLevel|null $priority_level
 * @property Carbon|null $last_score_update
 */
#[Fillable([
    'family_id', 'census_entry_id', 'full_name', 'document_type', 'document_number',
    'relationship_to_head', 'sex', 'birthdate', 'is_household_head',
    'chronic_conditions', 'current_symptoms', 'last_medical_review', 'medical_notes',
    'is_pregnant', 'pregnancy_trimester', 'has_disability', 'disability_type',
    'is_single_parent', 'has_no_home', 'employment_status', 'educational_level',
    'vulnerability_score', 'priority_level', 'last_score_update',
])]
class Beneficiary extends Model
{
    protected function casts(): array
    {
        return [
            'document_number' => 'encrypted',
            'birthdate' => 'date',
            'is_household_head' => 'boolean',
            'chronic_conditions' => 'encrypted:array',
            'current_symptoms' => 'encrypted:array',
            'last_medical_review' => 'date',
            'medical_notes' => 'encrypted',
            'is_pregnant' => 'boolean',
            'has_disability' => 'boolean',
            'is_single_parent' => 'boolean',
            'has_no_home' => 'boolean',
            'priority_level' => BeneficiaryPriorityLevel::class,
            'last_score_update' => 'datetime',
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

    /**
     * @return HasMany<BeneficiaryRecommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(BeneficiaryRecommendation::class);
    }

    public function age(): ?int
    {
        return $this->birthdate?->age;
    }

    /**
     * Si ya se le hizo el perfil de vulnerabilidad (Fase 2) o todavía solo
     * tiene los datos básicos del roster de la Fase 1.
     */
    public function hasProfile(): bool
    {
        return $this->last_score_update !== null;
    }
}
