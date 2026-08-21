<?php

declare(strict_types=1);

namespace App\Actions\Kardex;

use App\Enums\StockExitReason;
use App\Exceptions\ExpiredStockException;
use App\Exceptions\InsufficientStockException;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class RegisterStockExitAction
{
    /**
     * @param  array{client_uuid?: string|null, stock_entry_id: int, warehouse_id: int, family_id?: int|null, quantity_released: int, exit_reason: string, received_by_name?: string|null, destination_zone_id?: int|null, destination_description?: string|null, notes?: string|null}  $payload
     */
    public function handle(array $payload, User $operator): StockExit
    {
        $clientUuid = $payload['client_uuid'] ?? null;

        if ($clientUuid !== null) {
            $existing = StockExit::where('client_uuid', $clientUuid)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($payload, $operator) {
            $warehouse = Warehouse::findOrFail($payload['warehouse_id']);

            if ($operator->cannot('manageStock', $warehouse)) {
                throw new AuthorizationException('No tienes esta bodega asignada.');
            }

            $entry = StockEntry::lockForUpdate()->findOrFail($payload['stock_entry_id']);

            $reason = StockExitReason::from($payload['exit_reason']);

            if ($entry->expiry_date !== null && $entry->expiry_date->isPast() && ! $reason->isWriteOff()) {
                throw new ExpiredStockException;
            }

            $available = $entry->availableQuantity();

            if ($payload['quantity_released'] > $available) {
                throw new InsufficientStockException($available, $payload['quantity_released']);
            }

            return StockExit::create([
                ...$payload,
                'released_by_user_id' => $operator->id,
                'release_date' => now(),
            ]);
        });
    }
}
