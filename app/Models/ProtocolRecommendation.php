<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Protocolo de atención (Módulo 7): define bajo qué condiciones (edad,
 * embarazo, condiciones crónicas) se recomiendan ciertos ítems a un
 * beneficiario. Es una librería de referencia — ver BeneficiaryRecommendationService
 * para cómo se cruza contra el perfil de cada persona.
 *
 * @property int $id
 * @property string $protocol_name
 * @property string $source
 * @property array<string, mixed> $trigger_condition
 * @property array<int, array{item_id: int, quantity: int, frequency: string, duration_days: int|null}> $recommended_items
 * @property float $confidence_level
 * @property bool $requires_medical_approval
 * @property bool $is_active
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property string|null $notes
 */
#[Fillable([
    'protocol_name', 'source', 'trigger_condition', 'recommended_items', 'confidence_level',
    'requires_medical_approval', 'is_active', 'valid_from', 'valid_until', 'notes',
])]
class ProtocolRecommendation extends Model
{
    protected function casts(): array
    {
        return [
            'trigger_condition' => 'array',
            'recommended_items' => 'array',
            'confidence_level' => 'decimal:2',
            'requires_medical_approval' => 'boolean',
            'is_active' => 'boolean',
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    /**
     * @return HasMany<BeneficiaryRecommendation, $this>
     */
    public function beneficiaryRecommendations(): HasMany
    {
        return $this->hasMany(BeneficiaryRecommendation::class);
    }
}
