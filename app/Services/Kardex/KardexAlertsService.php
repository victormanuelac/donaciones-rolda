<?php

declare(strict_types=1);

namespace App\Services\Kardex;

use App\Enums\StockExitReason;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agregados de inventario para los avisos del Kardex, calculados en **una sola
 * consulta cada uno** en vez de recorrer ítems o lotes en PHP.
 *
 * Antes, la pantalla `/kardex` resolvía estos mismos números llamando a
 * `availableQuantity()` lote por lote dentro de bucles anidados: 73 consultas
 * medidas con apenas 16 lotes sembrados, y se repetía entero en cada cambio de
 * filtro (docs/17-Auditoria-Frontend.md, hallazgo A-2).
 *
 * Deliberadamente sin caché: con los agregados ya resueltos, cachear 5 minutos
 * ahorraría poco y volvería obsoletos los avisos de stock justo después de
 * registrar una entrada o una salida, que es cuando más importan.
 */
class KardexAlertsService
{
    /**
     * Subconsulta de lo despachado por lote. Va como `leftJoinSub` y no como
     * `join` directo contra `stock_exits` porque un lote con varias salidas
     * multiplicaría las filas y rompería el `SUM(quantity)`.
     */
    private function releasedPerEntry(): Builder
    {
        return DB::table('stock_exits')
            ->select('stock_entry_id')
            ->selectRaw('SUM(quantity_released) AS released')
            ->groupBy('stock_entry_id');
    }

    /**
     * Unidades disponibles por ítem, solo en lotes vigentes.
     *
     * @param  array<int, int>  $warehouseIds
     * @return array<int, int> master_item_id => unidades disponibles
     */
    public function availableByItem(array $warehouseIds): array
    {
        if ($warehouseIds === []) {
            return [];
        }

        return DB::table('stock_entries')
            ->leftJoinSub($this->releasedPerEntry(), 'x', 'x.stock_entry_id', '=', 'stock_entries.id')
            ->where('stock_entries.status', 'available')
            ->whereIn('stock_entries.warehouse_id', $warehouseIds)
            ->groupBy('stock_entries.master_item_id')
            ->selectRaw('stock_entries.master_item_id AS key_id, SUM(stock_entries.quantity - COALESCE(x.released, 0)) AS total')
            ->get()
            ->mapWithKeys(fn (object $row) => [(int) $row->key_id => (int) $row->total])
            ->all();
    }

    /**
     * Unidades que ocupan espacio físico por bodega: disponibles y también
     * vencidas, que siguen ocupando lugar hasta que alguien las dé de baja.
     *
     * @param  array<int, int>  $warehouseIds
     * @return array<int, int> warehouse_id => unidades ocupadas
     */
    public function occupiedByWarehouse(array $warehouseIds): array
    {
        if ($warehouseIds === []) {
            return [];
        }

        return DB::table('stock_entries')
            ->leftJoinSub($this->releasedPerEntry(), 'x', 'x.stock_entry_id', '=', 'stock_entries.id')
            ->whereIn('stock_entries.status', ['available', 'expired'])
            ->whereIn('stock_entries.warehouse_id', $warehouseIds)
            ->groupBy('stock_entries.warehouse_id')
            ->selectRaw('stock_entries.warehouse_id AS key_id, SUM(stock_entries.quantity - COALESCE(x.released, 0)) AS total')
            ->get()
            ->mapWithKeys(fn (object $row) => [(int) $row->key_id => (int) $row->total])
            ->all();
    }

    /**
     * Unidades consumidas por ítem en la ventana indicada, contando solo las
     * salidas que representan demanda real — mismo criterio que
     * `StockProjectionService`: se excluyen traslados y bajas por pérdida,
     * daño o vencimiento.
     *
     * @param  array<int, int>  $warehouseIds
     * @return array<int, int> master_item_id => unidades consumidas
     */
    public function consumedByItem(array $warehouseIds, int $lookbackDays): array
    {
        if ($warehouseIds === []) {
            return [];
        }

        return DB::table('stock_exits')
            ->join('stock_entries', 'stock_entries.id', '=', 'stock_exits.stock_entry_id')
            ->whereIn('stock_exits.warehouse_id', $warehouseIds)
            ->whereIn('stock_exits.exit_reason', StockExitReason::demandValues())
            ->where('stock_exits.release_date', '>=', Carbon::now()->subDays($lookbackDays))
            ->groupBy('stock_entries.master_item_id')
            ->selectRaw('stock_entries.master_item_id AS key_id, SUM(stock_exits.quantity_released) AS total')
            ->get()
            ->mapWithKeys(fn (object $row) => [(int) $row->key_id => (int) $row->total])
            ->all();
    }
}
