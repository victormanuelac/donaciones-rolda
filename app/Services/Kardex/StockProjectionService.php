<?php

declare(strict_types=1);

namespace App\Services\Kardex;

use App\Enums\StockExitReason;
use App\Models\MasterItem;
use App\Models\StockExit;
use Illuminate\Support\Carbon;

/**
 * Proyección de agotamiento de stock: estima cuántos días quedan de
 * existencias de un ítem según su ritmo de consumo reciente ("burn rate" /
 * días de cobertura, estándar de gestión de inventario), usando solo las
 * salidas que representan demanda real — se excluyen traslados (no bajan el
 * total del sistema) y bajas por pérdida/daño/vencimiento (no reflejan
 * consumo). Da contexto para priorizar reposición y para decidir cantidades
 * al registrar una entrega (Módulo 6).
 */
class StockProjectionService
{
    private const DEFAULT_LOOKBACK_DAYS = 30;

    /**
     * @param  array<int, int>|int|null  $warehouseIds
     * @return float|null Días estimados de cobertura, 0.0 si ya está agotado,
     *                    o null si no hay suficiente historial de salidas para proyectar.
     */
    public function daysRemaining(MasterItem $item, array|int|null $warehouseIds = null, int $lookbackDays = self::DEFAULT_LOOKBACK_DAYS): ?float
    {
        $available = $item->totalAvailableQuantity($warehouseIds);

        if ($available <= 0) {
            return 0.0;
        }

        $dailyRate = $this->dailyConsumptionRate($item, $warehouseIds, $lookbackDays);

        if ($dailyRate === null || $dailyRate <= 0.0) {
            return null;
        }

        return round($available / $dailyRate, 1);
    }

    /**
     * @param  array<int, int>|int|null  $warehouseIds
     */
    public function dailyConsumptionRate(MasterItem $item, array|int|null $warehouseIds = null, int $lookbackDays = self::DEFAULT_LOOKBACK_DAYS): ?float
    {
        $consumed = (int) StockExit::query()
            ->whereHas('stockEntry', fn ($query) => $query->where('master_item_id', $item->id))
            ->when($warehouseIds, fn ($query) => $query->whereIn('warehouse_id', (array) $warehouseIds))
            ->whereIn('exit_reason', $this->demandReasons())
            ->where('release_date', '>=', Carbon::now()->subDays($lookbackDays))
            ->sum('quantity_released');

        if ($consumed <= 0) {
            return null;
        }

        return $consumed / $lookbackDays;
    }

    /**
     * @return array<int, string>
     */
    private function demandReasons(): array
    {
        return [
            StockExitReason::Donation->value,
            StockExitReason::SubsidizedSale->value,
            StockExitReason::EmergencyAssistance->value,
            StockExitReason::Other->value,
        ];
    }
}
