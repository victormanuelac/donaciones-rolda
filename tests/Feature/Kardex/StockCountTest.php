<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\MasterItem;
use App\Models\StockCount;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;
use Livewire\Livewire;

function countWarehouse(string $name = 'Bodega de prueba'): Warehouse
{
    return Warehouse::create([
        'name' => $name,
        'address' => 'Dirección de prueba',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
    ]);
}

function countItem(): MasterItem
{
    $category = Category::create(['name' => 'Categoría '.uniqid()]);

    return MasterItem::create([
        'category_id' => $category->id,
        'name' => 'Arroz',
        'unit_of_measure' => 'kg',
    ]);
}

function countOperator(Warehouse $warehouse): User
{
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id]);

    return $operator;
}

test('roles sin permiso no pueden ver el conteo fisico', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('kardex.count'))->assertForbidden();
})->with([UserRole::Donor, UserRole::Doctor, UserRole::Municipal]);

test('un conteo que coincide con el sistema no genera movimientos', function () {
    $warehouse = countWarehouse();
    $item = countItem();
    $operator = countOperator($warehouse);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
    ]);

    Livewire::actingAs($operator)
        ->test('pages::kardex.count')
        ->set('stock_entry_id', $entry->id)
        ->set('counted_quantity', 20)
        ->call('register')
        ->assertHasNoErrors();

    expect(StockCount::count())->toBe(1)
        ->and(StockCount::first()->difference)->toBe(0)
        ->and(StockExit::count())->toBe(0)
        ->and(StockEntry::count())->toBe(1)
        ->and($entry->fresh()->availableQuantity())->toBe(20);
});

test('un conteo con menos unidades da de baja la diferencia', function () {
    $warehouse = countWarehouse();
    $item = countItem();
    $operator = countOperator($warehouse);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
    ]);

    Livewire::actingAs($operator)
        ->test('pages::kardex.count')
        ->set('stock_entry_id', $entry->id)
        ->set('counted_quantity', 15)
        ->set('notes', 'Faltante detectado en conteo mensual')
        ->call('register')
        ->assertHasNoErrors();

    $count = StockCount::first();
    $exit = StockExit::first();

    expect($count->difference)->toBe(-5)
        ->and($exit)->not->toBeNull()
        ->and($exit->exit_reason->value)->toBe('inventory_adjustment')
        ->and($exit->quantity_released)->toBe(5)
        ->and($entry->fresh()->availableQuantity())->toBe(15);
});

test('un conteo con mas unidades crea una entrada de ajuste', function () {
    $warehouse = countWarehouse();
    $item = countItem();
    $operator = countOperator($warehouse);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
        'lot_number' => 'L-1',
    ]);

    Livewire::actingAs($operator)
        ->test('pages::kardex.count')
        ->set('stock_entry_id', $entry->id)
        ->set('counted_quantity', 25)
        ->call('register')
        ->assertHasNoErrors();

    $count = StockCount::first();
    $adjustmentEntry = StockEntry::where('adjustment_stock_count_id', $count->id)->first();

    expect($count->difference)->toBe(5)
        ->and($adjustmentEntry)->not->toBeNull()
        ->and($adjustmentEntry->quantity)->toBe(5)
        ->and($adjustmentEntry->lot_number)->toBe('L-1')
        ->and($item->totalAvailableQuantity($warehouse->id))->toBe(25);
});

test('el ajuste por conteo puede aplicarse sobre un lote vencido', function () {
    $warehouse = countWarehouse();
    $item = countItem();
    $operator = countOperator($warehouse);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 10,
        'status' => 'expired',
        'expiry_date' => now()->subDay()->toDateString(),
    ]);

    Livewire::actingAs($operator)
        ->test('pages::kardex.count')
        ->set('stock_entry_id', $entry->id)
        ->set('counted_quantity', 6)
        ->call('register')
        ->assertHasNoErrors();

    expect(StockExit::where('exit_reason', 'inventory_adjustment')->count())->toBe(1)
        ->and($entry->fresh()->availableQuantity())->toBe(6);
});

test('un operador solo puede contar lotes de sus bodegas asignadas', function () {
    $ownWarehouse = countWarehouse('Propia');
    $otherWarehouse = countWarehouse('Ajena');
    $item = countItem();
    $operator = countOperator($ownWarehouse);

    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $otherWarehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
    ]);

    Livewire::actingAs($operator)
        ->test('pages::kardex.count')
        ->assertSee('No hay lotes disponibles para contar en tus bodegas asignadas.');
});
