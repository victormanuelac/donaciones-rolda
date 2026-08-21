<?php

use App\Enums\StockExitReason;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;
use App\Services\Kardex\KardexAlertsService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Guarda contra la regresión del N+1 de docs/17-Auditoria-Frontend.md (A-2):
 * `/kardex` disparaba 73 consultas con 16 lotes y 1.346 con 616. El número de
 * consultas debe depender de la *forma* de la pantalla, no del volumen de datos.
 */
function perfWarehouse(string $name): Warehouse
{
    return Warehouse::create([
        'name' => $name,
        'address' => 'Dirección',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
        'max_capacity_units' => 1000,
    ]);
}

/**
 * @return array{0: User, 1: Warehouse, 2: array<int, MasterItem>}
 */
function perfScenario(int $items, int $lotsPerItem): array
{
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    $warehouse = perfWarehouse('Bodega de rendimiento');
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id, 'is_active' => true]);

    $category = Category::create(['name' => 'Categoría '.uniqid()]);
    $created = [];

    for ($i = 0; $i < $items; $i++) {
        $item = MasterItem::create([
            'category_id' => $category->id,
            'name' => "Ítem de rendimiento {$i}",
            'unit_of_measure' => 'uds',
            'reorder_point' => 5,
        ]);

        for ($lot = 0; $lot < $lotsPerItem; $lot++) {
            StockEntry::create([
                'master_item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
                'registered_by_user_id' => $operator->id,
                'quantity' => 50,
                'expiry_date' => now()->addDays(120),
                'received_date' => now(),
                'status' => 'available',
            ]);
        }

        $created[] = $item;
    }

    return [$operator, $warehouse, $created];
}

test('el numero de consultas de /kardex no crece con el volumen de datos', function () {
    [$smallOperator] = perfScenario(2, 2);

    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::actingAs($smallOperator)->test('pages::kardex.index');
    $withFewLots = count(DB::getQueryLog());
    DB::disableQueryLog();

    [$bigOperator] = perfScenario(25, 6);

    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::actingAs($bigOperator)->test('pages::kardex.index');
    $withManyLots = count(DB::getQueryLog());
    DB::disableQueryLog();

    // 4 lotes vs. 150 lotes: si volviera el N+1, la diferencia sería de cientos.
    expect($withManyLots)->toBeLessThanOrEqual($withFewLots + 3)
        ->and($withManyLots)->toBeLessThan(30);
});

test('los agregados coinciden con el calculo lote por lote de los modelos', function () {
    [$operator, $warehouse, $items] = perfScenario(3, 3);

    // Una salida parcial sobre un lote, para que los totales no sean triviales.
    $entry = StockEntry::where('master_item_id', $items[0]->id)->firstOrFail();
    StockExit::create([
        'stock_entry_id' => $entry->id,
        'warehouse_id' => $warehouse->id,
        'released_by_user_id' => $operator->id,
        'exit_reason' => StockExitReason::EmergencyAssistance->value,
        'quantity_released' => 20,
        'release_date' => now(),
    ]);

    $service = app(KardexAlertsService::class);
    $available = $service->availableByItem([$warehouse->id]);
    $occupied = $service->occupiedByWarehouse([$warehouse->id]);

    foreach ($items as $item) {
        expect($available[$item->id])->toBe($item->totalAvailableQuantity([$warehouse->id]));
    }

    expect($occupied[$warehouse->id])->toBe($warehouse->occupiedUnits())
        ->and($available[$items[0]->id])->toBe(130); // 3 lotes de 50 menos 20 despachadas
});

test('el consumo agregado excluye traslados y bajas, igual que la proyeccion', function () {
    [$operator, $warehouse, $items] = perfScenario(1, 2);

    $entries = StockEntry::where('master_item_id', $items[0]->id)->get();

    // Demanda real: cuenta.
    StockExit::create([
        'stock_entry_id' => $entries[0]->id,
        'warehouse_id' => $warehouse->id,
        'released_by_user_id' => $operator->id,
        'exit_reason' => StockExitReason::Donation->value,
        'quantity_released' => 10,
        'release_date' => now(),
    ]);

    // Traslado y pérdida: no cuentan como consumo.
    foreach ([StockExitReason::Transfer, StockExitReason::Loss] as $reason) {
        StockExit::create([
            'stock_entry_id' => $entries[1]->id,
            'warehouse_id' => $warehouse->id,
            'released_by_user_id' => $operator->id,
            'exit_reason' => $reason->value,
            'quantity_released' => 5,
            'release_date' => now(),
        ]);
    }

    $consumed = app(KardexAlertsService::class)->consumedByItem([$warehouse->id], 30);

    expect($consumed[$items[0]->id])->toBe(10);
});

test('el scope withAvailableQuantity no consulta de nuevo por cada lote', function () {
    [, $warehouse] = perfScenario(4, 4);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $total = StockEntry::query()
        ->where('warehouse_id', $warehouse->id)
        ->withAvailableQuantity()
        ->get()
        ->sum(fn (StockEntry $entry) => $entry->availableQuantity());

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Una sola consulta para los 16 lotes, incluidos los que no tienen salidas
    // (withSum devuelve NULL ahí, y eso no debe provocar una consulta extra).
    expect($queries)->toBe(1)
        ->and($total)->toBe(16 * 50);
});
