<?php

use App\Enums\MasterItemStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\MasterItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;
use Livewire\Livewire;

test('un operador puede solicitar un item nuevo desde el formulario de entrada', function () {
    $warehouse = Warehouse::create([
        'name' => 'Bodega de prueba',
        'address' => 'Dirección de prueba',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
    ]);
    $category = Category::create(['name' => 'Categoría '.uniqid()]);
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id]);

    Livewire::actingAs($operator)
        ->test('pages::kardex.entry-form')
        ->call('openRequestItemModal')
        ->set('requestName', 'Ibuprofeno 400mg')
        ->set('requestCategoryId', $category->id)
        ->set('requestUnitOfMeasure', 'cajas')
        ->call('requestNewItem')
        ->assertHasNoErrors();

    $item = MasterItem::where('name', 'Ibuprofeno 400mg')->first();

    expect($item)->not->toBeNull()
        ->and($item->status)->toBe(MasterItemStatus::UnderReview)
        ->and($item->created_by_user_id)->toBe($operator->id);
});

test('un item recien solicitado no aparece en el selector de la entrada', function () {
    $warehouse = Warehouse::create([
        'name' => 'Bodega de prueba',
        'address' => 'Dirección de prueba',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
    ]);
    $category = Category::create(['name' => 'Categoría '.uniqid()]);
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $warehouse->id]);

    Livewire::actingAs($operator)
        ->test('pages::kardex.entry-form')
        ->set('requestName', 'Ibuprofeno 400mg')
        ->set('requestCategoryId', $category->id)
        ->set('requestUnitOfMeasure', 'cajas')
        ->call('requestNewItem')
        ->assertDontSee('Ibuprofeno 400mg');
});

test('la solicitud de item nuevo valida los campos obligatorios', function () {
    $operator = User::factory()->create(['role' => UserRole::Operator]);

    Livewire::actingAs($operator)
        ->test('pages::kardex.entry-form')
        ->set('requestName', '')
        ->call('requestNewItem')
        ->assertHasErrors(['requestName', 'requestCategoryId', 'requestUnitOfMeasure']);

    expect(MasterItem::count())->toBe(0);
});

test('roles sin permiso no pueden solicitar un item nuevo', function () {
    $donor = User::factory()->create(['role' => UserRole::Donor]);
    $category = Category::create(['name' => 'Categoría '.uniqid()]);

    Livewire::actingAs($donor)
        ->test('pages::kardex.entry-form')
        ->set('requestName', 'Ibuprofeno 400mg')
        ->set('requestCategoryId', $category->id)
        ->set('requestUnitOfMeasure', 'cajas')
        ->call('requestNewItem')
        ->assertForbidden();
});
