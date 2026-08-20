<?php

declare(strict_types=1);

namespace App\Actions\Kardex;

use App\Enums\StockEntryStatus;
use App\Models\StockEntry;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Auth\Access\AuthorizationException;

class RegisterStockEntryAction
{
    /**
     * Registra una entrada de stock. El operador la ingresa al momento de recibirla
     * físicamente en la bodega, así que queda disponible de inmediato (sin un paso
     * adicional de "confirmar llegada" — ese flujo aplica solo si en el futuro se
     * permite pre-registrar entradas remotas antes de que lleguen).
     *
     * @param  array{client_uuid?: string|null, master_item_id: int, warehouse_id: int, quantity: int, lot_number?: string|null, expiry_date?: string|null, notes?: string|null}  $payload
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

        $warehouse = Warehouse::findOrFail($payload['warehouse_id']);

        if ($operator->cannot('manageStock', $warehouse)) {
            throw new AuthorizationException('No tienes esta bodega asignada.');
        }

        return StockEntry::create([
            ...$payload,
            'registered_by_user_id' => $operator->id,
            'confirmed_by_user_id' => $operator->id,
            'status' => StockEntryStatus::Available,
            'received_date' => now()->toDateString(),
        ]);
    }
}
