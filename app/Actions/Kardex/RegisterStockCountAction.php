<?php

declare(strict_types=1);

namespace App\Actions\Kardex;

use App\Enums\StockExitReason;
use App\Models\StockCount;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Conteo físico de un lote (cycle counting): compara lo que dice el sistema
 * contra lo que el operador contó en la bodega y, si hay diferencia, genera
 * el movimiento que la corrige — un StockExit de ajuste si sobra menos de lo
 * esperado, o un StockEntry nuevo si sobra más. Si coinciden, solo deja
 * constancia del conteo sin mover inventario.
 */
class RegisterStockCountAction
{
    /**
     * @param  array{stock_entry_id: int, counted_quantity: int, notes?: string|null}  $payload
     */
    public function handle(array $payload, User $operator): StockCount
    {
        return DB::transaction(function () use ($payload, $operator) {
            $entry = StockEntry::lockForUpdate()->findOrFail($payload['stock_entry_id']);
            $warehouse = $entry->warehouse;

            if ($operator->cannot('manageStock', $warehouse)) {
                throw new AuthorizationException('No tienes esta bodega asignada.');
            }

            $systemQuantity = $entry->availableQuantity();
            $countedQuantity = $payload['counted_quantity'];
            $difference = $countedQuantity - $systemQuantity;
            $notes = $payload['notes'] ?? null;

            $count = StockCount::create([
                'stock_entry_id' => $entry->id,
                'warehouse_id' => $entry->warehouse_id,
                'counted_by_user_id' => $operator->id,
                'system_quantity' => $systemQuantity,
                'counted_quantity' => $countedQuantity,
                'difference' => $difference,
                'notes' => $notes,
            ]);

            if ($difference < 0) {
                StockExit::create([
                    'stock_entry_id' => $entry->id,
                    'warehouse_id' => $entry->warehouse_id,
                    'released_by_user_id' => $operator->id,
                    'exit_reason' => StockExitReason::InventoryAdjustment->value,
                    'quantity_released' => abs($difference),
                    'notes' => $this->adjustmentNote($count, $notes),
                    'release_date' => now(),
                ]);
            } elseif ($difference > 0) {
                StockEntry::create([
                    'master_item_id' => $entry->master_item_id,
                    'warehouse_id' => $entry->warehouse_id,
                    'registered_by_user_id' => $operator->id,
                    'confirmed_by_user_id' => $operator->id,
                    'quantity' => $difference,
                    'lot_number' => $entry->lot_number,
                    'expiry_date' => $entry->expiry_date,
                    'received_date' => now()->toDateString(),
                    'status' => $entry->status,
                    'notes' => $this->adjustmentNote($count, $notes),
                    'adjustment_stock_count_id' => $count->id,
                ]);
            }

            return $count;
        });
    }

    private function adjustmentNote(StockCount $count, ?string $notes): string
    {
        $base = "Ajuste por conteo físico #{$count->id}";

        return $notes ? "{$base}: {$notes}" : $base;
    }
}
