<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;
use Livewire\Livewire;

function projectionWarehouse(): Warehouse
{
    return Warehouse::create([
        'name' => 'Bodega de prueba',
        'address' => 'Dirección de prueba',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
    ]);
}

function projectionItem(string $name = 'Frijol'): MasterItem
{
    $category = Category::create(['name' => 'Categoría '.uniqid()]);

    return MasterItem::create([
        'category_id' => $category->id,
        'name' => $name,
        'unit_of_measure' => 'kg',
    ]);
}

test('el kardex muestra los items proyectados a agotarse en 21 dias o menos', function () {
    $warehouse = projectionWarehouse();
    $item = projectionItem();
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id]);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
    ]);

    // available=5, consumido=15 en 30 días -> 0.5/día -> 10 días de cobertura.
    StockExit::create([
        'stock_entry_id' => $entry->id,
        'warehouse_id' => $warehouse->id,
        'released_by_user_id' => $operator->id,
        'exit_reason' => 'emergency_assistance',
        'quantity_released' => 15,
        'release_date' => now()->subDays(10),
    ]);

    Livewire::actingAs($operator)
        ->test('pages::kardex.index')
        ->assertSee('Proyección de agotamiento')
        ->assertSee('Frijol');
});

test('un item sin historial de salidas reciente no aparece en la proyeccion', function () {
    $warehouse = projectionWarehouse();
    $item = projectionItem('Lentejas');
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id]);

    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
    ]);

    Livewire::actingAs($operator)
        ->test('pages::kardex.index')
        ->assertDontSee('Proyección de agotamiento');
});
