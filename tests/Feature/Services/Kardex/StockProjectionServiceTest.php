<?php

use App\Models\Category;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Kardex\StockProjectionService;

beforeEach(function () {
    $this->service = new StockProjectionService;

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

test('un item sin existencias disponibles ya está agotado', function () {
    StockEntry::create([
        'master_item_id' => $this->item->id,
        'warehouse_id' => $this->warehouse->id,
        'registered_by_user_id' => $this->operator->id,
        'quantity' => 0,
    ]);

    expect($this->service->daysRemaining($this->item))->toBe(0.0);
});

test('sin historial de salidas no se puede proyectar', function () {
    StockEntry::create([
        'master_item_id' => $this->item->id,
        'warehouse_id' => $this->warehouse->id,
        'registered_by_user_id' => $this->operator->id,
        'quantity' => 20,
    ]);

    expect($this->service->daysRemaining($this->item))->toBeNull();
});

test('proyecta los dias restantes segun el ritmo de consumo reciente', function () {
    $entry = StockEntry::create([
        'master_item_id' => $this->item->id,
        'warehouse_id' => $this->warehouse->id,
        'registered_by_user_id' => $this->operator->id,
        'quantity' => 20,
    ]);

    // 15 unidades consumidas en 30 días -> 0.5/día -> 10 días de cobertura sobre 5 disponibles.
    StockExit::create([
        'stock_entry_id' => $entry->id,
        'warehouse_id' => $this->warehouse->id,
        'released_by_user_id' => $this->operator->id,
        'exit_reason' => 'emergency_assistance',
        'quantity_released' => 15,
        'release_date' => now()->subDays(10),
    ]);

    expect($this->service->daysRemaining($this->item))->toBe(10.0);
});

test('las salidas fuera de la ventana de analisis no cuentan', function () {
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
        'exit_reason' => 'emergency_assistance',
        'quantity_released' => 10,
        'release_date' => now()->subDays(45),
    ]);

    expect($this->service->daysRemaining($this->item, lookbackDays: 30))->toBeNull();
});

test('las bajas por perdida, daño o vencimiento no cuentan como consumo', function () {
    $entry = StockEntry::create([
        'master_item_id' => $this->item->id,
        'warehouse_id' => $this->warehouse->id,
        'registered_by_user_id' => $this->operator->id,
        'quantity' => 20,
    ]);

    foreach (['loss', 'damage', 'expired_discard'] as $reason) {
        StockExit::create([
            'stock_entry_id' => $entry->id,
            'warehouse_id' => $this->warehouse->id,
            'released_by_user_id' => $this->operator->id,
            'exit_reason' => $reason,
            'quantity_released' => 2,
            'release_date' => now()->subDays(5),
        ]);
    }

    expect($this->service->daysRemaining($this->item))->toBeNull();
});

test('los traslados a otra bodega no cuentan como consumo', function () {
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
        'exit_reason' => 'transfer',
        'quantity_released' => 5,
        'release_date' => now()->subDays(5),
    ]);

    expect($this->service->daysRemaining($this->item))->toBeNull();
});
