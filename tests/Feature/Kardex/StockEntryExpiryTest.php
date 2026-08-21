<?php

use App\Models\Category;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\User;
use App\Models\Warehouse;

/**
 * `diffInDays()` devuelve un valor **con signo** en Carbon 3: una fecha futura
 * da negativo. La comparación ingenua `expiry_date->diffInDays(now()) <= 30`
 * era verdadera para cualquier vencimiento futuro y pintaba todo el Kardex en
 * rojo — docs/17-Auditoria-Frontend.md, hallazgo A-3.
 */
function expiryEntry(?string $expiryDate): StockEntry
{
    $warehouse = Warehouse::create([
        'name' => 'Bodega '.uniqid(),
        'address' => 'Dirección',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
    ]);

    $category = Category::create(['name' => 'Categoría '.uniqid()]);
    $item = MasterItem::create(['category_id' => $category->id, 'name' => 'Ítem '.uniqid(), 'unit_of_measure' => 'uds']);

    return StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => User::factory()->create()->id,
        'quantity' => 10,
        'expiry_date' => $expiryDate,
        'received_date' => now(),
        'status' => 'available',
    ]);
}

test('un lote que vence dentro de mucho tiempo no se marca como proximo a vencer', function () {
    expect(expiryEntry(now()->addDays(200)->toDateString())->isExpiringSoon())->toBeFalse();
});

test('un lote que vence pasado el umbral por un dia todavia no se marca', function () {
    expect(expiryEntry(now()->addDays(31)->toDateString())->isExpiringSoon())->toBeFalse();
});

test('un lote que vence justo en el umbral si se marca', function () {
    expect(expiryEntry(now()->addDays(30)->toDateString())->isExpiringSoon())->toBeTrue();
});

test('un lote que vence en pocos dias se marca como proximo a vencer', function () {
    expect(expiryEntry(now()->addDays(5)->toDateString())->isExpiringSoon())->toBeTrue();
});

test('un lote ya vencido se marca como proximo a vencer', function () {
    expect(expiryEntry(now()->subDays(3)->toDateString())->isExpiringSoon())->toBeTrue();
});

test('un lote sin fecha de vencimiento nunca se marca', function () {
    expect(expiryEntry(null)->isExpiringSoon())->toBeFalse();
});

test('el umbral es configurable', function () {
    $entry = expiryEntry(now()->addDays(45)->toDateString());

    expect($entry->isExpiringSoon(30))->toBeFalse()
        ->and($entry->isExpiringSoon(60))->toBeTrue();
});
