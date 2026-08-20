<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;
use Illuminate\Support\Str;

function transferWarehouse(string $name): Warehouse
{
    return Warehouse::create([
        'name' => $name,
        'address' => 'Dirección de prueba',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
    ]);
}

function transferItem(): MasterItem
{
    $category = Category::create(['name' => 'Categoría '.uniqid()]);

    return MasterItem::create([
        'category_id' => $category->id,
        'name' => 'Ítem de prueba',
        'unit_of_measure' => 'unidades',
    ]);
}

test('un operador con ambas bodegas asignadas puede trasladar stock', function () {
    $origin = transferWarehouse('Origen');
    $destination = transferWarehouse('Destino');
    $item = transferItem();
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $origin->id]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $destination->id]);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $origin->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 30,
        'lot_number' => 'L-100',
    ]);

    $response = $this->actingAs($operator)->postJson('/kardex/traslados/sync', [
        'entries' => [[
            'client_uuid' => (string) Str::uuid(),
            'stock_entry_id' => $entry->id,
            'source_warehouse_id' => $origin->id,
            'destination_warehouse_id' => $destination->id,
            'quantity' => 12,
        ]],
    ]);

    $response->assertOk();
    $response->assertJsonPath('results.0.status', 'ok');

    expect($entry->fresh()->availableQuantity())->toBe(18)
        ->and(StockExit::where('stock_entry_id', $entry->id)->first()->exit_reason->value)->toBe('transfer');

    $newEntry = StockEntry::where('warehouse_id', $destination->id)->first();

    expect($newEntry)->not->toBeNull()
        ->and($newEntry->quantity)->toBe(12)
        ->and($newEntry->lot_number)->toBe('L-100')
        ->and($newEntry->transferred_from_stock_entry_id)->toBe($entry->id);
});

test('no se puede trasladar a la misma bodega', function () {
    $origin = transferWarehouse('Única');
    $item = transferItem();
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $origin->id]);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $origin->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 10,
    ]);

    $response = $this->actingAs($operator)->postJson('/kardex/traslados/sync', [
        'entries' => [[
            'client_uuid' => (string) Str::uuid(),
            'stock_entry_id' => $entry->id,
            'source_warehouse_id' => $origin->id,
            'destination_warehouse_id' => $origin->id,
            'quantity' => 5,
        ]],
    ]);

    $response->assertUnprocessable();
    expect(StockEntry::count())->toBe(1);
});

test('no se puede trasladar sin acceso a la bodega destino', function () {
    $origin = transferWarehouse('Origen');
    $destination = transferWarehouse('Destino sin acceso');
    $item = transferItem();
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $origin->id]);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $origin->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 10,
    ]);

    $response = $this->actingAs($operator)->postJson('/kardex/traslados/sync', [
        'entries' => [[
            'client_uuid' => (string) Str::uuid(),
            'stock_entry_id' => $entry->id,
            'source_warehouse_id' => $origin->id,
            'destination_warehouse_id' => $destination->id,
            'quantity' => 5,
        ]],
    ]);

    $response->assertOk();
    $response->assertJsonPath('results.0.status', 'error');
    expect(StockEntry::count())->toBe(1);
});

test('no se puede trasladar más de lo disponible', function () {
    $origin = transferWarehouse('Origen');
    $destination = transferWarehouse('Destino');
    $item = transferItem();
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $origin->id]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $destination->id]);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $origin->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 5,
    ]);

    $response = $this->actingAs($operator)->postJson('/kardex/traslados/sync', [
        'entries' => [[
            'client_uuid' => (string) Str::uuid(),
            'stock_entry_id' => $entry->id,
            'source_warehouse_id' => $origin->id,
            'destination_warehouse_id' => $destination->id,
            'quantity' => 20,
        ]],
    ]);

    $response->assertOk();
    $response->assertJsonPath('results.0.status', 'error');
    expect(StockEntry::count())->toBe(1);
});

test('no se puede trasladar un lote vencido', function () {
    $origin = transferWarehouse('Origen');
    $destination = transferWarehouse('Destino');
    $item = transferItem();
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $origin->id]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $destination->id]);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $origin->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 10,
        'expiry_date' => now()->subDay()->toDateString(),
    ]);

    $response = $this->actingAs($operator)->postJson('/kardex/traslados/sync', [
        'entries' => [[
            'client_uuid' => (string) Str::uuid(),
            'stock_entry_id' => $entry->id,
            'source_warehouse_id' => $origin->id,
            'destination_warehouse_id' => $destination->id,
            'quantity' => 5,
        ]],
    ]);

    $response->assertOk();
    $response->assertJsonPath('results.0.status', 'error');
    expect(StockEntry::count())->toBe(1);
});

test('reenviar el mismo client_uuid no duplica el traslado', function () {
    $origin = transferWarehouse('Origen');
    $destination = transferWarehouse('Destino');
    $item = transferItem();
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $origin->id]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $destination->id]);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $origin->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 30,
    ]);

    $payload = [
        'client_uuid' => (string) Str::uuid(),
        'stock_entry_id' => $entry->id,
        'source_warehouse_id' => $origin->id,
        'destination_warehouse_id' => $destination->id,
        'quantity' => 10,
    ];

    $this->actingAs($operator)->postJson('/kardex/traslados/sync', ['entries' => [$payload]])->assertOk();
    $this->actingAs($operator)->postJson('/kardex/traslados/sync', ['entries' => [$payload]])->assertOk();

    expect(StockEntry::where('warehouse_id', $destination->id)->count())->toBe(1);
});
