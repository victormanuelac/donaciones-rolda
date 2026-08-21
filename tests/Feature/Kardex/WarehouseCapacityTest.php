<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;
use Livewire\Livewire;

test('una bodega sin capacidad maxima nunca esta sobrecupada', function () {
    $warehouse = Warehouse::create([
        'name' => 'Sin límite',
        'address' => 'Dirección',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
    ]);

    expect($warehouse->isOverCapacity())->toBeFalse();
});

test('una bodega por encima de su capacidad maxima queda marcada', function () {
    $warehouse = Warehouse::create([
        'name' => 'Con límite',
        'address' => 'Dirección',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
        'max_capacity_units' => 50,
    ]);

    $category = Category::create(['name' => 'Categoría '.uniqid()]);
    $item = MasterItem::create(['category_id' => $category->id, 'name' => 'Arroz', 'unit_of_measure' => 'kg']);
    $operator = User::factory()->create();

    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 60,
    ]);

    expect($warehouse->occupiedUnits())->toBe(60)
        ->and($warehouse->isOverCapacity())->toBeTrue();
});

test('los lotes retirados no cuentan para la ocupacion de la bodega', function () {
    $warehouse = Warehouse::create([
        'name' => 'Con lote retirado',
        'address' => 'Dirección',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
        'max_capacity_units' => 10,
    ]);

    $category = Category::create(['name' => 'Categoría '.uniqid()]);
    $item = MasterItem::create(['category_id' => $category->id, 'name' => 'Arroz', 'unit_of_measure' => 'kg']);
    $operator = User::factory()->create();

    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 100,
        'status' => 'withdrawn',
    ]);

    expect($warehouse->occupiedUnits())->toBe(0)
        ->and($warehouse->isOverCapacity())->toBeFalse();
});

test('el kardex avisa cuando una bodega asignada supera su capacidad', function () {
    $warehouse = Warehouse::create([
        'name' => 'Bodega llena',
        'address' => 'Dirección',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
        'max_capacity_units' => 10,
    ]);

    $category = Category::create(['name' => 'Categoría '.uniqid()]);
    $item = MasterItem::create(['category_id' => $category->id, 'name' => 'Arroz', 'unit_of_measure' => 'kg']);
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id]);

    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 15,
    ]);

    Livewire::actingAs($operator)
        ->test('pages::kardex.index')
        ->assertSee('Bodegas por encima de su capacidad máxima')
        ->assertSee('Bodega llena');
});
