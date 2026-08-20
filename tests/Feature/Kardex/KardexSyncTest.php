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

function makeWarehouse(): Warehouse
{
    return Warehouse::create([
        'name' => 'Bodega de prueba',
        'address' => 'Calle 1 #2-3',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
    ]);
}

function makeMasterItem(): MasterItem
{
    $category = Category::create(['name' => 'Categoría de prueba '.Str::random(6)]);

    return MasterItem::create([
        'category_id' => $category->id,
        'name' => 'Ítem de prueba',
        'unit_of_measure' => 'unidades',
    ]);
}

function operatorAssignedTo(Warehouse $warehouse): User
{
    $operator = User::factory()->create(['role' => UserRole::Operator]);

    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id]);

    return $operator;
}

test('un operador con la bodega asignada puede registrar una entrada de stock', function () {
    $warehouse = makeWarehouse();
    $item = makeMasterItem();
    $operator = operatorAssignedTo($warehouse);

    $response = $this->actingAs($operator)->postJson('/kardex/entradas/sync', [
        'entries' => [[
            'client_uuid' => (string) Str::uuid(),
            'warehouse_id' => $warehouse->id,
            'master_item_id' => $item->id,
            'quantity' => 50,
            'lot_number' => 'L-001',
        ]],
    ]);

    $response->assertOk();
    $response->assertJsonPath('results.0.status', 'ok');

    $entry = StockEntry::first();

    expect($entry->quantity)->toBe(50)
        ->and($entry->registered_by_user_id)->toBe($operator->id)
        ->and($entry->status->value)->toBe('available')
        ->and($entry->availableQuantity())->toBe(50);
});

test('reenviar el mismo client_uuid no duplica la entrada', function () {
    $warehouse = makeWarehouse();
    $item = makeMasterItem();
    $operator = operatorAssignedTo($warehouse);

    $payload = [
        'client_uuid' => (string) Str::uuid(),
        'warehouse_id' => $warehouse->id,
        'master_item_id' => $item->id,
        'quantity' => 10,
    ];

    $this->actingAs($operator)->postJson('/kardex/entradas/sync', ['entries' => [$payload]])->assertOk();
    $this->actingAs($operator)->postJson('/kardex/entradas/sync', ['entries' => [$payload]])->assertOk();

    expect(StockEntry::count())->toBe(1);
});

test('un operador sin la bodega asignada no puede registrar una entrada ahí', function () {
    $warehouse = makeWarehouse();
    $item = makeMasterItem();
    $operator = User::factory()->create(['role' => UserRole::Operator]);

    $response = $this->actingAs($operator)->postJson('/kardex/entradas/sync', [
        'entries' => [[
            'client_uuid' => (string) Str::uuid(),
            'warehouse_id' => $warehouse->id,
            'master_item_id' => $item->id,
            'quantity' => 10,
        ]],
    ]);

    $response->assertOk();
    $response->assertJsonPath('results.0.status', 'error');
    expect(StockEntry::count())->toBe(0);
});

test('un coordinador puede registrar stock en cualquier bodega activa sin asignación', function () {
    $warehouse = makeWarehouse();
    $item = makeMasterItem();
    $coordinator = User::factory()->create(['role' => UserRole::Coordinator]);

    $response = $this->actingAs($coordinator)->postJson('/kardex/entradas/sync', [
        'entries' => [[
            'client_uuid' => (string) Str::uuid(),
            'warehouse_id' => $warehouse->id,
            'master_item_id' => $item->id,
            'quantity' => 5,
        ]],
    ]);

    $response->assertOk();
    $response->assertJsonPath('results.0.status', 'ok');
});

test('un operador puede registrar una salida y se descuenta del disponible', function () {
    $warehouse = makeWarehouse();
    $item = makeMasterItem();
    $operator = operatorAssignedTo($warehouse);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
    ]);

    $response = $this->actingAs($operator)->postJson('/kardex/salidas/sync', [
        'entries' => [[
            'client_uuid' => (string) Str::uuid(),
            'stock_entry_id' => $entry->id,
            'warehouse_id' => $warehouse->id,
            'quantity_released' => 8,
            'exit_reason' => 'donation',
            'received_by_name' => 'Juan Pérez',
        ]],
    ]);

    $response->assertOk();
    $response->assertJsonPath('results.0.status', 'ok');

    expect($entry->fresh()->availableQuantity())->toBe(12)
        ->and(StockExit::first()->released_by_user_id)->toBe($operator->id)
        ->and(StockExit::first()->received_by_name)->toBe('Juan Pérez');
});

