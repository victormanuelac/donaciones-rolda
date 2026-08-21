<?php

use App\Models\Category;
use App\Models\MasterItem;
use App\Models\StockCount;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Kardex\KardexLedgerService;

beforeEach(function () {
    $this->service = new KardexLedgerService;

    $this->warehouse = Warehouse::create([
        'name' => 'Bodega de prueba',
        'address' => 'Dirección de prueba',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
    ]);

    $category = Category::create(['name' => 'Categoría '.uniqid()]);

    $this->item = MasterItem::create([
        'category_id' => $category->id,
        'name' => 'Arroz',
        'unit_of_measure' => 'kg',
    ]);

    $this->operator = User::factory()->create();
});

test('muestra la entrada inicial con su saldo', function () {
    StockEntry::create([
        'master_item_id' => $this->item->id,
        'warehouse_id' => $this->warehouse->id,
        'registered_by_user_id' => $this->operator->id,
        'quantity' => 20,
    ]);

    $ledger = $this->service->forItem($this->item);

    expect($ledger)->toHaveCount(1)
        ->and($ledger[0]['label'])->toBe('Entrada')
        ->and($ledger[0]['quantity_delta'])->toBe(20)
        ->and($ledger[0]['balance'])->toBe(20);
});

test('el saldo se acumula en orden cronologico entre entradas y salidas', function () {
    // stock_entries.created_at no es asignable en masa (lo pone Eloquent al
    // crear), así que la fecha se ajusta después con save() para poder
    // controlar el orden cronológico en la prueba.
    $entry = StockEntry::create([
        'master_item_id' => $this->item->id,
        'warehouse_id' => $this->warehouse->id,
        'registered_by_user_id' => $this->operator->id,
        'quantity' => 20,
    ]);
    $entry->forceFill(['created_at' => now()->subDays(5)])->save();

    StockExit::create([
        'stock_entry_id' => $entry->id,
        'warehouse_id' => $this->warehouse->id,
        'released_by_user_id' => $this->operator->id,
        'exit_reason' => 'donation',
        'quantity_released' => 8,
        'release_date' => now()->subDays(2),
    ]);

    $secondEntry = StockEntry::create([
        'master_item_id' => $this->item->id,
        'warehouse_id' => $this->warehouse->id,
        'registered_by_user_id' => $this->operator->id,
        'quantity' => 10,
    ]);
    $secondEntry->forceFill(['created_at' => now()->subDay()])->save();

    $ledger = $this->service->forItem($this->item);

    expect($ledger)->toHaveCount(3)
        ->and(array_column($ledger, 'balance'))->toBe([20, 12, 22]);
});

test('etiqueta las salidas de ajuste por conteo distinto de una salida normal', function () {
    $entry = StockEntry::create([
        'master_item_id' => $this->item->id,
        'warehouse_id' => $this->warehouse->id,
        'registered_by_user_id' => $this->operator->id,
        'quantity' => 20,
    ]);

    StockExit::create([
        'stock_entry_id' => $entry->id,
        'warehouse_id' => $this->warehouse->id,
        'released_by_user_id' => $this->operator->id,
        'exit_reason' => 'inventory_adjustment',
        'quantity_released' => 5,
        'release_date' => now(),
    ]);

    $ledger = $this->service->forItem($this->item);
    $adjustmentRow = collect($ledger)->firstWhere('quantity_delta', -5);

    expect($adjustmentRow['label'])->toBe('Ajuste por conteo (−)');
});

test('un conteo sin diferencia aparece en la ficha sin mover el saldo', function () {
    $entry = StockEntry::create([
        'master_item_id' => $this->item->id,
        'warehouse_id' => $this->warehouse->id,
        'registered_by_user_id' => $this->operator->id,
        'quantity' => 20,
    ]);

    StockCount::create([
        'stock_entry_id' => $entry->id,
        'warehouse_id' => $this->warehouse->id,
        'counted_by_user_id' => $this->operator->id,
        'system_quantity' => 20,
        'counted_quantity' => 20,
        'difference' => 0,
    ]);

    $ledger = $this->service->forItem($this->item);
    $verificationRow = collect($ledger)->firstWhere('label', 'Conteo físico (sin diferencia)');

    expect($ledger)->toHaveCount(2)
        ->and($verificationRow['quantity_delta'])->toBe(0)
        ->and($verificationRow['balance'])->toBe(20);
});

test('filtra el historial por bodega', function () {
    $otherWarehouse = Warehouse::create([
        'name' => 'Otra bodega',
        'address' => 'Dirección',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000001',
    ]);

    StockEntry::create([
        'master_item_id' => $this->item->id,
        'warehouse_id' => $this->warehouse->id,
        'registered_by_user_id' => $this->operator->id,
        'quantity' => 20,
    ]);

    StockEntry::create([
        'master_item_id' => $this->item->id,
        'warehouse_id' => $otherWarehouse->id,
        'registered_by_user_id' => $this->operator->id,
        'quantity' => 15,
    ]);

    $ledger = $this->service->forItem($this->item, $this->warehouse->id);

    expect($ledger)->toHaveCount(1)
        ->and($ledger[0]['warehouse_name'])->toBe('Bodega de prueba');
});
