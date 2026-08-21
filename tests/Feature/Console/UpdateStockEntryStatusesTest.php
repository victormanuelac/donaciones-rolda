<?php

use App\Enums\StockEntryStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\User;
use App\Models\Warehouse;

function makeStockEntry(array $overrides = []): StockEntry
{
    $warehouse = Warehouse::create([
        'name' => 'Bodega de prueba',
        'address' => 'Calle 1 #2-3',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
    ]);

    $category = Category::create(['name' => 'Categoría '.uniqid()]);

    $item = MasterItem::create([
        'category_id' => $category->id,
        'name' => 'Ítem de prueba',
        'unit_of_measure' => 'unidades',
    ]);

    $operator = User::factory()->create(['role' => UserRole::Operator]);

    return StockEntry::create([...[
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $operator->id,
        'quantity' => 20,
    ], ...$overrides]);
}

test('marca como vencido un lote disponible cuya fecha ya pasó', function () {
    $entry = makeStockEntry(['expiry_date' => now()->subDay()->toDateString()]);

    $this->artisan('kardex:update-stock-entry-statuses')->assertSuccessful();

    expect($entry->fresh()->status)->toBe(StockEntryStatus::Expired);
});

test('no toca un lote disponible que aún no vence', function () {
    $entry = makeStockEntry(['expiry_date' => now()->addMonth()->toDateString()]);

    $this->artisan('kardex:update-stock-entry-statuses')->assertSuccessful();

    expect($entry->fresh()->status)->toBe(StockEntryStatus::Available);
});

test('marca como retirado un lote agotado, aunque esté vencido', function () {
    $entry = makeStockEntry(['quantity' => 10, 'expiry_date' => now()->subDay()->toDateString()]);

    StockExit::create([
        'stock_entry_id' => $entry->id,
        'warehouse_id' => $entry->warehouse_id,
        'released_by_user_id' => $entry->registered_by_user_id,
        'exit_reason' => 'donation',
        'quantity_released' => 10,
    ]);

    $this->artisan('kardex:update-stock-entry-statuses')->assertSuccessful();

    expect($entry->fresh()->status)->toBe(StockEntryStatus::Withdrawn);
});

test('no reevalúa un lote ya retirado', function () {
    $entry = makeStockEntry(['quantity' => 10, 'status' => StockEntryStatus::Withdrawn]);

    StockExit::create([
        'stock_entry_id' => $entry->id,
        'warehouse_id' => $entry->warehouse_id,
        'released_by_user_id' => $entry->registered_by_user_id,
        'exit_reason' => 'donation',
        'quantity_released' => 10,
    ]);

    $this->artisan('kardex:update-stock-entry-statuses')->assertSuccessful();

    // Sigue "withdrawn" (no hay error ni cambio de estado inesperado).
    expect($entry->fresh()->status)->toBe(StockEntryStatus::Withdrawn);
});
