<?php

declare(strict_types=1);

namespace App\Services\Beneficiaries;

use App\Enums\BeneficiaryRecommendationStatus;
use App\Models\Beneficiary;
use App\Models\BeneficiaryRecommendation;
use App\Models\MasterItem;
use App\Models\ProtocolRecommendation;
use App\Models\StockEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Geo\HaversineDistance;

/**
 * Motor de recomendaciones del Módulo 7 (Fase 2): cruza el perfil de un
 * beneficiario contra los ProtocolRecommendation vigentes y genera una
 * BeneficiaryRecommendation por cada ítem recomendado, con una foto del
 * stock disponible en el momento. Idempotente por (beneficiario, protocolo,
 * ítem) — volver a generar actualiza en vez de duplicar.
 */
class BeneficiaryRecommendationService
{
    /**
     * @return array<int, BeneficiaryRecommendation>
     */
    public function generateFor(Beneficiary $beneficiary, User $recommender): array
    {
        $age = $beneficiary->age();

        $protocols = ProtocolRecommendation::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', today()))
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', today()))
            ->get()
            ->filter(fn (ProtocolRecommendation $protocol) => $this->matches($protocol, $beneficiary, $age));

        $created = [];

        foreach ($protocols as $protocol) {
            foreach ($protocol->recommended_items as $itemSpec) {
                $item = MasterItem::find($itemSpec['item_id']);

                if ($item === null) {
                    continue;
                }

                $snapshot = $this->stockSnapshot($item, $beneficiary);

                $created[] = BeneficiaryRecommendation::updateOrCreate(
                    [
                        'beneficiary_id' => $beneficiary->id,
                        'protocol_recommendation_id' => $protocol->id,
                        'master_item_id' => $item->id,
                    ],
                    [
                        'quantity_recommended' => $itemSpec['quantity'],
                        'frequency' => $itemSpec['frequency'],
                        'duration_days' => $itemSpec['duration_days'] ?? null,
                        'status' => BeneficiaryRecommendationStatus::Pending,
                        'available_stock' => $snapshot['total_stock'],
                        'available_warehouses' => $snapshot['warehouses'],
                        'recommended_at' => now(),
                        'recommended_by_user_id' => $recommender->id,
                    ]
                );
            }
        }

        return $created;
    }

    private function matches(ProtocolRecommendation $protocol, Beneficiary $beneficiary, ?int $age): bool
    {
        $condition = $protocol->trigger_condition;

        if (isset($condition['age_min']) || isset($condition['age_max'])) {
            if ($age === null) {
                return false;
            }

            if (isset($condition['age_min']) && $age < $condition['age_min']) {
                return false;
            }

            if (isset($condition['age_max']) && $age > $condition['age_max']) {
                return false;
            }
        }

        if (array_key_exists('pregnancy', $condition) && $condition['pregnancy'] && ! $beneficiary->is_pregnant) {
            return false;
        }

        if (! empty($condition['chronic_diseases'])) {
            $beneficiaryConditions = $beneficiary->chronic_conditions ?? [];

            if (array_intersect($condition['chronic_diseases'], $beneficiaryConditions) === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{total_stock: int, warehouses: list<array{warehouse_id: int, name: string, quantity: int, distance_km: float|null}>}
     */
    private function stockSnapshot(MasterItem $item, Beneficiary $beneficiary): array
    {
        $family = $beneficiary->family;

        $entriesByWarehouse = StockEntry::where('master_item_id', $item->id)
            ->where('status', 'available')
            ->with('warehouse')
            ->get()
            ->groupBy('warehouse_id');

        $totalStock = 0;
        $warehouses = [];

        foreach ($entriesByWarehouse as $group) {
            $warehouse = $group->first()->warehouse;
            $quantity = (int) $group->sum(fn (StockEntry $entry) => $entry->availableQuantity());

            if ($quantity <= 0 || ! $warehouse instanceof Warehouse) {
                continue;
            }

            $totalStock += $quantity;

            $distanceKm = ($family->latitude !== null && $family->longitude !== null && $warehouse->latitude !== null && $warehouse->longitude !== null)
                ? round(HaversineDistance::kilometers((float) $family->latitude, (float) $family->longitude, (float) $warehouse->latitude, (float) $warehouse->longitude), 1)
                : null;

            $warehouses[] = [
                'warehouse_id' => $warehouse->id,
                'name' => $warehouse->name,
                'quantity' => $quantity,
                'distance_km' => $distanceKm,
            ];
        }

        return ['total_stock' => $totalStock, 'warehouses' => $warehouses];
    }
}
