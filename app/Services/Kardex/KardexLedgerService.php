<?php

declare(strict_types=1);

namespace App\Services\Kardex;

use App\Enums\StockExitReason;
use App\Models\MasterItem;
use App\Models\StockCount;
use App\Models\StockEntry;
use App\Models\StockExit;
use Illuminate\Support\Carbon;

/**
 * Ficha Kardex: historial cronológico de movimientos de un ítem con saldo
 * corriente después de cada uno — el sentido original de la palabra "Kardex"
 * (tarjeta de control de existencias), que hasta ahora la app no mostraba
 * (solo el estado actual). Combina entradas, salidas y conteos físicos sin
 * diferencia (que no mueven inventario, pero sí dejan constancia de que se
 * verificó el saldo).
 */
class KardexLedgerService
{
    /**
     * @param  array<int, int>|int|null  $warehouseIds
     * @return list<array{date: Carbon, label: string, warehouse_name: string, lot_number: string|null, quantity_delta: int, balance: int}>
     */
    public function forItem(MasterItem $item, array|int|null $warehouseIds = null): array
    {
        $entries = StockEntry::query()
            ->where('master_item_id', $item->id)
            ->when($warehouseIds, fn ($query) => $query->whereIn('warehouse_id', (array) $warehouseIds))
            ->with('warehouse')
            ->get();

        $entryIds = $entries->pluck('id');

        $exits = StockExit::query()
            ->whereIn('stock_entry_id', $entryIds)
            ->with('warehouse')
            ->get();

        $verifiedCounts = StockCount::query()
            ->whereIn('stock_entry_id', $entryIds)
            ->where('difference', 0)
            ->with('warehouse')
            ->get();

        $entriesById = $entries->keyBy('id');

        /** @var list<array{date: Carbon, label: string, warehouse_name: string, lot_number: string|null, quantity_delta: int}> $movements */
        $movements = [];

        foreach ($entries as $entry) {
            $movements[] = [
                'date' => Carbon::parse($entry->created_at),
                'label' => $this->entryLabel($entry),
                'warehouse_name' => $entry->warehouse->name,
                'lot_number' => $entry->lot_number,
                'quantity_delta' => $entry->quantity,
            ];
        }

        foreach ($exits as $exit) {
            $movements[] = [
                'date' => Carbon::parse($exit->release_date),
                'label' => $this->exitLabel($exit),
                'warehouse_name' => $exit->warehouse->name,
                'lot_number' => $entriesById->get($exit->stock_entry_id)?->lot_number,
                'quantity_delta' => -$exit->quantity_released,
            ];
        }

        foreach ($verifiedCounts as $count) {
            $movements[] = [
                'date' => Carbon::parse($count->created_at),
                'label' => 'Conteo físico (sin diferencia)',
                'warehouse_name' => $count->warehouse->name,
                'lot_number' => $entriesById->get($count->stock_entry_id)?->lot_number,
                'quantity_delta' => 0,
            ];
        }

        usort($movements, fn (array $a, array $b) => $a['date'] <=> $b['date']);

        $balance = 0;

        /** @var list<array{date: Carbon, label: string, warehouse_name: string, lot_number: string|null, quantity_delta: int, balance: int}> $ledger */
        $ledger = [];

        foreach ($movements as $row) {
            $balance += $row['quantity_delta'];

            $ledger[] = [
                'date' => $row['date'],
                'label' => $row['label'],
                'warehouse_name' => $row['warehouse_name'],
                'lot_number' => $row['lot_number'],
                'quantity_delta' => $row['quantity_delta'],
                'balance' => $balance,
            ];
        }

        return $ledger;
    }

    private function entryLabel(StockEntry $entry): string
    {
        return match (true) {
            $entry->transferred_from_stock_entry_id !== null => 'Traslado (entrada)',
            $entry->adjustment_stock_count_id !== null => 'Ajuste por conteo (+)',
            default => 'Entrada',
        };
    }

    private function exitLabel(StockExit $exit): string
    {
        return match ($exit->exit_reason) {
            StockExitReason::Transfer => 'Traslado (salida)',
            StockExitReason::InventoryAdjustment => 'Ajuste por conteo (−)',
            StockExitReason::Loss, StockExitReason::Damage, StockExitReason::ExpiredDiscard => 'Baja — '.$exit->exit_reason->label(),
            default => 'Salida — '.$exit->exit_reason->label(),
        };
    }
}
