<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Family;
use App\Models\GeographicZone;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;
use Livewire\Livewire;

function listingWarehouse(string $name = 'Bodega de prueba'): Warehouse
{
    return Warehouse::create([
        'name' => $name,
        'address' => 'Dirección de prueba',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
    ]);
}

function listingItem(): MasterItem
{
    $category = Category::create(['name' => 'Categoría '.uniqid()]);

    return MasterItem::create([
        'category_id' => $category->id,
        'name' => 'Arroz',
        'unit_of_measure' => 'kg',
    ]);
}

function listingFamily(string $headFullName, ?GeographicZone $zone = null): Family
{
    return Family::create([
        'zone_id' => $zone?->id,
        'zone_type' => 'urbana',
        'address' => 'Calle 1 #2-3',
        'head_full_name' => $headFullName,
        'housing_damage_level' => 'sin_dano',
        'household_size' => 3,
    ]);
}

test('roles sin permiso no pueden ver el listado de entregas', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('deliveries.index'))->assertForbidden();
})->with([UserRole::Donor, UserRole::Doctor, UserRole::Municipal]);

test('el listado muestra solo salidas con hogar beneficiario asociado', function () {
    $warehouse = listingWarehouse();
    $item = listingItem();
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id]);
    $family = listingFamily('Yolanda Pérez');

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
    ]);

    StockExit::create([
        'stock_entry_id' => $entry->id,
        'warehouse_id' => $warehouse->id,
        'released_by_user_id' => $operator->id,
        'exit_reason' => 'donation',
        'quantity_released' => 2,
        'received_by_name' => 'Comedor comunitario',
    ]);

    Livewire::actingAs($operator)
        ->test('pages::deliveries.index')
        ->assertSee('Yolanda Pérez')
        ->assertDontSee('Comedor comunitario');
});

test('el listado filtra por nombre del jefe de hogar', function () {
    $warehouse = listingWarehouse();
    $item = listingItem();
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id]);
    $perez = listingFamily('Yolanda Pérez');
    $gomez = listingFamily('Carlos Gómez');

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
    ]);

    foreach ([$perez, $gomez] as $family) {
        StockExit::create([
            'stock_entry_id' => $entry->id,
            'warehouse_id' => $warehouse->id,
            'family_id' => $family->id,
            'released_by_user_id' => $operator->id,
            'exit_reason' => 'emergency_assistance',
            'quantity_released' => 1,
        ]);
    }

    Livewire::actingAs($operator)
        ->test('pages::deliveries.index')
        ->set('search', 'Gómez')
        ->assertSee('Carlos Gómez')
        ->assertDontSee('Yolanda Pérez');
});

test('el listado filtra por zona geografica del hogar', function () {
    $warehouse = listingWarehouse();
    $item = listingItem();
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id]);

    $zoneA = GeographicZone::create(['zone_type' => 'barrio', 'name' => 'Barrio Centro']);
    $zoneB = GeographicZone::create(['zone_type' => 'barrio', 'name' => 'Barrio El Salado']);

    $inZone = listingFamily('Yolanda Pérez', $zoneA);
    $outsideZone = listingFamily('Carlos Gómez', $zoneB);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
    ]);

    foreach ([$inZone, $outsideZone] as $family) {
        StockExit::create([
            'stock_entry_id' => $entry->id,
            'warehouse_id' => $warehouse->id,
            'family_id' => $family->id,
            'released_by_user_id' => $operator->id,
            'exit_reason' => 'emergency_assistance',
            'quantity_released' => 1,
        ]);
    }

    Livewire::actingAs($operator)
        ->test('pages::deliveries.index')
        ->set('zoneFilter', $zoneA->id)
        ->assertSee('Yolanda Pérez')
        ->assertDontSee('Carlos Gómez');
});