test('una salida rechaza cantidades mayores al stock disponible', function () {
    $warehouse = makeWarehouse();
    $item = makeMasterItem();
    $operator = operatorAssignedTo($warehouse);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 5,
    ]);

    $response = $this->actingAs($operator)->postJson('/kardex/salidas/sync', [
        'entries' => [[
            'client_uuid' => (string) Str::uuid(),
            'stock_entry_id' => $entry->id,
            'warehouse_id' => $warehouse->id,
            'quantity_released' => 10,
            'exit_reason' => 'donation',
        ]],
    ]);

    $response->assertOk();
    $response->assertJsonPath('results.0.status', 'error');
    expect(StockExit::count())->toBe(0);
});

test('una salida rechaza un lote vencido', function () {
    $warehouse = makeWarehouse();
    $item = makeMasterItem();
    $operator = operatorAssignedTo($warehouse);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
        'expiry_date' => now()->subDay()->toDateString(),
    ]);

    $response = $this->actingAs($operator)->postJson('/kardex/salidas/sync', [
        'entries' => [[
            'client_uuid' => (string) Str::uuid(),
            'stock_entry_id' => $entry->id,
            'warehouse_id' => $warehouse->id,
            'quantity_released' => 5,
            'exit_reason' => 'donation',
        ]],
    ]);

    $response->assertOk();
    $response->assertJsonPath('results.0.status', 'error');
    $response->assertJsonPath('results.0.message', 'Este lote está vencido y no puede despacharse.');
    expect(StockExit::count())->toBe(0);
});

test('un lote vencido no aparece en el desplegable del formulario de salida', function () {
    $warehouse = makeWarehouse();
    $item = makeMasterItem();
    $operator = operatorAssignedTo($warehouse);

    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
        'expiry_date' => now()->subDay()->toDateString(),
    ]);

    $response = $this->actingAs($operator)->get('/kardex/salida');

    $response->assertOk();
    $response->assertSee('No hay lotes con existencias disponibles');
});

test('reenviar el mismo client_uuid no duplica la salida', function () {
    $warehouse = makeWarehouse();
    $item = makeMasterItem();
    $operator = operatorAssignedTo($warehouse);

    $entry = StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
    ]);

    $payload = [
        'client_uuid' => (string) Str::uuid(),
        'stock_entry_id' => $entry->id,
        'warehouse_id' => $warehouse->id,
        'quantity_released' => 3,
        'exit_reason' => 'donation',
    ];

    $this->actingAs($operator)->postJson('/kardex/salidas/sync', ['entries' => [$payload]])->assertOk();
    $this->actingAs($operator)->postJson('/kardex/salidas/sync', ['entries' => [$payload]])->assertOk();

    expect(StockExit::count())->toBe(1);
});

test('rechaza la entrada si falta un campo obligatorio', function () {
    $warehouse = makeWarehouse();
    $operator = operatorAssignedTo($warehouse);

    $response = $this->actingAs($operator)->postJson('/kardex/entradas/sync', [
        'entries' => [[
            'client_uuid' => (string) Str::uuid(),
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
        ]],
    ]);

    $response->assertUnprocessable();
    expect(StockEntry::count())->toBe(0);
});

test('un rol sin acceso de campo no puede usar el kardex', function () {
    $warehouse = makeWarehouse();
    $item = makeMasterItem();
    $donor = User::factory()->create(['role' => UserRole::Donor]);

    $this->actingAs($donor)->get('/kardex')->assertForbidden();
    $this->actingAs($donor)->get('/kardex/entrada')->assertForbidden();
    $this->actingAs($donor)->get('/kardex/salida')->assertForbidden();

    $this->actingAs($donor)->postJson('/kardex/entradas/sync', [
        'entries' => [[
            'client_uuid' => (string) Str::uuid(),
            'warehouse_id' => $warehouse->id,
            'master_item_id' => $item->id,
            'quantity' => 1,
        ]],
    ])->assertForbidden();
});

test('un invitado no puede usar el kardex', function () {
    $this->postJson('/kardex/entradas/sync', ['entries' => []])->assertUnauthorized();
    $this->get('/kardex')->assertRedirect(route('login'));
});

test('un operador ve el listado, el formulario de entrada y el de salida', function () {
    $warehouse = makeWarehouse();
    makeMasterItem();
    $operator = operatorAssignedTo($warehouse);

    $this->actingAs($operator)->get('/kardex')->assertOk();
    $this->actingAs($operator)->get('/kardex/entrada')->assertOk();
    $this->actingAs($operator)->get('/kardex/salida')->assertOk();
});
