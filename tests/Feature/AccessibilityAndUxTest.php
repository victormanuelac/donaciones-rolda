<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Family;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\User;
use App\Models\Warehouse;

/**
 * Regresiones de accesibilidad y consistencia de interfaz del Bloque 3
 * (docs/17-Auditoria-Frontend.md): A-4, B-1 y B-2.
 */
test('el layout autenticado tiene landmark main y enlace para saltar la navegacion', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('id="contenido"', escape: false)
        ->assertSee('href="#contenido"', escape: false)
        ->assertSee('Saltar al contenido');
});

test('la interfaz no deja cadenas del starter kit sin traducir', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Plataforma')
        ->assertDontSee('>Platform<', escape: false)
        ->assertDontSee('>Log out<', escape: false)
        ->assertDontSee('>Settings<', escape: false);
});

test('las tablas se pueden desplazar en horizontal en vez de recortarse', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    // Las tablas solo se renderizan si hay datos; sin esto se ve el estado vacío.
    $warehouse = Warehouse::create([
        'name' => 'Bodega Centro',
        'address' => 'Centro',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
    ]);
    $category = Category::create(['name' => 'Categoría '.uniqid()]);
    $item = MasterItem::create(['category_id' => $category->id, 'name' => 'Suero oral', 'unit_of_measure' => 'bolsas']);
    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $admin->id,
        'quantity' => 30,
        'received_date' => now(),
        'status' => 'available',
    ]);
    Family::create([
        'zone_type' => 'urbana',
        'address' => 'Calle 1 #2-3',
        'head_full_name' => 'Jefa de hogar',
        'housing_damage_level' => 'sin_dano',
        'household_size' => 3,
    ]);

    // Kardex y Beneficiarios son las que se consultan desde el teléfono en campo.
    foreach ([route('kardex.index'), route('beneficiaries.index')] as $url) {
        $this->actingAs($admin)
            ->get($url)
            ->assertOk()
            ->assertSee('overflow-x-auto', escape: false);
    }
});

test('los encabezados de columna declaran su alcance para lectores de pantalla', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    User::factory()->create(['status' => UserStatus::Pending]);

    $this->actingAs($admin)
        ->get(route('admin.users.pending'))
        ->assertOk()
        ->assertSee('<th scope="col"', escape: false);
});
