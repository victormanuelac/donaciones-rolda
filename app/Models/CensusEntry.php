<?php

namespace App\Models;

use App\Enums\CensusPriorityLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $family_id
 * @property int|null $user_id
 * @property string $form_code
 * @property string $phase
 * @property string $form_version
 * @property Carbon $surveyed_at
 * @property string $surveyor_entity
 * @property string|null $client_uuid
 * @property string $sync_status
 * @property bool $consent_given
 * @property string|null $consent_minors
 * @property string|null $consent_given_by_name
 * @property string|null $consent_relationship
 * @property int $total_people
 * @property int $under_5_count
 * @property int $over_60_count
 * @property int $pregnant_lactating_count
 * @property int $disability_count
 * @property int $chronic_illness_count
 * @property int|null $meals_yesterday
 * @property int|null $rcsi_less_preferred
 * @property int|null $rcsi_borrow_food
 * @property int|null $rcsi_reduce_portion
 * @property int|null $rcsi_reduce_adult_consumption
 * @property int|null $rcsi_reduce_meals
 * @property bool $injured
 * @property bool $needs_urgent_medical_attention
 * @property bool $lost_permanent_medication
 * @property string $sleeping_location
 * @property string $needs_temporary_shelter
 * @property array<int, string>|null $environment_risks
 * @property string $access_passable
 * @property array<int, string> $priority_needs
 * @property string $registered_in_rud
 * @property string $damage_verified
 * @property bool $needs_structural_assessment
 * @property string|null $signature_path
 * @property int $vulnerability_score
 * @property CensusPriorityLevel $priority_level
 * @property array<int, string>|null $red_flags
 */
#[Fillable([
    'family_id', 'user_id', 'form_code', 'phase', 'form_version', 'surveyed_at', 'surveyor_entity',
    'client_uuid', 'sync_status', 'consent_given', 'consent_minors', 'consent_given_by_name', 'consent_relationship',
    'total_people', 'under_5_count', 'over_60_count', 'pregnant_lactating_count', 'disability_count', 'chronic_illness_count',
    'meals_yesterday', 'rcsi_less_preferred', 'rcsi_borrow_food', 'rcsi_reduce_portion', 'rcsi_reduce_adult_consumption', 'rcsi_reduce_meals',
    'injured', 'needs_urgent_medical_attention', 'lost_permanent_medication',
    'sleeping_location', 'needs_temporary_shelter', 'environment_risks', 'access_passable', 'priority_needs', 'registered_in_rud',
    'damage_verified', 'needs_structural_assessment', 'signature_path',
    'vulnerability_score', 'priority_level', 'red_flags',
])]
class CensusEntry extends Model
{
    protected function casts(): array
    {
        return [
            'surveyed_at' => 'datetime',
            'consent_given' => 'boolean',
            'injured' => 'boolean',
            'needs_urgent_medical_attention' => 'boolean',
            'lost_permanent_medication' => 'boolean',
            'needs_structural_assessment' => 'boolean',
            'environment_risks' => 'array',
            'priority_needs' => 'array',
            'red_flags' => 'array',
            'priority_level' => CensusPriorityLevel::class,
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
     * @return BelongsTo<User, $this>
     */
    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<Beneficiary, $this>
     */
    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }
}
