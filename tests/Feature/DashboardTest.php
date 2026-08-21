<?php

use App\Enums\CensusPriorityLevel;
use App\Enums\MasterItemStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\CensusEntry;
use App\Models\Family;
use App\Models\MasterItem;
use App\Models\User;
use App\Models\Warehouse;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('el panel muestra el conteo real de hogares censados, no un cero fijo', function () {
    $coordinator = User::factory()->create(['role' => UserRole::Coordinator]);

    $family = Family::create([
        'zone_type' => 'urbana',
        'address' => 'Calle 1 #2-3',
        'head_full_name' => 'Jefa de hogar',
        'housing_damage_level' => 'sin_dano',
        'household_size' => 4,
    ]);

    CensusEntry::create([
        'family_id' => $family->id,
        'user_id' => $coordinator->id,
        'form_code' => 'ROL-2026-TEST1',
        'surveyor_entity' => 'donaciones_rolda',
        'surveyed_at' => now(),
        'consent_given' => true,
        'total_people' => 4,
        'sleeping_location' => 'vivienda_propia',
        'needs_temporary_shelter' => 'no',
        'access_passable' => 'si',
        'priority_needs' => ['agua'],
        'registered_in_rud' => 'no_sabe',
        'damage_verified' => 'no',
        'vulnerability_score' => 80,
        'priority_level' => CensusPriorityLevel::Critico,
    ]);

    $this->actingAs($coordinator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Hogares censados')
        ->assertSeeInOrder(['Hogares censados', '1'])
        ->assertSeeInOrder(['Personas cubiertas', '4'])
        ->assertDontSee('Todavía no hay módulos operativos');
});

test('el panel muestra el conteo real de bodegas e items pendientes para el admin', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Warehouse::create(['name' => 'Bodega Centro', 'address' => 'Centro', 'contact_person_name' => 'Coordinador', 'contact_phone' => '3000000000', 'is_active' => true]);
    Warehouse::create(['name' => 'Bodega Cerrada', 'address' => 'Sur', 'contact_person_name' => 'Coordinador', 'contact_phone' => '3000000001', 'is_active' => false]);

    $category = Category::create(['name' => 'Medicamentos '.uniqid()]);
    MasterItem::create([
        'category_id' => $category->id,
        'name' => 'Ibuprofeno 400mg',
        'unit_of_measure' => 'cajas',
        'status' => MasterItemStatus::UnderReview,
    ]);

    User::factory()->create(['status' => UserStatus::Pending]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Bodegas activas', '1'])
        ->assertSeeInOrder(['Ítems por revisar', '1'])
        ->assertSeeInOrder(['Usuarios por aprobar', '1']);
});

test('un rol sin modulos asignados no ve conteos de inventario ni de censo', function () {
    $donor = User::factory()->create(['role' => UserRole::Donor]);

    Family::create([
        'zone_type' => 'urbana',
        'address' => 'Calle 1 #2-3',
        'head_full_name' => 'Jefa de hogar',
        'housing_damage_level' => 'sin_dano',
        'household_size' => 4,
    ]);

    $this->actingAs($donor)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Hogares censados')
        ->assertDontSee('Lotes disponibles')
        ->assertSee('Tu rol todavía no tiene módulos asignados');
});
