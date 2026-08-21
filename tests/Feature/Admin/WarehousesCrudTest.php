<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\GeographicZone;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;

function makeAdmin(): User
{
    return User::factory()->admin()->create();
}

test('un admin puede ver el listado de bodegas', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)->get(route('admin.warehouses.index'))->assertOk();
});

test('roles sin permiso no pueden ver el listado de bodegas', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('admin.warehouses.index'))->assertForbidden();
})->with([UserRole::Operator, UserRole::Coordinator, UserRole::Donor, UserRole::Doctor, UserRole::Municipal]);

test('un admin puede crear una bodega desde el modal', function () {
    $admin = makeAdmin();
    $zone = GeographicZone::create(['zone_type' => 'municipio', 'name' => 'Roldanillo']);

    Livewire::actingAs($admin)
        ->test('pages::admin.warehouses')
        ->call('openCreate')
        ->set('name', 'Bodega Norte')
        ->set('address', 'Calle 20 #4-10')
        ->set('geographic_zone_id', $zone->id)
        ->set('contact_person_name', 'María Pérez')
        ->set('contact_phone', '3130000000')
        ->call('save')
        ->assertHasNoErrors();

    $warehouse = Warehouse::where('name', 'Bodega Norte')->first();

    expect($warehouse)->not->toBeNull()
        ->and($warehouse->is_active)->toBeTrue()
        ->and($warehouse->contact_phone)->toBe('3130000000')
        ->and($warehouse->geographic_zone_id)->toBe($zone->id);
});

test('la creación de bodega valida los campos obligatorios', function () {
    $admin = makeAdmin();

    Livewire::actingAs($admin)
        ->test('pages::admin.warehouses')
        ->call('openCreate')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name', 'address', 'contact_person_name', 'contact_phone']);

    expect(Warehouse::count())->toBe(0);
});

test('un admin puede editar una bodega existente', function () {
    $admin = makeAdmin();
    $warehouse = Warehouse::create([
        'name' => 'Bodega Vieja',
        'address' => 'Dirección original',
        'contact_person_name' => 'Contacto original',
        'contact_phone' => '3000000000',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.warehouses')
        ->call('openEdit', $warehouse->id)
        ->assertSet('contact_phone', '3000000000')
        ->set('name', 'Bodega Renombrada')
        ->call('save')
        ->assertHasNoErrors();

    expect($warehouse->fresh()->name)->toBe('Bodega Renombrada');
});

test('un admin puede activar y desactivar una bodega', function () {
    $admin = makeAdmin();
    $warehouse = Warehouse::create([
        'name' => 'Bodega Toggle',
        'address' => 'Dirección',
        'contact_person_name' => 'Contacto',
        'contact_phone' => '3000000000',
    ]);

    expect($warehouse->fresh()->is_active)->toBeTrue();

    Livewire::actingAs($admin)->test('pages::admin.warehouses')->call('toggleActive', $warehouse->id);
    expect($warehouse->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($admin)->test('pages::admin.warehouses')->call('toggleActive', $warehouse->id);
    expect($warehouse->fresh()->is_active)->toBeTrue();
});

test('el listado muestra la ocupacion frente a la capacidad maxima', function () {
    $admin = makeAdmin();
    $warehouse = Warehouse::create([
        'name' => 'Bodega con límite',
        'address' => 'Dirección',
        'contact_person_name' => 'Contacto',
        'contact_phone' => '3000000000',
        'max_capacity_units' => 100,
    ]);

    $category = Category::create(['name' => 'Categoría '.uniqid()]);
    $item = MasterItem::create(['category_id' => $category->id, 'name' => 'Arroz', 'unit_of_measure' => 'kg']);

    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $admin->id,
        'quantity' => 40,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::admin.warehouses')
        ->assertSee('40 / 100');
});
