<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;
use Livewire\Livewire;

test('un operador puede ver la ficha kardex de un item', function () {
    $warehouse = Warehouse::create([
        'name' => 'Bodega de prueba',
        'address' => 'Dirección de prueba',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
    ]);

    $category = Category::create(['name' => 'Categoría '.uniqid()]);

    $item = MasterItem::create([
        'category_id' => $category->id,
        'name' => 'Arroz',
        'unit_of_measure' => 'kg',
    ]);

    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id]);

    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
        'lot_number' => 'L-100',
    ]);

    Livewire::actingAs($operator)
        ->test('pages::kardex.ledger')
        ->assertSee('Selecciona un ítem')
        ->set('itemId', $item->id)
        ->assertSee('L-100')
        ->assertSee('Entrada');
});

test('roles sin permiso no pueden ver la ficha kardex', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('kardex.ledger'))->assertForbidden();
})->with([UserRole::Donor, UserRole::Doctor, UserRole::Municipal]);
