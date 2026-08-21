<?php

namespace App\Models;

use App\Enums\BeneficiaryRecommendationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Recomendación personalizada de un ítem para un beneficiario, generada al
 * cruzar su perfil de vulnerabilidad contra los ProtocolRecommendation
 * vigentes (ver BeneficiaryRecommendationService).
 *
 * @property int $id
 * @property int $beneficiary_id
 * @property int $protocol_recommendation_id
 * @property int $master_item_id
 * @property int $quantity_recommended
 * @property string $frequency
 * @property int|null $duration_days
 * @property BeneficiaryRecommendationStatus $status
 * @property int $available_stock
 * @property array<int, array{warehouse_id: int, name: string, quantity: int, distance_km: float|null}>|null $available_warehouses
 * @property Carbon $recommended_at
 * @property int $recommended_by_user_id
 * @property string|null $notes
 */
#[Fillable([
    'beneficiary_id', 'protocol_recommendation_id', 'master_item_id', 'quantity_recommended',
    'frequency', 'duration_days', 'status', 'available_stock', 'available_warehouses',
    'recommended_at', 'recommended_by_user_id', 'notes',
])]
class BeneficiaryRecommendation extends Model
{
    protected function casts(): array
    {
        return [
            'status' => BeneficiaryRecommendationStatus::class,
            'available_warehouses' => 'array',
            'recommended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Beneficiary, $this>
     */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    /**
     * @return BelongsTo<ProtocolRecommendation, $this>
     */
    public function protocol(): BelongsTo
    {
        return $this->belongsTo(ProtocolRecommendation::class, 'protocol_recommendation_id');
    }

    /**
     * @return BelongsTo<MasterItem, $this>
     */
    public function masterItem(): BelongsTo
    {
        return $this->belongsTo(MasterItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recommendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recommended_by_user_id');
    }
}
