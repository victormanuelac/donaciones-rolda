<?php

use App\Enums\MasterItemStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\MasterItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;
use Livewire\Livewire;

/**
 * El nombre del ítem termina renderizándose en el mapa del portal público, que
 * es anónimo. El escapado real vive en el front (`results-map.js` arma el popup
 * con nodos del DOM), esto es la defensa en profundidad del lado del servidor
 * — ver docs/17-Auditoria-Frontend.md, hallazgo C-1.
 */
function sanitizationCategory(): Category
{
    return Category::create(['name' => 'Medicamentos '.uniqid()]);
}

test('un operador no puede solicitar un item con etiquetas html en el nombre', function () {
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    $warehouse = Warehouse::create(['name' => 'Bodega Centro', 'address' => 'Centro', 'contact_person_name' => 'Coordinador', 'contact_phone' => '3000000000', 'is_active' => true]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id, 'is_active' => true]);

    Livewire::actingAs($operator)
        ->test('pages::kardex.entry-form')
        ->call('openRequestItemModal')
        ->set('requestName', '<img src=x onerror=alert(1)>')
        ->set('requestCategoryId', sanitizationCategory()->id)
        ->set('requestUnitOfMeasure', 'cajas')
        ->call('requestNewItem')
        ->assertHasErrors(['requestName']);

    expect(MasterItem::count())->toBe(0);
});

test('un operador si puede solicitar un item con un nombre normal', function () {
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    $warehouse = Warehouse::create(['name' => 'Bodega Centro', 'address' => 'Centro', 'contact_person_name' => 'Coordinador', 'contact_phone' => '3000000000', 'is_active' => true]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id, 'is_active' => true]);

    Livewire::actingAs($operator)
        ->test('pages::kardex.entry-form')
        ->call('openRequestItemModal')
        ->set('requestName', 'Suero oral 500ml')
        ->set('requestCategoryId', sanitizationCategory()->id)
        ->set('requestUnitOfMeasure', 'bolsas')
        ->call('requestNewItem')
        ->assertHasNoErrors();

    expect(MasterItem::where('name', 'Suero oral 500ml')->exists())->toBeTrue();
});

test('un admin no puede colar etiquetas html al corregir el nombre antes de aprobar', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $item = MasterItem::create([
        'category_id' => sanitizationCategory()->id,
        'name' => 'Ibuprofeno 400mg',
        'unit_of_measure' => 'cajas',
        'status' => MasterItemStatus::UnderReview,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.items-pending')
        ->set("name.{$item->id}", '<script>alert(1)</script>')
        ->call('approve', $item->id)
        ->assertHasErrors(["name.{$item->id}"]);

    expect($item->fresh()->status)->toBe(MasterItemStatus::UnderReview);
});
