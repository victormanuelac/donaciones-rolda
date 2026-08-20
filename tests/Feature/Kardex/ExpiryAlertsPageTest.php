<?php

use App\Enums\ExpiryAlertType;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\ExpiryAlert;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;
use Livewire\Livewire;

function alertsWarehouse(): Warehouse
{
    return Warehouse::create([
        'name' => 'Bodega de alertas',
        'address' => 'Dirección',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
    ]);
}

function alertsItem(): MasterItem
{
    $category = Category::create(['name' => 'Categoría '.uniqid()]);

    return MasterItem::create([
        'category_id' => $category->id,
        'name' => 'Ítem de prueba',
        'unit_of_measure' => 'unidades',
    ]);
}

test('un operador ve las alertas de vencimiento de sus bodegas asignadas', function () {
    $warehouse = alertsWarehouse();
    $item = alertsItem();
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id]);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 10,
        'expiry_date' => now()->addDays(5)->toDateString(),
    ]);

    ExpiryAlert::create(['stock_entry_id' => $entry->id, 'alert_type' => ExpiryAlertType::SevenDays]);

    $response = $this->actingAs($operator)->get('/kardex/vencimientos');

    $response->assertOk();
    $response->assertSee($item->name);
});

test('un operador no ve alertas de bodegas que no tiene asignadas', function () {
    $warehouse = alertsWarehouse();
    $item = alertsItem();
    $operator = User::factory()->create(['role' => UserRole::Operator]);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 10,
        'expiry_date' => now()->addDays(5)->toDateString(),
    ]);

    ExpiryAlert::create(['stock_entry_id' => $entry->id, 'alert_type' => ExpiryAlertType::SevenDays]);

    $response = $this->actingAs($operator)->get('/kardex/vencimientos');

    $response->assertOk();
    $response->assertDontSee($item->name);
});

test('resolver una alerta como descartado genera una salida y descuenta el disponible', function () {
    $warehouse = alertsWarehouse();
    $item = alertsItem();
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id]);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 15,
        'expiry_date' => now()->addDays(3)->toDateString(),
    ]);

    $alert = ExpiryAlert::create(['stock_entry_id' => $entry->id, 'alert_type' => ExpiryAlertType::SevenDays]);

    Livewire::actingAs($operator)
        ->test('pages::kardex.expiry-alerts')
        ->call('openResolve', $alert->id)
        ->set('resolution_action', 'discarded')
        ->set('resolution_notes', 'Empaque roto')
        ->call('resolve')
        ->assertHasNoErrors();

    expect($alert->fresh()->resolved_at)->not->toBeNull()
        ->and($entry->fresh()->availableQuantity())->toBe(0);
});

test('resolver una alerta como usado no genera ninguna salida', function () {
    $warehouse = alertsWarehouse();
    $item = alertsItem();
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id]);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 15,
        'expiry_date' => now()->addDays(3)->toDateString(),
    ]);

    $alert = ExpiryAlert::create(['stock_entry_id' => $entry->id, 'alert_type' => ExpiryAlertType::SevenDays]);

    Livewire::actingAs($operator)
        ->test('pages::kardex.expiry-alerts')
        ->call('openResolve', $alert->id)
        ->set('resolution_action', 'used')
        ->call('resolve')
        ->assertHasNoErrors();

    expect($entry->fresh()->availableQuantity())->toBe(15);
});
