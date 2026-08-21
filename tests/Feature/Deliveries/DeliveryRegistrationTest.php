<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Family;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;
use Livewire\Livewire;

function deliveryWarehouse(string $name = 'Bodega de prueba'): Warehouse
{
    return Warehouse::create([
        'name' => $name,
        'address' => 'Dirección de prueba',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
    ]);
}

function deliveryItem(string $name = 'Arroz'): MasterItem
{
    $category = Category::create(['name' => 'Categoría '.uniqid()]);

    return MasterItem::create([
        'category_id' => $category->id,
        'name' => $name,
        'unit_of_measure' => 'kg',
    ]);
}

function deliveryFamily(string $headFullName = 'Yolanda Pérez'): Family
{
    return Family::create([
        'zone_type' => 'urbana',
        'address' => 'Calle 1 #2-3',
        'head_full_name' => $headFullName,
        'housing_damage_level' => 'sin_dano',
        'household_size' => 3,
    ]);
}

function deliveryOperator(Warehouse $warehouse): User
{
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id]);

    return $operator;
}

test('roles sin permiso no pueden ver el registro de entregas', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('deliveries.register'))->assertForbidden();
})->with([UserRole::Donor, UserRole::Doctor, UserRole::Municipal]);

test('la busqueda de hogar solo arranca desde 3 letras', function () {
    $warehouse = deliveryWarehouse();
    $operator = deliveryOperator($warehouse);
    deliveryFamily('Yolanda Pérez');

    Livewire::actingAs($operator)
        ->test('pages::deliveries.register')
        ->set('familySearch', 'Yo')
        ->assertDontSee('Yolanda Pérez')
        ->set('familySearch', 'Yol')
        ->assertSee('Yolanda Pérez');
});

test('un operador puede registrar una entrega a un hogar beneficiario', function () {
    $warehouse = deliveryWarehouse();
    $item = deliveryItem();
    $operator = deliveryOperator($warehouse);
    $family = deliveryFamily();

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
    ]);

    Livewire::actingAs($operator)
        ->test('pages::deliveries.register')
        ->call('selectFamily', $family->id)
        ->set('stock_entry_id', $entry->id)
        ->set('quantity_released', 5)
        ->set('exit_reason', 'emergency_assistance')
        ->call('register')
        ->assertHasNoErrors();

    $exit = StockExit::where('family_id', $family->id)->first();

    expect($exit)->not->toBeNull()
        ->and($exit->quantity_released)->toBe(5)
        ->and($exit->stock_entry_id)->toBe($entry->id)
        ->and($entry->fresh()->availableQuantity())->toBe(15);
});

test('no se puede registrar una entrega sin seleccionar un hogar', function () {
    $warehouse = deliveryWarehouse();
    $item = deliveryItem();
    $operator = deliveryOperator($warehouse);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
    ]);

    Livewire::actingAs($operator)
        ->test('pages::deliveries.register')
        ->set('stock_entry_id', $entry->id)
        ->set('quantity_released', 5)
        ->call('register')
        ->assertHasErrors(['family_id']);

    expect(StockExit::count())->toBe(0);
});

test('una entrega no puede superar la cantidad disponible', function () {
    $warehouse = deliveryWarehouse();
    $item = deliveryItem();
    $operator = deliveryOperator($warehouse);
    $family = deliveryFamily();

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 5,
    ]);

    Livewire::actingAs($operator)
        ->test('pages::deliveries.register')
        ->call('selectFamily', $family->id)
        ->set('stock_entry_id', $entry->id)
        ->set('quantity_released', 20)
        ->call('register')
        ->assertHasErrors(['stock_entry_id']);

    expect(StockExit::count())->toBe(0);
});

test('muestra las entregas recientes del hogar seleccionado', function () {
    $warehouse = deliveryWarehouse();
    $item = deliveryItem();
    $operator = deliveryOperator($warehouse);
    $family = deliveryFamily();

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
    ]);

    StockExit::create([
        'stock_entry_id' => $entry->id,
        'warehouse_id' => $warehouse->id,
        'family_id' => $family->id,
        'released_by_user_id' => $operator->id,
        'exit_reason' => 'emergency_assistance',
        'quantity_released' => 3,
        'release_date' => now()->subDays(2),
    ]);

    Livewire::actingAs($operator)
        ->test('pages::deliveries.register')
        ->call('selectFamily', $family->id)
        ->assertSee('Entregas recientes a este hogar')
        ->assertSee($item->name);
});

test('un operador solo ve lotes de sus bodegas asignadas', function () {
    $ownWarehouse = deliveryWarehouse('Propia');
    $otherWarehouse = deliveryWarehouse('Ajena');
    $item = deliveryItem();
    $operator = deliveryOperator($ownWarehouse);
    $family = deliveryFamily();

    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $otherWarehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
    ]);

    Livewire::actingAs($operator)
        ->test('pages::deliveries.register')
        ->call('selectFamily', $family->id)
        ->assertSee('No hay lotes con existencias disponibles en tus bodegas asignadas.');
});
