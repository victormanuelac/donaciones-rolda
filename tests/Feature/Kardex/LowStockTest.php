<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;

test('el kardex avisa cuando un ítem cae por debajo del punto de reorden', function () {
    $warehouse = Warehouse::create([
        'name' => 'Bodega',
        'address' => 'Dirección',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
    ]);
    $category = Category::create(['name' => 'Categoría '.uniqid()]);
    $item = MasterItem::create([
        'category_id' => $category->id,
        'name' => 'Ítem con reorden',
        'unit_of_measure' => 'unidades',
        'reorder_point' => 10,
    ]);
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id]);

    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 5,
    ]);

    $response = $this->actingAs($operator)->get('/kardex');

    $response->assertOk();
    $response->assertSee('Ítems bajo el punto de reorden');
    $response->assertSee('Ítem con reorden');
});

test('el kardex no avisa si el disponible supera el punto de reorden', function () {
    $warehouse = Warehouse::create([
        'name' => 'Bodega',
        'address' => 'Dirección',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
    ]);
    $category = Category::create(['name' => 'Categoría '.uniqid()]);
    $item = MasterItem::create([
        'category_id' => $category->id,
        'name' => 'Ítem sobrado',
        'unit_of_measure' => 'unidades',
        'reorder_point' => 10,
    ]);
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id]);

    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 50,
    ]);

    $response = $this->actingAs($operator)->get('/kardex');

    $response->assertOk();
    $response->assertDontSee('Ítems bajo el punto de reorden');
});
