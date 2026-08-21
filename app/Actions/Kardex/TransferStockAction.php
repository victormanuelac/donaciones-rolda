<?php

declare(strict_types=1);

namespace App\Actions\Kardex;

use App\Enums\StockEntryStatus;
use App\Enums\StockExitReason;
use App\Exceptions\ExpiredStockException;
use App\Exceptions\InsufficientStockException;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransferStockAction
{
    /**
     * Traslada stock de una bodega a otra: registra una salida (motivo `transfer`)
     * en el lote de origen y crea un lote nuevo en la bodega destino, con el mismo
     * lote/vencimiento, enlazado vía transferred_from_stock_entry_id para trazabilidad.
     *
     * @param  array{client_uuid?: string|null, stock_entry_id: int, source_warehouse_id: int, destination_warehouse_id: int, quantity: int, notes?: string|null}  $payload
     */
    public function handle(array $payload, User $operator): StockEntry
    {
        $clientUuid = $payload['client_uuid'] ?? null;

        if ($clientUuid !== null) {
            $existing = StockEntry::where('client_uuid', $clientUuid)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($payload, $operator, $clientUuid) {
            if ($payload['source_warehouse_id'] === $payload['destination_warehouse_id']) {
                throw new InvalidArgumentException('El origen y el destino deben ser bodegas distintas.');
            }

            $sourceWarehouse = Warehouse::findOrFail($payload['source_warehouse_id']);
            $destinationWarehouse = Warehouse::findOrFail($payload['destination_warehouse_id']);

            if ($operator->cannot('manageStock', $sourceWarehouse) || $operator->cannot('manageStock', $destinationWarehouse)) {
                throw new AuthorizationException('No tienes acceso a alguna de las dos bodegas.');
            }

            $sourceEntry = StockEntry::lockForUpdate()->findOrFail($payload['stock_entry_id']);

            if ($sourceEntry->expiry_date !== null && $sourceEntry->expiry_date->isPast()) {
                throw new ExpiredStockException;
            }

            $available = $sourceEntry->availableQuantity();

            if ($payload['quantity'] > $available) {
                throw new InsufficientStockException($available, $payload['quantity']);
            }

            StockExit::create([
                'stock_entry_id' => $sourceEntry->id,
                'warehouse_id' => $sourceWarehouse->id,
                'released_by_user_id' => $operator->id,
                'exit_reason' => StockExitReason::Transfer,
                'quantity_released' => $payload['quantity'],
                'release_date' => now(),
                'destination_description' => "Traslado a {$destinationWarehouse->name}",
                'notes' => $payload['notes'] ?? null,
            ]);

            return StockEntry::create([
                'master_item_id' => $sourceEntry->master_item_id,
                'warehouse_id' => $destinationWarehouse->id,
                'registered_by_user_id' => $operator->id,
                'confirmed_by_user_id' => $operator->id,
                'quantity' => $payload['quantity'],
                'lot_number' => $sourceEntry->lot_number,
                'expiry_date' => $sourceEntry->expiry_date,
                'received_date' => now()->toDateString(),
                'status' => StockEntryStatus::Available,
                'notes' => "Trasladado desde {$sourceWarehouse->name}",
                'client_uuid' => $clientUuid,
                'transferred_from_stock_entry_id' => $sourceEntry->id,
            ]);
        });
    }
}
